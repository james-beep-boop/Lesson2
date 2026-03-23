<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
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

        Notification::make()->title('New version saved.')->success()->send();
    }

    public function markOfficial(VersionService $versionService): void
    {
        $this->authorize('markOfficial', $this->selectedVersion);

        $versionService->setOfficialVersion($this->record, $this->selectedVersion);
        $this->record->refresh();
        $this->selectedVersion = $this->selectedVersion->fresh();

        Notification::make()->title('Official version updated.')->success()->send();
    }

    public function favorite(FavoriteService $favoriteService): void
    {
        abort_unless(auth()->check(), 403);

        $favoriteService->upsert(auth()->user(), $this->selectedVersion);

        Notification::make()->title('Added to favorites.')->success()->send();
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

    public function enterCompareMode(int $versionId): void
    {
        $other = $this->record->versions()->findOrFail($versionId);
        $this->compareVersion = $other;
        $this->compareMode = true;
    }
}
