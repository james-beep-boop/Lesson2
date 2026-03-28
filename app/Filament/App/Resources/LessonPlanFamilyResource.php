<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LessonPlanFamilyResource extends Resource
{
    protected static ?string $model = LessonPlanFamily::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Lessons';

    protected static ?string $label = 'Lesson Plan';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('subject_grade_id')
                ->label('Subject Grade')
                ->relationship('subjectGrade', 'id', fn ($query) => $query->with('subject'))
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->subject->name.' — Grade '.$record->grade)
                ->searchable()
                ->required(),
            TextInput::make('day')->required(),
        ]);
    }

    /**
     * Table definition — applies to the version-per-row query supplied by
     * ListLessonPlanFamilies::getTableQuery(). Each $record is a LessonPlanVersion
     * with its family, subjectGrade, and subject eager-loaded.
     */
    public static function table(Table $table): Table
    {
        return $table
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
                    ->indicateUsing(fn (array $data): ?string => filled($data['grade'] ?? null) ? 'Grade '.$data['grade'] : null
                    ),

            ])
            ->recordUrl(fn (LessonPlanVersion $record): string => static::getUrl('view', ['record' => $record->lesson_plan_family_id])
            )
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessonPlanFamilies::route('/'),
            'create' => Pages\CreateLessonPlanFamily::route('/create'),
            'view' => Pages\ViewLessonPlanFamily::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isSiteAdmin()
            || SubjectGrade::where('subject_admin_user_id', $user->id)->exists();
    }
}
