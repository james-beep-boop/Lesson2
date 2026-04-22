<?php

use App\Filament\App\Resources\LessonPlanFamilyResource;
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

test('message author action is visible to authenticated non-system users', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertActionVisible('messageAuthor');
});

test('message author action prefills subject and body with lesson context', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $expectedBody = 'Subject: '.$sg->subject->name
        .' Grade '.$sg->grade
        .' Day '.$family->day
        .' Version v'.$version->version."\n"
        .'Contributor: '.$version->contributor->name."\n\n"
        .'---'."\n"
        .'Lesson plan: ['.$sg->subject->name
        .' Grade '.$sg->grade
        .' Day '.$family->day
        .' version '.$version->version
        .']('.LessonPlanFamilyResource::versionUrl($version).')';

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->mountAction('messageAuthor')
        ->assertSchemaStateSet([
            'subject' => 'Question about '.$sg->subject->name.' Grade '.$sg->grade.' Day '.$family->day.' v'.$version->version,
            'body' => $expectedBody,
        ]);
});

test('message action trigger renders without the old inline message panel', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('Message About This')
        ->assertDontSee('Message About This Lesson');
});

test('author message action sends to the selected version contributor', function () {
    $sg = makeSubjectGrade();
    $contributor = makeTeacher();
    $family = LessonPlanFamily::factory()->create(['subject_grade_id' => $sg->id]);
    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
        'contributor_id' => $contributor->id,
    ]);

    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->callAction('messageAuthor', [
            'subject' => 'Please review this lesson',
            'body' => 'Can you review the current version?',
        ])
        ->assertNotified('Message sent.');

    expect(
        Message::where('to_user_id', $contributor->id)
            ->where('from_user_id', $sender->id)
            ->where('subject', 'Please review this lesson')
            ->exists()
    )->toBeTrue();
});

test('subject admin message action targets the correct subject-grade admin', function () {
    $sg = makeSubjectGrade();
    $admin = makeSubjectAdmin($sg);
    [$family] = makeFamilyWithVersion($sg);

    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertActionVisible('messageSubjectAdmin')
        ->callAction('messageSubjectAdmin', [
            'subject' => 'Question for subject admin',
            'body' => 'Could you take a look at this lesson?',
        ])
        ->assertNotified('Message sent.');

    expect(
        Message::where('to_user_id', $admin->id)
            ->where('from_user_id', $sender->id)
            ->where('subject', 'Question for subject admin')
            ->exists()
    )->toBeTrue();
});

test('subject admin message action is hidden when the current user is the subject admin', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $this->actingAs(makeSubjectAdmin($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertActionHidden('messageSubjectAdmin');
});

test('site admin message action sends to all other site administrators', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $admin1 = makeSiteAdmin();
    $admin2 = makeSiteAdmin();
    $sender = makeTeacher();
    $this->actingAs($sender);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->callAction('messageSiteAdmin', [
            'subject' => 'Question for site admins',
            'body' => 'Please review this lesson.',
        ])
        ->assertNotified('Message sent to 2 recipients.');

    expect(Message::where('from_user_id', $sender->id)->where('to_user_id', $admin1->id)->exists())->toBeTrue();
    expect(Message::where('from_user_id', $sender->id)->where('to_user_id', $admin2->id)->exists())->toBeTrue();
});

test('system user cannot use lesson message actions', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $systemUser = User::factory()->create(['is_system' => true]);
    $this->actingAs($systemUser);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertActionHidden('messageAuthor')
        ->assertActionHidden('messageSiteAdmin');
});
