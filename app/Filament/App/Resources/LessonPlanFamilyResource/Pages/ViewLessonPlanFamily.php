<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Ai\Agents\LessonPlanAdvisor;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\DeletionRequestService;
use App\Services\FavoriteService;
use App\Services\MarkdownSelectionMatcher;
use App\Services\VersionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Laravel\Ai\Streaming\Events\TextDelta;

class ViewLessonPlanFamily extends Page
{
    protected static string $resource = LessonPlanFamilyResource::class;

    protected string $view = 'filament.app.pages.view-lesson-plan-family';

    public LessonPlanFamily $record;

    public ?LessonPlanVersion $selectedVersion = null;

    public ?LessonPlanVersion $compareVersion = null;

    public bool $compareMode = false;

    public bool $editMode = false;

    public string $editContent = '';

    public string $versionBump = 'patch';

    public ?string $revisionNote = null;

    public ?Favorite $userFavorite = null;

    public bool $hasPendingDeletion = false;

    public bool $showDeletionForm = false;

    public string $deletionReason = '';

    // AI panel state
    public bool $aiPanelOpen = false;

    public string $aiPrompt = '';

    public string $aiResponse = '';

    public function getTitle(): string
    {
        return 'View / Edit Lesson Plan';
    }

    public function mount(LessonPlanFamily $record): void
    {
        $this->record = $record->load(['versions.contributor', 'officialVersion', 'latestVersion', 'subjectGrade.subject']);
        $this->selectedVersion = $record->officialVersion ?? $record->latestVersion;
        $this->syncDerivedState();
    }

    private function syncDerivedState(): void
    {
        $user = auth()->user();

        $this->userFavorite = $user
            ? Favorite::where('user_id', $user->id)
                ->where('lesson_plan_family_id', $this->record->id)
                ->with('version')
                ->first()
            : null;

        $this->hasPendingDeletion = (bool) ($this->selectedVersion?->deletionRequests()
            ->whereNull('resolved_at')
            ->exists());
    }

    public function selectVersion(int $versionId): void
    {
        $version = $this->record->versions->find($versionId);

        if (! $version) {
            return;
        }

        $this->selectedVersion = $version;
        $this->compareMode = false;
        $this->compareVersion = null;
        $this->hasPendingDeletion = (bool) $this->selectedVersion->deletionRequests()
            ->whereNull('resolved_at')
            ->exists();
    }

    public function enterEditMode(): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);
        $this->enterEditModeIfNeeded();
    }

    private function enterEditModeIfNeeded(): void
    {
        if (! $this->editMode) {
            $this->editContent = $this->selectedVersion?->content ?? '';
            $this->editMode = true;
        }
    }

    public function saveNewVersion(VersionService $versionService): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);

        $this->validate([
            'editContent' => 'required|string',
            'versionBump' => 'required|in:patch,minor,major',
        ]);

        $version = $versionService->addVersion(
            $this->record,
            $this->editContent,
            $this->versionBump,
            $this->revisionNote ?: null,
            auth()->user()
        );

        $this->record->refresh();
        $this->selectedVersion = $version;
        $this->editMode = false;
        $this->revisionNote = null;
        $this->hasPendingDeletion = false; // new versions have no pending requests

        Notification::make('version-saved')->title('New version saved.')->success()->send();
    }

    public function markOfficial(VersionService $versionService): void
    {
        $this->authorize('markOfficial', $this->selectedVersion);

        $versionService->setOfficialVersion($this->record, $this->selectedVersion);
        $this->record->refresh();
        $this->selectedVersion = $this->selectedVersion->fresh();

        Notification::make('official-updated')->title('Official version updated.')->success()->send();
    }

    public function favorite(FavoriteService $favoriteService): void
    {
        abort_unless(auth()->check(), 403);

        $this->userFavorite = $favoriteService->upsert(auth()->user(), $this->selectedVersion);

        Notification::make('favorited')->title('Added to favorites.')->success()->send();
    }

    /** Returns ['major' => '2.0.0', 'minor' => '1.1.0', 'patch' => '1.0.1'] based on current versions. */
    public function versionPreviews(): array
    {
        return app(VersionService::class)->computeAllNextVersions($this->record);
    }

    public function requestDeletion(DeletionRequestService $service): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('requestDeletion', $this->selectedVersion);

        $user = auth()->user();

        // Prevent duplicate pending requests for the same version.
        if ($this->hasPendingDeletion) {
            Notification::make('deletion-already-pending')
                ->title('A pending deletion request already exists for this version.')
                ->warning()
                ->send();
            $this->showDeletionForm = false;

            return;
        }

        $service->request(
            $this->selectedVersion,
            $user,
            filled($this->deletionReason) ? $this->deletionReason : null
        );

        $this->showDeletionForm = false;
        $this->deletionReason = '';
        $this->hasPendingDeletion = true;

        Notification::make('deletion-requested')
            ->title('Deletion request submitted — Site Admins have been notified.')
            ->success()
            ->send();
    }

    public function enterCompareMode(int $versionId): void
    {
        $other = $this->record->versions->find($versionId);

        if (! $other) {
            return;
        }

        $this->compareVersion = $other;
        $this->compareMode = true;
    }

    /**
     * Called from Alpine when the user clicks "Edit Selected Text".
     * Resolves the rendered selection back to its byte offsets in the Markdown
     * source, enters edit mode, then dispatches a browser event so Alpine can
     * scroll and highlight the corresponding range in the source textarea.
     */
    public function mapSelectionToSource(
        string $text,
        string $before,
        string $after,
        MarkdownSelectionMatcher $matcher,
    ): void {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);
        $this->enterEditModeIfNeeded();

        $result = $matcher->find($this->editContent, $text, $before, $after);

        $this->dispatch(
            'highlight-source-range',
            start: $result->start,
            end: $result->end,
            confident: $result->confident,
        );
    }

    // -------------------------------------------------------------------------
    // Ask AI
    // -------------------------------------------------------------------------

    /**
     * Stream an AI suggestion into the AI panel response area.
     * Requires the AI_SUGGESTIONS_ENABLED flag and editor-or-above role.
     */
    public function submitAiPrompt(): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('askAi', $this->selectedVersion);

        if (blank($this->aiPrompt)) {
            return;
        }

        $content = $this->selectedVersion?->content ?? '';
        $prompt = "The following is the current lesson plan content:\n\n{$content}"
                 ."\n\n---\n\nUser's request: {$this->aiPrompt}";

        $this->aiResponse = '';
        $accumulated = '';

        set_time_limit(120);

        $stream = LessonPlanAdvisor::make()->stream($prompt);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $accumulated .= $event->delta;
                $this->stream($event->delta, false, 'aiResponse');
            }
        }

        // Persist the full text so it survives subsequent Livewire re-renders.
        $this->aiResponse = $accumulated;
    }
}
