<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminLessonsWidget extends TableWidget
{
    /** Active filter tab: all | official | latest | favorites */
    public string $activeTab = 'all';

    /**
     * Custom view renders the tab bar above the Filament table.
     *
     * @var view-string
     */
    protected string $view = 'filament.app.widgets.admin-lessons-widget';

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);
    }

    // -------------------------------------------------------------------------
    // Heading
    // -------------------------------------------------------------------------

    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    // -------------------------------------------------------------------------
    // Table definition
    // -------------------------------------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildQuery())
            ->queryStringIdentifier('admin-lessons')
            ->columns([
                TextColumn::make('family.subjectGrade.subject.name')
                    ->label('Subject')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('family.subjectGrade.grade')
                    ->label('Grade')
                    ->formatStateUsing(fn ($state) => 'Grade '.$state)
                    ->sortable(),
                TextColumn::make('family.day')
                    ->label('Day')
                    ->sortable(),
                TextColumn::make('version')
                    ->label('Version')
                    ->sortable(),
                TextColumn::make('official_indicator')
                    ->label('Official')
                    ->state(fn (LessonPlanVersion $record): string => ($record->family && (int) $record->family->official_version_id === $record->id)
                        ? '✓' : ''
                    )
                    ->color(fn (LessonPlanVersion $record): string => ($record->family && (int) $record->family->official_version_id === $record->id)
                        ? 'success' : 'gray'
                    ),
                TextColumn::make('contributor.name')
                    ->label('By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('subject')
                    ->form([
                        Select::make('subject_id')
                            ->label('Subject')
                            ->options(fn () => Subject::orderBy('name')->pluck('name', 'id'))
                            ->placeholder('All subjects'),
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data): Builder {
                        if (! filled($data['subject_id'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'family',
                            fn (Builder $q) => $q->whereHas(
                                'subjectGrade',
                                fn (Builder $q2) => $q2->where('subject_id', $data['subject_id'])
                            )
                        );
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['subject_id'] ?? null)
                        ? 'Subject: '.(Subject::find($data['subject_id'])?->name ?? $data['subject_id'])
                        : null
                    ),

                Filter::make('grade')
                    ->form([
                        Select::make('grade')
                            ->label('Grade')
                            ->options(fn () => SubjectGrade::query()
                                ->distinct()
                                ->orderBy('grade')
                                ->pluck('grade', 'grade')
                                ->mapWithKeys(fn ($g) => [$g => 'Grade '.$g])
                            )
                            ->placeholder('All grades'),
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data): Builder {
                        if (! filled($data['grade'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'family',
                            fn (Builder $q) => $q->whereHas(
                                'subjectGrade',
                                fn (Builder $q2) => $q2->where('grade', $data['grade'])
                            )
                        );
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['grade'] ?? null) ? 'Grade '.$data['grade'] : null),
            ])
            ->recordUrl(fn (LessonPlanVersion $record): string => LessonPlanFamilyResource::getUrl('view', ['record' => $record->lesson_plan_family_id]))
            ->toolbarActions([
                BulkAction::make('delete')
                    ->button()
                    ->label('Delete')
                    ->color('primary')
                    ->modalHeading('Delete selected items?')
                    ->modalDescription('This cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->modalSubmitAction(fn ($action) => $action->color('danger'))
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->deleteLessons($records))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Query builder
    // -------------------------------------------------------------------------

    private function buildQuery(): Builder
    {
        $query = LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor'])
            ->join('lesson_plan_families', 'lesson_plan_versions.lesson_plan_family_id', '=', 'lesson_plan_families.id')
            ->join('subject_grades', 'lesson_plan_families.subject_grade_id', '=', 'subject_grades.id')
            ->join('subjects', 'subject_grades.subject_id', '=', 'subjects.id')
            ->select('lesson_plan_versions.*');

        match ($this->activeTab) {
            'official' => $query->whereIn(
                'lesson_plan_versions.id',
                DB::table('lesson_plan_families')
                    ->whereNotNull('official_version_id')
                    ->pluck('official_version_id')
            ),
            'latest' => $query->whereIn(
                'lesson_plan_versions.id',
                DB::table('lesson_plan_versions')
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('lesson_plan_family_id')
            ),
            'favorites' => $query->whereHas(
                'favorites',
                fn ($q) => $q->where('user_id', auth()->id())
            ),
            default => null,
        };

        return $query;
    }

    // -------------------------------------------------------------------------
    // Tab change — reset pagination and deselect
    // -------------------------------------------------------------------------

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    // -------------------------------------------------------------------------
    // Bulk delete
    // -------------------------------------------------------------------------

    private function deleteLessons(Collection $records): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        DB::transaction(function () use ($records): void {
            foreach ($records as $version) {
                $family = $version->lesson_plan_family_id
                    ? LessonPlanFamily::find($version->lesson_plan_family_id)
                    : null;

                if ($family && (int) $family->official_version_id === $version->id) {
                    $family->official_version_id = null;
                    $family->save();
                }

                $version->delete();

                if ($family && $family->versions()->doesntExist()) {
                    $family->delete();
                }
            }
        });

        $count = $records->count();

        Notification::make('lessons-deleted')
            ->title('Deleted '.$count.' '.str('version')->plural($count).'.')
            ->success()
            ->send();
    }
}
