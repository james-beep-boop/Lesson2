<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Ai\Agents\LessonPlanAdvisor;
use App\Ai\Agents\LessonPlanTranslator;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\DeletionRequestService;
use App\Services\FavoriteService;
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

    // Translation state: idle | streaming | review | conflict
    public string $translationState = 'idle';

    public string $translateContent = '';

    public string $translationBump = 'patch';

    public function mount(LessonPlanFamily $record): void
    {
        $this->record = $record->load(['versions', 'officialVersion', 'latestVersion', 'subjectGrade.subject']);
        $this->selectedVersion = $record->officialVersion ?? $record->latestVersion;
        $this->syncDerivedState();
    }

    private function syncDerivedState(): void
    {
        $user = auth()->user();

        $this->userFavorite = $user
            ? Favorite::where('user_id', $user->id)
                ->where('lesson_plan_family_id', $this->record->id)
                ->first()
            : null;

        $this->hasPendingDeletion = (bool) ($this->selectedVersion?->deletionRequests()
            ->whereNull('resolved_at')
            ->exists());
    }

    public function selectVersion(int $versionId): void
    {
        $this->selectedVersion = $this->record->versions()->findOrFail($versionId);
        $this->compareMode = false;
        $this->compareVersion = null;
        $this->hasPendingDeletion = (bool) $this->selectedVersion->deletionRequests()
            ->whereNull('resolved_at')
            ->exists();
    }

    public function enterEditMode(): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);

        $this->editContent = $this->selectedVersion?->content ?? '';
        $this->editMode = true;
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
        $svc = app(VersionService::class);

        return [
            'major' => $svc->computeNextVersion($this->record, 'major'),
            'minor' => $svc->computeNextVersion($this->record, 'minor'),
            'patch' => $svc->computeNextVersion($this->record, 'patch'),
        ];
    }

    public function requestDeletion(DeletionRequestService $service): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $user = auth()->user();
        $sg = $this->record->subjectGrade;

        if (! $user->isSubjectAdminFor($sg) && ! $user->isSiteAdmin()) {
            Notification::make('deletion-unauthorized')
                ->title('Not authorized.')
                ->danger()
                ->send();

            return;
        }

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
        $other = $this->record->versions()->findOrFail($versionId);
        $this->compareVersion = $other;
        $this->compareMode = true;
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
        if (! config('features.ai_suggestions')) {
            return;
        }

        $user = auth()->user();

        if (! $user->canEditSubjectGrade($this->record->subjectGrade)) {
            return;
        }

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

    // -------------------------------------------------------------------------
    // Translate to Swahili
    // -------------------------------------------------------------------------

    /**
     * Begin the translation flow: stream the Swahili translation into the
     * review panel. On completion, transitions to the 'review' state where
     * the user can edit before saving.
     */
    public function startTranslation(): void
    {
        if (! config('features.ai_suggestions')) {
            return;
        }

        $user = auth()->user();

        if (! ($user->isSiteAdmin() || $user->isSubjectAdminFor($this->record->subjectGrade))) {
            return;
        }

        if (! $this->selectedVersion) {
            return;
        }

        $this->translateContent = '';
        $this->translationState = 'streaming';
        $accumulated = '';

        set_time_limit(120);

        $stream = LessonPlanTranslator::make()->stream($this->selectedVersion->content);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $accumulated .= $event->delta;
                $this->stream($event->delta, false, 'translatePreview');
            }
        }

        $this->translateContent = $accumulated;
        $this->translationState = 'review';
    }

    /**
     * Confirm and save the translation. If a version-number conflict exists
     * and no bump has been chosen yet, transitions to the 'conflict' state
     * so the user can pick a bump type before confirming again.
     */
    public function saveTranslation(VersionService $versionService): void
    {
        if (! config('features.ai_suggestions')) {
            return;
        }

        $user = auth()->user();

        if (! ($user->isSiteAdmin() || $user->isSubjectAdminFor($this->record->subjectGrade))) {
            return;
        }

        // First save attempt: detect version conflict and ask user to choose a bump.
        if ($this->translationState !== 'conflict') {
            $conflict = $versionService->translationHasVersionConflict(
                $this->record,
                $this->selectedVersion->version
            );

            if ($conflict) {
                $this->translationState = 'conflict';

                return;
            }
        }

        $version = $versionService->createTranslatedVersion(
            $this->record,
            $this->selectedVersion,
            $this->translateContent,
            $user,
            $this->translationBump,
        );

        Notification::make('translation-saved')
            ->title('Swahili translation saved.')
            ->success()
            ->send();

        $this->redirect(
            LessonPlanFamilyResource::getUrl('view', ['record' => $version->lesson_plan_family_id])
        );
    }

    /** Reset all translation state and return to the idle button. */
    public function cancelTranslation(): void
    {
        $this->translationState = 'idle';
        $this->translateContent = '';
        $this->translationBump = 'patch';
    }
}
