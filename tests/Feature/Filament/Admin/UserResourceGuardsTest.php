<?php

use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -------------------------------------------------------------------------
// Revoke Site Admin guards
// -------------------------------------------------------------------------

test('revoking site admin is blocked when the target is the last admin', function () {
    $onlyAdmin = makeSiteAdmin();
    $this->actingAs($onlyAdmin);

    Livewire::test(ListUsers::class)
        ->callTableAction('revokeSiteAdmin', $onlyAdmin)
        ->assertNotified();

    expect($onlyAdmin->fresh()->isSiteAdmin())->toBeTrue();
});

test('revoking site admin is blocked when the actor is the target (self-revoke)', function () {
    $admin = makeSiteAdmin();
    makeSiteAdmin(); // ensure it is not the last admin
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableAction('revokeSiteAdmin', $admin)
        ->assertNotified();

    expect($admin->fresh()->isSiteAdmin())->toBeTrue();
});

test('revoking site admin succeeds when another admin exists', function () {
    $admin = makeSiteAdmin();
    $target = makeSiteAdmin();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableAction('revokeSiteAdmin', $target)
        ->assertNotified();

    expect($target->fresh()->isSiteAdmin())->toBeFalse();
});

// -------------------------------------------------------------------------
// Delete user guards
// -------------------------------------------------------------------------

test('deleting a user is blocked when the actor is the target (self-delete)', function () {
    $admin = makeSiteAdmin();
    makeSiteAdmin(); // ensure there is another admin
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableAction('deleteUser', $admin)
        ->assertNotified();

    expect(User::where('id', $admin->id)->exists())->toBeTrue();
});

test('deleting a user is blocked when the target is the last admin', function () {
    $onlyAdmin = makeSiteAdmin();
    $this->actingAs($onlyAdmin);

    Livewire::test(ListUsers::class)
        ->callTableAction('deleteUser', $onlyAdmin)
        ->assertNotified();

    expect(User::where('id', $onlyAdmin->id)->exists())->toBeTrue();
});

test('User::isLastSiteAdmin returns true when no other non-system admins exist', function () {
    $admin = makeSiteAdmin();

    expect(User::isLastSiteAdmin($admin))->toBeTrue();
});

test('User::isLastSiteAdmin returns false when another admin exists', function () {
    $admin1 = makeSiteAdmin();
    $admin2 = makeSiteAdmin();

    expect(User::isLastSiteAdmin($admin1))->toBeFalse();
    expect(User::isLastSiteAdmin($admin2))->toBeFalse();
});

test('deleting a non-admin user succeeds', function () {
    $admin = makeSiteAdmin();
    $teacher = makeTeacher();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableAction('deleteUser', $teacher)
        ->assertNotified();

    expect(User::where('id', $teacher->id)->withTrashed()->exists())->toBeTrue();
    expect(User::where('id', $teacher->id)->exists())->toBeFalse();
});

test('user table shows scoped assignment summaries', function () {
    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $science = Subject::factory()->create(['name' => 'Science']);
    $mathGrade = SubjectGrade::factory()->create([
        'subject_id' => $math->id,
        'grade' => 10,
    ]);
    $scienceGrade = SubjectGrade::factory()->create([
        'subject_id' => $science->id,
        'grade' => 8,
    ]);
    $target = makeSubjectAdmin($mathGrade);
    $target->subjectGrades()->attach($scienceGrade->id, ['role' => 'editor']);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(ListUsers::class)
        ->assertSee('SA: Mathematics G10')
        ->assertSee('Ed: Science G8');
});
