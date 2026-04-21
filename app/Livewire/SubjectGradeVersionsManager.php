<?php

namespace App\Livewire;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Services\VersionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SubjectGradeVersionsManager extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $subjectGradeId;

    private ?SubjectGrade $cachedSubjectGrade = null;

    public function mount(int $subjectGradeId): void
    {
        $subjectGrade = SubjectGrade::query()
            ->with(['subject'])
            ->findOrFail($subjectGradeId);

        abort_unless(auth()->user()?->isSubjectAdminFor($subjectGrade), 403);

        $this->subjectGradeId = $subjectGradeId;
        $this->cachedSubjectGrade = $subjectGrade;
    }

    public function getSubjectGrade(): SubjectGrade
    {
        return $this->cachedSubjectGrade ??= SubjectGrade::query()
            ->with(['subject'])
            ->findOrFail($this->subjectGradeId);
    }

    public function table(Table $table): Table
    {
        $subjectGrade = $this->getSubjectGrade();

        return $table
            ->query($this->getVersionsQuery())
            ->columns([
                TextColumn::make('family.day')
                    ->label('Day')
                    ->sortable(),
                TextColumn::make('version')
                    ->label('Version')
                    ->sortable(),
                TextColumn::make('contributor.name')
                    ->label('By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('toggleOfficial')
                    ->label(fn (LessonPlanVersion $record): string => $record->isOfficial()
                        ? '✓ Official'
                        : 'Set Official'
                    )
                    ->color(fn (LessonPlanVersion $record): string => $record->isOfficial()
                        ? 'success'
                        : 'gray'
                    )
                    ->tooltip(fn (LessonPlanVersion $record): string => $record->isOfficial()
                        ? 'This version is currently the official one for this plan'
                        : 'Mark this version as the official one for this plan'
                    )
                    ->button()
                    ->size('xs')
                    ->action(function (LessonPlanVersion $record): void {
                        abort_unless(auth()->user()?->isSubjectAdminFor($this->getSubjectGrade()), 403);

                        $family = $record->lesson_plan_family_id
                            ? LessonPlanFamily::find($record->lesson_plan_family_id)
                            : null;

                        if (! $family) {
                            return;
                        }

                        if ((int) $family->official_version_id === $record->id) {
                            Notification::make('official-unchanged')
                                ->title('This version is already official.')
                                ->info()
                                ->send();

                            $this->resetTable();

                            return;
                        }

                        app(VersionService::class)->setOfficialVersion($family, $record);

                        Notification::make('official-updated')
                            ->title('Official version set.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ], RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkAction::make('deleteVersions')
                    ->button()
                    ->label('Delete')
                    ->color('danger')
                    ->modalHeading('Delete selected versions?')
                    ->modalDescription('This cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->modalSubmitAction(fn ($action) => $action->color('danger'))
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->deleteVersions($records))
                    ->deselectRecordsAfterCompletion()
                    ->extraAttributes(['x-show' => '1']),
            ])
            ->checkIfRecordIsSelectableUsing(fn (LessonPlanVersion $record): bool => ! $record->isOfficial())
            ->recordUrl(fn (LessonPlanVersion $record): string => LessonPlanFamilyResource::versionUrl($record))
            ->heading($subjectGrade->subject->name.', Grade '.$subjectGrade->grade.' — Lesson Plan Versions')
            ->emptyStateHeading('No lesson plan versions yet.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->paginated(false)
            ->queryStringIdentifier("subject-grade-versions-{$this->subjectGradeId}");
    }

    private function deleteVersions(Collection $records): void
    {
        $subjectGrade = $this->getSubjectGrade();

        abort_unless(auth()->user()?->isSubjectAdminFor($subjectGrade), 403);

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($records, &$deleted, &$skipped): void {
            foreach ($records as $version) {
                $family = $version->lesson_plan_family_id
                    ? LessonPlanFamily::find($version->lesson_plan_family_id)
                    : null;

                // Skip official versions.
                if ($family && (int) $family->official_version_id === $version->id) {
                    $skipped++;

                    continue;
                }

                $version->delete();
                $deleted++;

                // Remove orphaned family when its last version was just deleted.
                if ($family && $family->versions()->doesntExist()) {
                    $family->delete();
                }
            }
        });

        if ($skipped > 0) {
            Notification::make('versions-skipped')
                ->title($skipped.' official '.str('version')->plural($skipped).' skipped')
                ->body('Official versions cannot be deleted. Remove official status first.')
                ->warning()
                ->send();
        }

        if ($deleted > 0) {
            Notification::make('versions-deleted')
                ->title('Deleted '.$deleted.' '.str('version')->plural($deleted).'.')
                ->success()
                ->send();

            $this->resetTable();
        }
    }

    private function getVersionsQuery(): Builder
    {
        return LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor'])
            ->join('lesson_plan_families', 'lesson_plan_versions.lesson_plan_family_id', '=', 'lesson_plan_families.id')
            ->where('lesson_plan_families.subject_grade_id', $this->subjectGradeId)
            ->select('lesson_plan_versions.*')
            ->orderBy('lesson_plan_families.day')
            ->orderBy('lesson_plan_versions.created_at', 'desc');
    }

    public function render(): View
    {
        return view('livewire.subject-grade-versions-manager');
    }
}
