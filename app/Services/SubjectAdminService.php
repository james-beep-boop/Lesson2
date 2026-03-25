<?php

namespace App\Services;

use App\Models\SubjectGrade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubjectAdminService
{
    /**
     * Promote $user to Subject Admin for $subjectGrade.
     *
     * Rules (all in one transaction):
     * 1. If $user is already Subject Admin of another subject_grade → demote them to Editor there.
     * 2. If $subjectGrade already has a Subject Admin → demote that admin to Editor for this subject_grade.
     * 3. Set subject_grades.subject_admin_user_id = $user->id on $subjectGrade.
     */
    public function promote(User $user, SubjectGrade $subjectGrade): void
    {
        DB::transaction(function () use ($user, $subjectGrade) {
            // 1. If user is already Subject Admin of a different subject_grade, demote them there.
            $previousSubjectGrade = SubjectGrade::where('subject_admin_user_id', $user->id)
                ->where('id', '!=', $subjectGrade->id)
                ->first();

            if ($previousSubjectGrade) {
                $previousSubjectGrade->subject_admin_user_id = null;
                $previousSubjectGrade->save();
                $this->upsertEditorRole($user, $previousSubjectGrade);
            }

            // 2. If subject_grade already has a Subject Admin, demote them to Editor.
            if ($subjectGrade->subject_admin_user_id && $subjectGrade->subject_admin_user_id !== $user->id) {
                $existingAdmin = User::find($subjectGrade->subject_admin_user_id);
                if ($existingAdmin) {
                    $this->upsertEditorRole($existingAdmin, $subjectGrade);
                }
            }

            // 3. Promote the user.
            $subjectGrade->subject_admin_user_id = $user->id;
            $subjectGrade->save();

            // Remove them from the editor pivot if present (they are now Subject Admin, not Editor).
            $subjectGrade->users()->detach($user->id);
        });
    }

    /**
     * Demote $user from Subject Admin of $subjectGrade to Editor.
     * Does nothing if they are not currently the Subject Admin.
     */
    public function demoteToEditor(User $user, SubjectGrade $subjectGrade): void
    {
        DB::transaction(function () use ($user, $subjectGrade) {
            if ((int) $subjectGrade->subject_admin_user_id !== $user->id) {
                return;
            }

            $subjectGrade->subject_admin_user_id = null;
            $subjectGrade->save();
            $this->upsertEditorRole($user, $subjectGrade);
        });
    }

    /**
     * Assign Editor role to a user for a subject_grade.
     * Uses updateOrInsert to avoid duplicate pivot rows.
     */
    public function assignEditor(User $user, SubjectGrade $subjectGrade): void
    {
        DB::transaction(function () use ($user, $subjectGrade) {
            $this->upsertEditorRole($user, $subjectGrade);
        });
    }

    /**
     * Remove a user from a subject_grade entirely.
     */
    public function removeUser(User $user, SubjectGrade $subjectGrade): void
    {
        DB::transaction(function () use ($user, $subjectGrade) {
            if ((int) $subjectGrade->subject_admin_user_id === $user->id) {
                $subjectGrade->subject_admin_user_id = null;
                $subjectGrade->save();
            }

            $subjectGrade->users()->detach($user->id);
        });
    }

    private function upsertEditorRole(User $user, SubjectGrade $subjectGrade): void
    {
        DB::table('subject_grade_user')->updateOrInsert(
            ['subject_grade_id' => $subjectGrade->id, 'user_id' => $user->id],
            ['role' => 'editor', 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
