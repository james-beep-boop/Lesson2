<?php

use App\Models\SubjectGrade;
use App\Services\SubjectAdminService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
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
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $existingAdmin->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});

test('promote allows a user to remain subject admin for multiple subject grades', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $user = makeTeacher();
    $service = new SubjectAdminService;

    $service->promote($user, $sg1);
    $service->promote($user, $sg2);

    expect($sg1->fresh()->subject_admin_user_id)->toBe($user->id);
    expect($sg2->fresh()->subject_admin_user_id)->toBe($user->id);
});

test('a user can be subject admin for more than one subject grade simultaneously', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $user = makeTeacher();
    $service = new SubjectAdminService;

    $service->promote($user, $sg1);
    $service->promote($user, $sg2);

    $adminCount = SubjectGrade::where('subject_admin_user_id', $user->id)->count();
    expect($adminCount)->toBe(2);
});

test('demoteToEditor sets subject_admin_user_id to null and adds editor pivot', function () {
    $sg = makeSubjectGrade();
    $user = makeSubjectAdmin($sg);
    $service = new SubjectAdminService;

    $service->demoteToEditor($user, $sg);

    expect($sg->fresh()->subject_admin_user_id)->toBeNull();
    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $user->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});
