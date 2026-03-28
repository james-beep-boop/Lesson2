<?php

use App\Filament\App\Pages\AdminDashboard;
use App\Filament\App\Widgets\AdminLessonsWidget;
use App\Filament\App\Widgets\LessonVersionsWidget;
use App\Filament\App\Widgets\UsersWidget;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

// ── AdminDashboard access control ─────────────────────────────────────────────

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

// ── Widget mount auth guards ───────────────────────────────────────────────────

test('non-admin cannot mount LessonVersionsWidget', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(LessonVersionsWidget::class)
        ->assertForbidden();
});

test('non-admin cannot mount UsersWidget', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(UsersWidget::class)
        ->assertForbidden();
});

test('non-admin cannot mount AdminLessonsWidget', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(AdminLessonsWidget::class)
        ->assertForbidden();
});

// ── LessonVersionsWidget – toggleOfficial ─────────────────────────────────────

test('toggleOfficial marks a version as official', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->callTableAction('toggleOfficial', $version)
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBe($version->id);
});

test('toggleOfficial toggles off when the version is already official', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->callTableAction('toggleOfficial', $version)
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBeNull();
});

// ── LessonVersionsWidget – bulk delete ───────────────────────────────────────

test('bulk delete removes a version but keeps the family when others remain', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->callTableBulkAction('delete', [$v1])
        ->assertNotified();

    expect(LessonPlanVersion::find($v1->id))->toBeNull();
    expect(LessonPlanFamily::find($family->id))->not->toBeNull();
});

test('bulk delete removes the family when its last version is deleted', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->callTableBulkAction('delete', [$version])
        ->assertNotified();

    expect(LessonPlanVersion::find($version->id))->toBeNull();
    expect(LessonPlanFamily::find($family->id))->toBeNull();
});

test('bulk delete clears official_version_id before deleting the official version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    // Would fail with FK constraint if official_version_id were not cleared first.
    Livewire::test(LessonVersionsWidget::class)
        ->callTableBulkAction('delete', [$version])
        ->assertNotified();

    expect(LessonPlanFamily::find($family->id))->toBeNull();
});

// ── AdminLessonsWidget – bulk delete ─────────────────────────────────────────

test('admin-lessons bulk delete removes a version but keeps the family when others remain', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminLessonsWidget::class)
        ->callTableBulkAction('delete', [$v1])
        ->assertNotified();

    expect(LessonPlanVersion::find($v1->id))->toBeNull();
    expect(LessonPlanFamily::find($family->id))->not->toBeNull();
});

test('admin-lessons bulk delete clears official_version_id before deleting the official version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    // Would fail with FK constraint if official_version_id were not cleared first.
    Livewire::test(AdminLessonsWidget::class)
        ->callTableBulkAction('delete', [$version])
        ->assertNotified();

    expect(LessonPlanFamily::find($family->id))->toBeNull();
});

// ── UsersWidget – bulk delete ─────────────────────────────────────────────────

test('bulk delete removes the target user', function () {
    $target = makeTeacher();

    $this->actingAs(makeSiteAdmin());

    Livewire::test(UsersWidget::class)
        ->callTableBulkAction('delete', [$target])
        ->assertNotified();

    expect(User::find($target->id))->toBeNull();
});

test('bulk delete refuses to delete own account', function () {
    $admin = makeSiteAdmin();

    $this->actingAs($admin);

    Livewire::test(UsersWidget::class)
        ->callTableBulkAction('delete', [$admin])
        ->assertNotified();

    expect(User::find($admin->id))->not->toBeNull();
});

// ── UsersWidget – changeRole action ──────────────────────────────────────────

test('changeRole action promotes a user to site administrator', function () {
    $target = makeTeacher();

    $this->actingAs(makeSiteAdmin());

    Livewire::test(UsersWidget::class)
        ->callTableAction('changeRole', $target, data: ['new_role' => 'site_admin'])
        ->assertNotified();

    expect($target->fresh()->isSiteAdmin())->toBeTrue();
});

test('changeRole action demotes an admin when another admin remains', function () {
    $admin1 = makeSiteAdmin();
    $admin2 = makeSiteAdmin();

    $this->actingAs($admin1);

    Livewire::test(UsersWidget::class)
        ->callTableAction('changeRole', $admin2, data: ['new_role' => 'user'])
        ->assertNotified();

    expect($admin2->fresh()->isSiteAdmin())->toBeFalse();
});

// Note: the last-admin server-side guard in demoteToUser() is intentional
// defense-in-depth but cannot be triggered via the UI: the ->hidden() guard on
// the changeRole action prevents an admin from targeting themselves, and you
// cannot reach a state where you are the sole admin targeting a different sole admin
// (the actor must be an admin to mount the widget, so ≥1 admin always remains).

test('changeRole action demoting to user removes all scoped role assignments', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $admin = makeSiteAdmin();

    $this->actingAs($admin);

    Livewire::test(UsersWidget::class)
        ->callTableAction('changeRole', $editor, data: ['new_role' => 'user'])
        ->assertNotified();

    expect(DB::table('subject_grade_user')->where('user_id', $editor->id)->exists())->toBeFalse();
});

test('changeRole action demoting to user clears subject_admin_user_id', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);
    $admin = makeSiteAdmin();

    $this->actingAs($admin);

    Livewire::test(UsersWidget::class)
        ->callTableAction('changeRole', $subjectAdmin, data: ['new_role' => 'user'])
        ->assertNotified();

    expect($sg->fresh()->subject_admin_user_id)->toBeNull();
});

// ── UsersWidget – message action ──────────────────────────────────────────────

test('message action creates a message record', function () {
    $target = makeTeacher();
    $admin = makeSiteAdmin();

    $this->actingAs($admin);

    Livewire::test(UsersWidget::class)
        ->callTableAction('message', $target, data: [
            'subject' => 'Hello',
            'body' => 'Test message body.',
        ])
        ->assertNotified();

    assertDatabaseHas(Message::class, [
        'from_user_id' => $admin->id,
        'to_user_id' => $target->id,
        'subject' => 'Hello',
        'body' => 'Test message body.',
    ]);
});
