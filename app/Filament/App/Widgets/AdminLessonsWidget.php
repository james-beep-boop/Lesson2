<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Filament\App\Widgets\Concerns\HasVersionTable;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AdminLessonsWidget extends TableWidget
{
    use HasVersionTable;

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
            ->query(fn (): Builder => $this->buildVersionQuery())
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
    // Bulk delete — delegates to shared trait implementation
    // -------------------------------------------------------------------------

    private function deleteLessons(Collection $records): void
    {
        $this->performVersionDelete($records, 'lessons-deleted');
    }
}
