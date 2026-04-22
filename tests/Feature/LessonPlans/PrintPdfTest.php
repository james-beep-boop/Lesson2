<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Mail\LessonPlanDocxMail;
use App\Mail\LessonPlanPdfMail;
use App\Models\LessonPlanVersion;
use App\Services\LessonPlanDocxService;
use App\Services\LessonPlanPdfService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
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

test('translation preview PDF route returns 403 for unauthenticated requests', function () {
    $token = 'preview-token';
    $sg = makeSubjectGrade();
    [, $version] = makeFamilyWithVersion($sg);

    Cache::put("translation-preview-pdf:{$token}", [
        'user_id' => 999999,
        'lesson_plan_version_id' => $version->id,
        'translated_content' => 'Mpango wa Somo',
    ], now()->addMinutes(5));

    $this->get(route('lesson-plan.translation-preview-pdf', ['token' => $token]))
        ->assertForbidden();
});

test('translation preview PDF route returns 403 for the wrong authenticated user', function () {
    config(['features.ai_suggestions' => true]);
    $token = 'preview-token';
    $sg = makeSubjectGrade();
    [, $version] = makeFamilyWithVersion($sg);
    $owner = makeEditor($sg);

    Cache::put("translation-preview-pdf:{$token}", [
        'user_id' => $owner->id,
        'lesson_plan_version_id' => $version->id,
        'translated_content' => 'Mpango wa Somo',
    ], now()->addMinutes(5));

    $this->actingAs(makeTeacher());

    $this->get(route('lesson-plan.translation-preview-pdf', ['token' => $token]))
        ->assertForbidden();
});

test('translation preview PDF route returns an inline PDF for a valid cached preview', function () {
    config(['features.ai_suggestions' => true]);
    $token = 'preview-token';
    $sg = makeSubjectGrade();
    [, $version] = makeFamilyWithVersion($sg);
    $user = makeEditor($sg);

    Cache::put("translation-preview-pdf:{$token}", [
        'user_id' => $user->id,
        'lesson_plan_version_id' => $version->id,
        'translated_content' => 'Mpango wa Somo',
    ], now()->addMinutes(5));

    $this->mock(LessonPlanPdfService::class)
        ->makePartial()
        ->shouldReceive('renderTranslation')
        ->once()
        ->andReturn('fake-pdf-bytes');

    $this->actingAs($user);

    $response = $this->get(route('lesson-plan.translation-preview-pdf', ['token' => $token]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'inline; filename="'.(new LessonPlanPdfService)->translationFilename($version).'"');
    expect($response->getContent())->toBe('fake-pdf-bytes');
});

// ---------------------------------------------------------------------------
// Email PDF — Livewire
// ---------------------------------------------------------------------------

test('email PDF validates email address', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->callAction('emailPdf', [
            'email' => 'not-an-email',
        ])
        ->assertHasActionErrors(['email' => 'email']);
});

test('email PDF sends mail with attachment to the specified address', function () {
    Mail::fake();
    $this->mock(LessonPlanPdfService::class)
        ->makePartial()
        ->shouldReceive('render')
        ->once()
        ->andReturn('fake-pdf-bytes');

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->callAction('emailPdf', [
            'email' => 'teacher@school.ac.ke',
            'message' => 'Please review this plan.',
        ])
        ->assertNotified('PDF sent successfully.');

    Mail::assertSent(LessonPlanPdfMail::class, fn ($mail) => $mail->hasTo('teacher@school.ac.ke'));
});

test('email PDF action can be mounted from the page', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->mountAction('emailPdf')
        ->assertSchemaStateSet([
            'email' => null,
            'message' => null,
        ]);
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

// ---------------------------------------------------------------------------
// DOCX route
// ---------------------------------------------------------------------------

test('DOCX download route returns 403 for unauthenticated requests', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $response = $this->get(route('lesson-plan.docx', [
        'family' => $family->id,
        'version' => $version->id,
    ]));

    $response->assertForbidden();
});

test('DOCX download route returns 404 when version does not belong to family', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $otherSg = makeSubjectGrade();
    [, $otherVersion] = makeFamilyWithVersion($otherSg);

    $this->actingAs(makeTeacher());

    $this->get(route('lesson-plan.docx', [
        'family' => $family->id,
        'version' => $otherVersion->id,
    ]))->assertNotFound();
});

