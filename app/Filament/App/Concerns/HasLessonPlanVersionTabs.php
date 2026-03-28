<?php

namespace App\Filament\App\Concerns;

use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared tab definitions for lesson-plan-version tables.
 * Used by both ListLessonPlanFamilies (resource page) and
 * LessonVersionsWidget (admin dashboard widget).
 */
trait HasLessonPlanVersionTabs
{
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
                    'favorites',
                    fn (Builder $fq) => $fq->where('user_id', auth()->id())
                )),
        ];
    }
}
