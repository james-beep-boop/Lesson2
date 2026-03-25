<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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
     * Query versions instead of families so each table row is one LessonPlanVersion.
     * Columns, filters, and recordUrl live in LessonPlanFamilyResource::table() and
     * are applied normally through configureTable() — no makeTable() override needed.
     */
    protected function getTableQuery(): Builder
    {
        return LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor']);
    }

    /**
     * Tabs displayed to the left of the search bar.
     * All | Official | Latest (newest version per family) | Favorites
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
                    'favorites',
                    fn (Builder $fq) => $fq->where('user_id', auth()->id())
                )),
        ];
    }
}
