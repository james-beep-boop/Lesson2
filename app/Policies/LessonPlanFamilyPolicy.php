<?php

namespace App\Policies;

use App\Models\LessonPlanFamily;
use App\Models\SubjectGrade;
use App\Models\User;

class LessonPlanFamilyPolicy
{
    /** All authenticated non-system users may view any lesson plan. */
    public function view(User $user, LessonPlanFamily $family): bool
    {
        return ! $user->is_system;
    }

    /** Creating a new family requires Subject Admin (own subject_grade) or Site Admin. */
    public function create(User $user): bool
    {
        return $user->isSiteAdmin()
            || SubjectGrade::where('subject_admin_user_id', $user->id)->exists();
    }

    /** Subject Admin (own) or Site Admin may request deletion. */
    public function requestDeletion(User $user, LessonPlanFamily $family): bool
    {
        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($family->subjectGrade);
    }

    /** Only Site Admin may hard-delete. */
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
