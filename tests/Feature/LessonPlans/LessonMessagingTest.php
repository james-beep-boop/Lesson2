<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

// ---------------------------------------------------------------------------
// openMessageModal
// ---------------------------------------------------------------------------

test('openMessageModal is available to authenticated non-system user', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'author')
        ->assertSet('showMessageModal', true)
        ->assertSet('messageRecipientType', 'author');
});

test('openMessageModal prefills subject with lesson context', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'author');

    $subject = $component->get('messageSubject');

    expect($subject)->toContain($sg->subject->name)
        ->toContain((string) $sg->grade)
        ->toContain((string) $family->day);
});

test('openMessageModal prefills body with lesson URL and context block', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'author');

    $body = $component->get('messageBody');

    expect($body)
        ->toContain('Lesson Context')
        ->toContain($sg->subject->name)
        ->toContain((string) $sg->grade)
        ->toContain((string) $family->day)
        ->toContain($version->version);
});

// ---------------------------------------------------------------------------
// Recipient shortcuts
// ---------------------------------------------------------------------------

test('author shortcut sends message to the selected version contributor', function () {
    $sg = makeSubjectGrade();
    $contributor = makeTeacher();
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $contributor->id,
    ]);

    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'author')
        ->call('sendLessonMessage')
        ->assertNotified();

    expect(
        Message::where('to_user_id', $contributor->id)->where('from_user_id', $sender->id)->exists()
    )->toBeTrue();
});

test('subject admin shortcut targets the correct subject-grade admin', function () {
    $sg = makeSubjectGrade();
    $admin = makeSubjectAdmin($sg);
    [$family] = makeFamilyWithVersion($sg);

    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'subject_admin')
        ->call('sendLessonMessage')
        ->assertNotified();

    expect(
        Message::where('to_user_id', $admin->id)->where('from_user_id', $sender->id)->exists()
    )->toBeTrue();
});

test('site admin shortcut sends to all site administrators', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $admin1 = makeSiteAdmin();
    $admin2 = makeSiteAdmin();
    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'site_admin')
        ->call('sendLessonMessage')
        ->assertNotified();

    expect(Message::where('from_user_id', $sender->id)->count())->toBeGreaterThanOrEqual(2);
    expect(Message::where('from_user_id', $sender->id)->where('to_user_id', $admin1->id)->exists())->toBeTrue();
    expect(Message::where('from_user_id', $sender->id)->where('to_user_id', $admin2->id)->exists())->toBeTrue();
});

test('any user shortcut excludes the system user from search results', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $systemUser = User::factory()->create(['is_system' => true]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('userSearchQuery', $systemUser->name);

    $results = $component->instance()->getMessageUserSearchResults();

    expect($results->pluck('id'))->not->toContain($systemUser->id);
});

test('any user search returns matching non-system users', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $target = User::factory()->create(['name' => 'Unique Search Target User', 'is_system' => false]);
    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('userSearchQuery', 'Unique Search Target');

    $results = $component->instance()->getMessageUserSearchResults();
    expect($results->pluck('id'))->toContain($target->id);
});

test('any user shortcut sends message to selected user', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $target = User::factory()->create(['is_system' => false]);
    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'any_user')
        ->call('selectMessageUser', $target->id)
        ->call('sendLessonMessage')
        ->assertNotified();

    expect(Message::where('to_user_id', $target->id)->where('from_user_id', $sender->id)->exists())->toBeTrue();
});

test('system user cannot send lesson messages', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $systemUser = User::factory()->create(['is_system' => true]);
    $this->actingAs($systemUser);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openMessageModal', 'author')
        ->assertForbidden();
});
