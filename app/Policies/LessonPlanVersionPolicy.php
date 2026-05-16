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
        $subjectGrade = $family->subjectGrade;

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->canEditSubjectGrade($subjectGrade);
    }

    /** Mark a version official: Subject Admin (own) or Site Admin. */
    public function markOfficial(User $user, LessonPlanVersion $version): bool
    {
        $subjectGrade = $version->family?->subjectGrade;

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($subjectGrade);
    }

    /**
     * Request deletion: Editor (own subject_grade), Subject Admin (own), or Site Admin.
     * Editors may request deletion of any version in their subject_grade (even not their own).
     */
    public function requestDeletion(User $user, LessonPlanVersion $version): bool
    {
        $subjectGrade = $version->family?->subjectGrade;

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->isSubjectAdminFor($subjectGrade)
            || $user->isEditorFor($subjectGrade);
    }

    /**
     * Direct delete (no request): Editor who authored the version in own subject_grade,
     * Subject Admin for any non-official version in own subject_grade, or Site Admin.
     * Official versions are protected from direct deletion by Editors and Subject Admins.
     */
    public function directDelete(User $user, LessonPlanVersion $version): bool
    {
        $family = $version->family;
        $subjectGrade = $family?->subjectGrade;

        if (! $subjectGrade) {
            return false;
        }

        if ($user->isSiteAdmin()) {
            return true;
        }

        $isOfficial = $family && (int) $family->official_version_id === $version->id;

        if ($isOfficial) {
            return false;
        }

        if ($user->isSubjectAdminFor($subjectGrade)) {
            return true;
        }

        if ($user->isEditorFor($subjectGrade)) {
            return $version->contributor_id === $user->id;
        }

        return false;
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

        $subjectGrade = $version->family?->subjectGrade;

        if (! $subjectGrade) {
            return false;
        }

        return $user->isSiteAdmin()
            || $user->canEditSubjectGrade($subjectGrade);
    }

    /** Translate to Swahili preview: any authenticated non-system user + AI flag. */
    public function translate(User $user, LessonPlanVersion $version): bool
    {
        if (! config('features.ai_suggestions')) {
            return false;
        }

        return ! $user->is_system;
    }
}
