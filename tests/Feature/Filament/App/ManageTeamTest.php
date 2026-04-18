<?php

use App\Filament\App\Pages\ManageTeam;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('manage team page loads for subject admin', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->assertOk();
});

test('page heading includes subject name and grade', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->assertSee("Manage Team: {$sg->subject->name}, Grade {$sg->grade}");
});

test('editors table renders column headers, bulk-action checkbox, and editor data', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->assertSeeHtml('type="checkbox"')
        ->assertSee('Full name')
        ->assertSee('Username')
        ->assertSee('Email')
        ->assertSee($editor->name)
        ->assertSee($editor->username)
        ->assertSee($editor->email);
});

test('teacher cannot access the manage team page', function () {
    $teacher = makeTeacher();

    $this->actingAs($teacher)
        ->get(ManageTeam::getUrl())
        ->assertForbidden();
});

test('add editor assigns editor role in the pivot', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $newEditor = makeTeacher();

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->set('addUserId', $newEditor->id)
        ->call('addEditor')
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $newEditor->id)
            ->where('role', 'editor')
            ->exists()
    )->toBeTrue();
});

test('remove editor detaches the user from the pivot', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->call('removeEditor', $editor->id)
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $editor->id)
            ->exists()
    )->toBeFalse();
});

test('bulk remove action detaches editors from the pivot', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $editor = makeEditor($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->callTableBulkAction('remove', [$editor])
        ->assertNotified();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg->id)
            ->where('user_id', $editor->id)
            ->exists()
    )->toBeFalse();
});

test('add editor validates that user id is required', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->set('addUserId', null)
        ->call('addEditor')
        ->assertHasErrors(['addUserId' => 'required']);
});

test('subject admin cannot add editor to another subject grade', function () {
    $sg1 = makeSubjectGrade();
    $sg2 = makeSubjectGrade();
    $adminOfSg1 = makeSubjectAdmin($sg1);
    $userToAdd = makeTeacher();

    // Admin of sg1 calls addEditor — their getSubjectGrade() returns sg1, not sg2
    $this->actingAs($adminOfSg1);

    Livewire::test(ManageTeam::class)
        ->set('addUserId', $userToAdd->id)
        ->call('addEditor');

    // User should be added to sg1 only
    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg1->id)
            ->where('user_id', $userToAdd->id)
            ->exists()
    )->toBeTrue();

    expect(
        DB::table('subject_grade_user')
            ->where('subject_grade_id', $sg2->id)
            ->where('user_id', $userToAdd->id)
            ->exists()
    )->toBeFalse();
});

test('manage team does not list unverified users as available editors', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $verifiedUser = makeTeacher();
    $unverifiedUser = User::factory()->unverified()->create();

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->assertSee($verifiedUser->name)
        ->assertDontSee($unverifiedUser->name);
});

test('manage team rejects adding an unverified editor', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $unverifiedUser = User::factory()->unverified()->create();

    $this->actingAs($subjectAdmin);

    Livewire::test(ManageTeam::class)
        ->set('addUserId', $unverifiedUser->id)
        ->call('addEditor')
        ->assertHasErrors(['addUserId']);
});