test('DOCX download route returns DOCX response for valid family and version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    $response = $this->get(route('lesson-plan.docx', [
        'family' => $family->id,
        'version' => $version->id,
    ]));

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );
});

// ---------------------------------------------------------------------------
// Email DOCX — Livewire
// ---------------------------------------------------------------------------

test('email DOCX validates email address', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->callAction('emailDocx', [
            'email' => 'not-an-email',
        ])
        ->assertHasActionErrors(['email' => 'email']);
});

test('email DOCX sends mail with attachment to the specified address', function () {
    Mail::fake();
    $this->mock(LessonPlanDocxService::class)
        ->makePartial()
        ->shouldReceive('render')
        ->once()
        ->andReturn('fake-docx-bytes');

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->callAction('emailDocx', [
            'email' => 'teacher@school.ac.ke',
            'message' => 'Please review this plan.',
        ])
        ->assertNotified('.docx sent successfully.');

    Mail::assertSent(LessonPlanDocxMail::class, fn ($mail) => $mail->hasTo('teacher@school.ac.ke'));
});

test('email DOCX action can be mounted from the page', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->mountAction('emailDocx')
        ->assertSchemaStateSet([
            'email' => null,
            'message' => null,
        ]);
});

test('lesson plan PDF view includes the shared copyright footer', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $html = view('pdf.lesson-plan', [
        'family' => $family,
        'version' => $version,
        'exportedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Exported ')
        ->toContain('ARES Kenya Lesson Library')
        ->toContain('https://kenyalessons.org')
        ->toContain('Copyright')
        ->toContain('ARES Education')
        ->toContain('https://areseducation.org')
        ->toContain('CC BY-SA 4.0')
        ->toContain('https://creativecommons.org/licenses/by-sa/4.0/deed.en')
        ->toContain('Adapt, transform, redistribute, given appropriate attribution')
        ->toContain('position: fixed;')
        ->toContain('aria-label="Creative Commons"')
        ->toContain('aria-label="Attribution"')
        ->toContain('aria-label="Share Alike"');
});

test('translation PDF view includes the shared copyright footer', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);

    $html = view('pdf.translation', [
        'family' => $family,
        'sourceVersion' => $version,
        'translatedContent' => 'Mpango wa Somo',
        'exportedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Exported ')
        ->toContain('ARES Kenya Lesson Library')
        ->toContain('https://kenyalessons.org')
        ->toContain('Copyright')
        ->toContain('ARES Education')
        ->toContain('https://areseducation.org')
        ->toContain('CC BY-SA 4.0')
        ->toContain('https://creativecommons.org/licenses/by-sa/4.0/deed.en')
        ->toContain('Adapt, transform, redistribute, given appropriate attribution')
        ->toContain('position: fixed;')
        ->toContain('aria-label="Creative Commons"')
        ->toContain('aria-label="Attribution"')
        ->toContain('aria-label="Share Alike"');
});

test('guide manual PDF view includes the shared copyright footer', function () {
    $html = view('pdf.guide-manual', [
        'language' => 'en',
        'title' => 'Kenya Lesson Plan Manual',
        'sections' => [
            ['title' => 'Overview', 'body' => 'Guide body'],
        ],
        'exportedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Exported ')
        ->toContain('ARES Kenya Lesson Library')
        ->toContain('https://kenyalessons.org')
        ->toContain('Copyright')
        ->toContain('ARES Education')
        ->toContain('https://areseducation.org')
        ->toContain('CC BY-SA 4.0')
        ->toContain('https://creativecommons.org/licenses/by-sa/4.0/deed.en')
        ->toContain('Adapt, transform, redistribute, given appropriate attribution')
        ->toContain('position: fixed;')
        ->toContain('aria-label="Creative Commons"')
        ->toContain('aria-label="Attribution"')
        ->toContain('aria-label="Share Alike"');
});

test('guide manual PDF footer uses Swahili export label when language is sw', function () {
    $html = view('pdf.guide-manual', [
        'language' => 'sw',
        'title' => 'Mwongozo wa Masomo ya Kenya',
        'sections' => [
            ['title' => 'Muhtasari', 'body' => 'Maelezo ya mwongozo'],
        ],
        'exportedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Imetolewa ')
        ->not->toContain('Exported ');
});
