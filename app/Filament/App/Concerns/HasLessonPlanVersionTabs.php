<?php

namespace App\Filament\App\Concerns;

use App\Models\User;
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
        /** @var User|null $user */
        $user = auth()->user();

        // "Mine" tab: visible only to Editors and Subject Admins, not plain Teachers or Site Admins.
        $adminGradeIds = ($user && ! $user->isSiteAdmin())
            ? $user->subjectGradesAsAdmin()->pluck('id')
            : collect();

        $showMineTab = $adminGradeIds->isNotEmpty()
            || ($user && ! $user->isSiteAdmin() && $user->subjectGrades()->wherePivot('role', 'editor')->exists());

        $subjectGradeIds = $showMineTab
            ? $user->subjectGrades()->pluck('subject_grades.id')
                ->merge($adminGradeIds)
                ->filter()
                ->unique()
            : collect();

        $tabs = [
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

        if ($showMineTab) {
            $tabs['mine'] = Tab::make('My Subject-Grades')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'family',
                    fn (Builder $fq) => $fq->whereIn('subject_grade_id', $subjectGradeIds)
                ));
        }

        return $tabs;
    }
}
