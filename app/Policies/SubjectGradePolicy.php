<?php

namespace App\Policies;

use App\Models\SubjectGrade;
use App\Models\User;

class SubjectGradePolicy
{
    public function view(User $user, SubjectGrade $subjectGrade): bool
    {
        return ! $user->is_system;
    }

    public function create(User $user): bool
    {
        return $user->isSiteAdmin();
    }

    public function update(User $user, SubjectGrade $subjectGrade): bool
    {
        return $user->isSiteAdmin();
    }

    public function delete(User $user, SubjectGrade $subjectGrade): bool
    {
        return $user->isSiteAdmin();
    }

    /** Assign / change roles within a subject_grade. */
    public function manageRoles(User $user, SubjectGrade $subjectGrade): bool
    {
        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($subjectGrade);
    }
}
