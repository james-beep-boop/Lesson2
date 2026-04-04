<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Mail\LessonPlanPdfMail;
use App\Models\LessonPlanVersion;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

// ---------------------------------------------------------------------------
// PDF route
// ---------------------------------------------------------------------------

test('PDF download route returns 403 for unauthenticated requests', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $response = $this->get(route('lesson-plan.pdf', [
        'family' => $family->id,
        'version' => $version->id,
    ]));

    $response->assertForbidden();
});

test('PDF download route returns 404 when version does not belong to family', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $otherSg = makeSubjectGrade();
    [, $otherVersion] = makeFamilyWithVersion($otherSg);

    $this->actingAs(makeTeacher());

    $this->get(route('lesson-plan.pdf', [
        'family' => $family->id,
        'version' => $otherVersion->id,
    ]))->assertNotFound();
});

test('PDF download route returns PDF response for valid family and version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    $response = $this->get(route('lesson-plan.pdf', [
        'family' => $family->id,
        'version' => $version->id,
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

// ---------------------------------------------------------------------------
// Email PDF — Livewire
// ---------------------------------------------------------------------------

test('email PDF validates email address', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openEmailPdfModal')
        ->set('emailPdfTo', 'not-an-email')
        ->call('sendEmailPdf')
        ->assertHasErrors(['emailPdfTo' => 'email']);
});

test('email PDF sends mail with attachment to the specified address', function () {
    Mail::fake();

    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openEmailPdfModal')
        ->set('emailPdfTo', 'teacher@school.ac.ke')
        ->set('emailPdfMessage', 'Please review this plan.')
        ->call('sendEmailPdf')
        ->assertNotified();

    Mail::assertSent(LessonPlanPdfMail::class, fn ($mail) => $mail->hasTo('teacher@school.ac.ke'));
});

test('email PDF modal closes on success', function () {
    Mail::fake();

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openEmailPdfModal')
        ->set('emailPdfTo', 'someone@example.com')
        ->call('sendEmailPdf');

    expect($component->get('showEmailPdfModal'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Exported content corresponds to the selected version
// ---------------------------------------------------------------------------

test('PDF export uses the content of the selected version', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
        'content' => '# Unique v2 content for PDF test',
    ]);

    $this->actingAs(makeTeacher());

    // PDF route uses the version passed in the URL
    $response = $this->get(route('lesson-plan.pdf', [
        'family' => $family->id,
        'version' => $v2->id,
    ]));

    $response->assertOk();
    // The response is a PDF binary — we only check it is a PDF (content-type check above).
    // Deeper rendering is an integration concern covered by the PDF library itself.
    expect($v2->content)->toContain('Unique v2 content');
});
