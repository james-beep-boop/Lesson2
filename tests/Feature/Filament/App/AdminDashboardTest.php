<?php

use App\Filament\App\Pages\AdminDashboard;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Filament\App\Widgets\LessonVersionsWidget;
use App\Filament\App\Widgets\UsersWidget;
use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\Subject;
use App\Models\SubjectGrade;
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
        ->assertOk()
        ->assertSee('Site Administrator')
        ->assertSee('Subject Admins');
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

test('lesson versions widget rows link to the selected version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSiteAdmin());

    $component = Livewire::test(LessonVersionsWidget::class);

    expect($component->instance()->getTable()->getRecordUrl($version))->toBe(
        LessonPlanFamilyResource::versionUrl($version)
    );
});

test('toggleOfficial leaves the current official version unchanged', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->callTableAction('toggleOfficial', $version)
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBe($version->id);
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

test('bulk delete skips official versions and sends a warning', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->update(['official_version_id' => $version->id]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->callTableBulkAction('delete', [$version])
        ->assertNotified();

    // Official version must be preserved
    expect(LessonPlanVersion::find($version->id))->not->toBeNull();
    expect(LessonPlanFamily::find($family->id))->not->toBeNull();
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

// ── UsersWidget – role display and site-admin actions ─────────────────────────

test('users widget shows scoped assignment summaries', function () {
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

    Livewire::test(UsersWidget::class)
        ->assertSee('SA: Mathematics G10')
        ->assertSee('Ed: Science G8');
});

test('scoped assignment summary handles users with admin and editor assignments', function () {
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

    $target->load(['subjectGradesAsAdmin.subject', 'subjectGrades.subject']);

    expect($target->scopedAssignmentSummary())->toBe('SA: Mathematics G10 | Ed: Science G8');
});

test('grant site admin action promotes a user to site administrator', function () {
    $target = makeTeacher();

    $this->actingAs(makeSiteAdmin());

    Livewire::test(UsersWidget::class)
        ->callTableAction('grantSiteAdmin', $target)
        ->assertNotified();

    expect($target->fresh()->isSiteAdmin())->toBeTrue();
});

test('revoke site admin action removes global admin when another admin remains', function () {
    $admin1 = makeSiteAdmin();
    $admin2 = makeSiteAdmin();

    $this->actingAs($admin1);

    Livewire::test(UsersWidget::class)
        ->callTableAction('revokeSiteAdmin', $admin2)
        ->assertNotified();

    expect($admin2->fresh()->isSiteAdmin())->toBeFalse();
});

test('revoke site admin action keeps subject-grade assignments unchanged', function () {
    $sg = makeSubjectGrade();
    $admin = makeSiteAdmin();
    $subjectAdmin = makeSubjectAdmin($sg);
    $subjectAdmin->assignRole('site_administrator');

    $this->actingAs($admin);

    Livewire::test(UsersWidget::class)
        ->callTableAction('revokeSiteAdmin', $subjectAdmin)
        ->assertNotified();

    expect($subjectAdmin->fresh()->isSiteAdmin())->toBeFalse();
    expect($sg->fresh()->subject_admin_user_id)->toBe($subjectAdmin->id);
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

// ── LessonVersionsWidget – tab switching ──────────────────────────────────────

test('all tab shows every version', function () {
    [, $versionA] = makeFamilyWithVersion(makeSubjectGrade());
    [, $versionB] = makeFamilyWithVersion(makeSubjectGrade());

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$versionA, $versionB]);
});

test('official tab shows only official versions', function () {
    [$familyA, $official] = makeFamilyWithVersion(makeSubjectGrade());
    [, $unofficial] = makeFamilyWithVersion(makeSubjectGrade());
    $familyA->update(['official_version_id' => $official->id]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->set('activeTab', 'official')
        ->assertCanSeeTableRecords([$official])
        ->assertCanNotSeeTableRecords([$unofficial]);
});

test('latest tab shows only the newest version per family', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->set('activeTab', 'latest')
        ->assertCanSeeTableRecords([$v2])
        ->assertCanNotSeeTableRecords([$v1]);
});

test('favorites tab shows only versions from favorited families', function () {
    $admin = makeSiteAdmin();
    [$familyA, $favoritedVersion] = makeFamilyWithVersion(makeSubjectGrade());
    [, $unfavoritedVersion] = makeFamilyWithVersion(makeSubjectGrade());

    Favorite::factory()->for($admin, 'user')->create([
        'lesson_plan_family_id' => $familyA->id,
        'lesson_plan_version_id' => $favoritedVersion->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(LessonVersionsWidget::class)
        ->set('activeTab', 'favorites')
        ->assertCanSeeTableRecords([$favoritedVersion])
        ->assertCanNotSeeTableRecords([$unfavoritedVersion]);
});

test('favorites tab is empty when user has no favorites', function () {
    [, $version] = makeFamilyWithVersion(makeSubjectGrade());

    $this->actingAs(makeSiteAdmin());

    Livewire::test(LessonVersionsWidget::class)
        ->set('activeTab', 'favorites')
        ->assertCanNotSeeTableRecords([$version]);
});
