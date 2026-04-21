<?php

namespace App\Policies;

use App\Models\LessonPlanFamily;
use App\Models\User;

class LessonPlanFamilyPolicy
{
    /** All authenticated non-system users may view any lesson plan. */
    public function view(User $user, LessonPlanFamily $family): bool
    {
        return ! $user->is_system;
    }

    /** Only Site Admins may create a new lesson plan family. */
    public function create(User $user): bool
    {
        return $user->isSiteAdmin();
    }

    /**
     * There is no user-facing "delete family" action.
     * Families are deleted automatically when their last version is removed.
     * These methods exist for completeness but are not called by any controller or page.
     */
    public function requestDeletion(User $user, LessonPlanFamily $family): bool
    {
        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($family->subjectGrade);
    }

    public function forceDelete(User $user, LessonPlanFamily $family): bool
    {
        return $user->isSiteAdmin();
    }

    /** Translate: Subject Admin (own) or Site Admin, and AI flag must be on. */
    public function translate(User $user, LessonPlanFamily $family): bool
    {
        if (! config('features.ai_suggestions')) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($family->subjectGrade);
    }
}
