<?php

use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\SubjectAdminService;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('promote sets subject_admin_user_id', function () {
    $sg = makeSubjectGrade();
    $user = makeTeacher();
    $service = new SubjectAdminService;

    $service->promote($user, $sg);

    expect($sg->fresh()->subject_admin_user_id)->toBe($user->id);
});

test('promote demotes existing subject admin to editor', function () {
    $sg = makeSubjectGrade();
    $existingAdmin = makeSubjectAdmin($sg);
    $newAdmin = makeTeacher();
    $service = new SubjectAdminService;

    $service->promote($newAdmin, $sg);

    $sg->refresh();
    expect($sg->subject_admin_user_id)->toBe($newAdmin->id);

    // Existing admin should now be in the editor pivot
    expect(
        \DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $existingAdmin->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});

test('promote demotes user from their previous subject_grade subject admin role', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $user = makeTeacher();
    $service = new SubjectAdminService;

    // User is Subject Admin of sg1
    $service->promote($user, $sg1);
    expect($sg1->fresh()->subject_admin_user_id)->toBe($user->id);

    // Now promote them to sg2 — they should be demoted from sg1
    $service->promote($user, $sg2);

    expect($sg1->fresh()->subject_admin_user_id)->toBeNull();
    expect($sg2->fresh()->subject_admin_user_id)->toBe($user->id);

    // They should be editor in sg1 now
    expect(
        \DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg1->id)
            ->where('user_id', $user->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});

test('a user cannot be subject admin for more than one subject grade simultaneously', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $user = makeTeacher();
    $service = new SubjectAdminService;

    $service->promote($user, $sg1);
    $service->promote($user, $sg2);

    $adminCount = SubjectGrade::where('subject_admin_user_id', $user->id)->count();
    expect($adminCount)->toBe(1);
});

test('demoteToEditor sets subject_admin_user_id to null and adds editor pivot', function () {
    $sg = makeSubjectGrade();
    $user = makeSubjectAdmin($sg);
    $service = new SubjectAdminService;

    $service->demoteToEditor($user, $sg);

    expect($sg->fresh()->subject_admin_user_id)->toBeNull();
    expect(
        \DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $user->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});
