<?php

namespace App\Policies;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;

class LessonPlanVersionPolicy
{
    /** All authenticated non-system users may view any version. */
    public function view(User $user, LessonPlanVersion $version): bool
    {
        return ! $user->is_system;
    }

    /**
     * Adding a new version to an existing family:
     * Editor (own), Subject Admin (own), or Site Admin.
     *
     * Gate call: $this->authorize('create', [LessonPlanVersion::class, $family])
     */
    public function create(User $user, LessonPlanFamily $family): bool
    {
        $subjectGrade = $family->subjectGrade()->first();

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->canEditSubjectGrade($subjectGrade);
    }

    /** Mark a version official: Subject Admin (own) or Site Admin. */
    public function markOfficial(User $user, LessonPlanVersion $version): bool
    {
        $subjectGrade = $version->family()->first()?->subjectGrade()->first();

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($subjectGrade);
    }

    /** Request deletion: Subject Admin (own) or Site Admin. */
    public function requestDeletion(User $user, LessonPlanVersion $version): bool
    {
        $subjectGrade = $version->family()->first()?->subjectGrade()->first();

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($subjectGrade);
    }

    /** Hard delete: Site Admin only. */
    public function forceDelete(User $user, LessonPlanVersion $version): bool
    {
        return $user->isSiteAdmin();
    }

    /** Use "Ask AI" in editor: Editor, Subject Admin, or Site Admin. */
    public function askAi(User $user, LessonPlanVersion $version): bool
    {
        if (! config('features.ai_suggestions')) {
            return false;
        }

        $subjectGrade = $version->family()->first()?->subjectGrade()->first();

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->canEditSubjectGrade($subjectGrade);
    }

    /** Translate to Swahili preview: Editor, Subject Admin (own), or Site Admin + AI flag. */
    public function translate(User $user, LessonPlanVersion $version): bool
    {
        if (! config('features.ai_suggestions')) {
            return false;
        }

        $subjectGrade = $version->family()->first()?->subjectGrade()->first();

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->canEditSubjectGrade($subjectGrade);
    }
}
