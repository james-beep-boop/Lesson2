<?php

use App\Filament\App\Pages\AdminDashboard;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

// ── Access control ────────────────────────────────────────────────────────────

test('non-admin is denied access to admin dashboard', function () {
    $this->actingAs(makeTeacher())
        ->get(AdminDashboard::getUrl())
        ->assertForbidden();
});

test('site admin can access admin dashboard', function () {
    $this->actingAs(makeSiteAdmin())
        ->get(AdminDashboard::getUrl())
        ->assertOk();
});

test('admin nav item is hidden from non-admins', function () {
    $this->actingAs(makeTeacher());
    expect(AdminDashboard::shouldRegisterNavigation())->toBeFalse();
});

test('admin nav item is visible to site admins', function () {
    $this->actingAs(makeSiteAdmin());
    expect(AdminDashboard::shouldRegisterNavigation())->toBeTrue();
});

// ── setOfficial ───────────────────────────────────────────────────────────────

test('setOfficial marks a version as official', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->call('setOfficial', $family->id, $version->id)
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBe($version->id);
});

test('setOfficial toggles off when the version is already official', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->call('setOfficial', $family->id, $version->id)
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBeNull();
});

test('setOfficial rejects a version that does not belong to the family', function () {
    [$family1] = makeFamilyWithVersion(makeSubjectGrade());
    [, $alienVersion] = makeFamilyWithVersion(makeSubjectGrade());

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->call('setOfficial', $family1->id, $alienVersion->id)
        ->assertStatus(422);
});

// ── deleteSelectedVersions ────────────────────────────────────────────────────

test('deleteSelectedVersions removes the selected version but keeps the family when others remain', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set('selectedVersionIds', [$v1->id])
        ->call('deleteSelectedVersions')
        ->assertNotified();

    expect(LessonPlanVersion::find($v1->id))->toBeNull();
    expect(LessonPlanFamily::find($family->id))->not->toBeNull();
});

test('deleteSelectedVersions deletes the family when its last version is removed', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set('selectedVersionIds', [$version->id])
        ->call('deleteSelectedVersions')
        ->assertNotified();

    expect(LessonPlanVersion::find($version->id))->toBeNull();
    expect(LessonPlanFamily::find($family->id))->toBeNull();
});

test('deleteSelectedVersions clears official_version_id before deleting', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    // If official_version_id were not cleared first the FK constraint would fail.
    Livewire::test(AdminDashboard::class)
        ->set('selectedVersionIds', [$version->id])
        ->call('deleteSelectedVersions')
        ->assertNotified();

    expect(LessonPlanFamily::find($family->id))->toBeNull();
});

// ── deleteSelectedUsers ───────────────────────────────────────────────────────

test('deleteSelectedUsers soft-deletes the target user', function () {
    $target = makeTeacher();

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set('selectedUserIds', [$target->id])
        ->call('deleteSelectedUsers')
        ->assertNotified();

    expect(User::find($target->id))->toBeNull();
});

test('deleteSelectedUsers refuses to delete own account', function () {
    $admin = makeSiteAdmin();

    $this->actingAs($admin);

    Livewire::test(AdminDashboard::class)
        ->set('selectedUserIds', [$admin->id])
        ->call('deleteSelectedUsers')
        ->assertNotified();

    expect(User::find($admin->id))->not->toBeNull();
});

// ── confirmStatusChanges ──────────────────────────────────────────────────────

test('confirmStatusChanges promotes a user to site administrator', function () {
    $target = makeTeacher();

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set("userStatusChanges.{$target->id}", 'administrator')
        ->call('confirmStatusChanges')
        ->assertNotified();

    expect($target->fresh()->isSiteAdmin())->toBeTrue();
});

test('confirmStatusChanges demotes a site administrator when another admin exists', function () {
    $admin1 = makeSiteAdmin();
    $admin2 = makeSiteAdmin();

    $this->actingAs($admin1);

    Livewire::test(AdminDashboard::class)
        ->set("userStatusChanges.{$admin2->id}", 'user')
        ->call('confirmStatusChanges')
        ->assertNotified();

    expect($admin2->fresh()->isSiteAdmin())->toBeFalse();
});

test('confirmStatusChanges refuses to remove the last site administrator', function () {
    $admin = makeSiteAdmin();

    $this->actingAs($admin);

    Livewire::test(AdminDashboard::class)
        ->set("userStatusChanges.{$admin->id}", 'user')
        ->call('confirmStatusChanges');

    expect($admin->fresh()->isSiteAdmin())->toBeTrue();
});
