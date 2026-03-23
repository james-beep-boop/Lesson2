<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListLessonPlanFamilies extends ListRecords
{
    protected static string $resource = LessonPlanFamilyResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if ($user->isSiteAdmin() || SubjectGrade::where('subject_admin_user_id', $user->id)->exists()) {
            return [CreateAction::make()->label('Add Lesson Plan')];
        }

        return [];
    }

    /**
     * Override to query lesson_plan_versions instead of lesson_plan_families.
     * Each row represents one version so the table shows the full version history.
     */
    protected function getTableQuery(): Builder
    {
        return LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor']);
    }

    /**
     * Define version-based columns and record URL, replacing the resource's
     * family-based table definition entirely.
     */
    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->columns([
                TextColumn::make('family.subjectGrade.subject.name')
                    ->label('Subject')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('family.subjectGrade.grade')
                    ->label('Grade')
                    ->formatStateUsing(fn ($state) => 'Grade ' . $state)
                    ->sortable(),
                TextColumn::make('family.day')
                    ->label('Day')
                    ->sortable(),
                TextColumn::make('family.language')
                    ->label('Language')
                    ->formatStateUsing(fn ($state) => $state === 'en' ? 'English' : 'Swahili'),
                TextColumn::make('version')
                    ->label('Version')
                    ->sortable(),
                TextColumn::make('official_badge')
                    ->label('Official')
                    ->state(fn (LessonPlanVersion $record): string =>
                        ($record->family && (int) $record->family->official_version_id === $record->id) ? '✓' : ''
                    )
                    ->color(fn (LessonPlanVersion $record): string =>
                        ($record->family && (int) $record->family->official_version_id === $record->id) ? 'success' : 'gray'
                    ),
                TextColumn::make('contributor.name')
                    ->label('By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->recordUrl(fn (LessonPlanVersion $record): string =>
                LessonPlanFamilyResource::getUrl('view', ['record' => $record->lesson_plan_family_id])
            )
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Filter tabs shown to the left of the search bar.
     * All  — every version in the system
     * Official — versions designated as the official version of their family
     * Latest   — the most recently added version per family
     * Favorites — versions the current user has favorited
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'official' => Tab::make('Official')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                    'lesson_plan_versions.id',
                    DB::table('lesson_plan_families')
                        ->whereNotNull('official_version_id')
                        ->pluck('official_version_id')
                )),

            'latest' => Tab::make('Latest')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                    'lesson_plan_versions.id',
                    DB::table('lesson_plan_versions')
                        ->selectRaw('MAX(id) as id')
                        ->groupBy('lesson_plan_family_id')
                )),

            'favorites' => Tab::make('Favorites')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'favorites', fn (Builder $fq) => $fq->where('user_id', auth()->id())
                )),
        ];
    }
}
