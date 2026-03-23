<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\DeletionRequestService;
use App\Services\FavoriteService;
use App\Services\VersionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

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

    public bool $showDeletionForm = false;
    public string $deletionReason = '';

    // AI panel state
    public bool $aiPanelOpen = false;
    public string $aiPrompt = '';
    public string $aiResponse = '';

    public function mount(LessonPlanFamily $record): void
    {
        $this->record = $record->load(['versions', 'officialVersion', 'latestVersion', 'subjectGrade.subject']);
        $this->selectedVersion = $record->officialVersion ?? $record->latestVersion;
    }

    public function selectVersion(int $versionId): void
    {
        $this->selectedVersion = $this->record->versions()->findOrFail($versionId);
        $this->compareMode = false;
        $this->compareVersion = null;
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

        $favoriteService->upsert(auth()->user(), $this->selectedVersion);

        Notification::make('favorited')->title('Added to favorites.')->success()->send();
    }

    /**
     * Compute a line-by-line diff between the selected version and the compare version.
     * Returns an array of ['type' => 'equal'|'deleted'|'added', 'left' => string, 'right' => string].
     * 'deleted' lines appear on the left (old) only; 'added' lines appear on the right (new) only.
     */
    public function computeDiff(): array
    {
        if (! $this->selectedVersion || ! $this->compareVersion) {
            return [];
        }

        return $this->diffLines($this->selectedVersion->content, $this->compareVersion->content);
    }

    private function diffLines(string $old, string $new): array
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);
        $m = count($oldLines);
        $n = count($newLines);

        // LCS DP table
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $oldLines[$i - 1] === $newLines[$j - 1]
                    ? $dp[$i - 1][$j - 1] + 1
                    : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }

        // Backtrack
        $ops = [];
        $i = $m;
        $j = $n;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $oldLines[$i - 1] === $newLines[$j - 1]) {
                array_unshift($ops, ['type' => 'equal', 'left' => $oldLines[$i - 1], 'right' => $newLines[$j - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
                array_unshift($ops, ['type' => 'added', 'left' => '', 'right' => $newLines[$j - 1]]);
                $j--;
            } else {
                array_unshift($ops, ['type' => 'deleted', 'left' => $oldLines[$i - 1], 'right' => '']);
                $i--;
            }
        }

        return $ops;
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
        $sg   = $this->record->subjectGrade;

        if (! $user->isSubjectAdminFor($sg)) {
            Notification::make('deletion-unauthorized')
                ->title('Not authorized.')
                ->danger()
                ->send();
            return;
        }

        // Prevent duplicate pending requests for the same version.
        if ($this->selectedVersion->deletionRequests()->whereNull('resolved_at')->exists()) {
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
        $this->deletionReason   = '';

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
}
