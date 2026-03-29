<?php

namespace App\Filament\App\Widgets\Concerns;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared query and delete logic used by every version-table widget.
 *
 * Using classes must extend Filament\Widgets\TableWidget so that
 * $this->resetTable() and abort_unless() are available.
 * Tab filtering is handled by the widget's getTabs() via HasTabs.
 */
trait HasVersionTable
{
    // -------------------------------------------------------------------------
    // Base query (tab filtering applied via HasTabs::modifyQueryWithActiveTab)
    // -------------------------------------------------------------------------

    protected function buildVersionQuery(): Builder
    {
        return LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor'])
            ->join('lesson_plan_families', 'lesson_plan_versions.lesson_plan_family_id', '=', 'lesson_plan_families.id')
            ->join('subject_grades', 'lesson_plan_families.subject_grade_id', '=', 'subject_grades.id')
            ->join('subjects', 'subject_grades.subject_id', '=', 'subjects.id')
            ->select('lesson_plan_versions.*');
    }

    // -------------------------------------------------------------------------
    // Shared delete transaction
    // -------------------------------------------------------------------------

    /**
     * Delete a collection of LessonPlanVersions inside a transaction.
     *
     * Clears official_version_id before deletion to avoid FK constraint
     * violations, then removes orphaned families.
     *
     * @param  string  $notificationId  Unique notification ID to prevent toast deduplication.
     */
    protected function performVersionDelete(Collection $records, string $notificationId): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($records, &$deleted, &$skipped): void {
            foreach ($records as $version) {
                // Fresh DB load — avoid acting on a stale official_version_id.
                $family = $version->lesson_plan_family_id
                    ? LessonPlanFamily::find($version->lesson_plan_family_id)
                    : null;

                // Official versions are protected; skip silently and tally.
                // The UI already hides the checkbox, but enforce server-side too
                // in case the request was crafted directly.
                if ($family && (int) $family->official_version_id === $version->id) {
                    $skipped++;

                    continue;
                }

                // Favorites cascade via FK; deletion_requests nullOnDelete.
                $version->delete();
                $deleted++;

                // Remove orphaned family when its last version was just deleted.
                if ($family && $family->versions()->doesntExist()) {
                    $family->delete();
                }
            }
        });

        if ($skipped > 0) {
            Notification::make($notificationId.'-skipped')
                ->title($skipped.' official '.str('version')->plural($skipped).' skipped')
                ->body('Official versions cannot be deleted. Remove official status first.')
                ->warning()
                ->send();
        }

        if ($deleted > 0) {
            Notification::make($notificationId)
                ->title('Deleted '.$deleted.' '.str('version')->plural($deleted).'.')
                ->success()
                ->send();
        }
    }
}
