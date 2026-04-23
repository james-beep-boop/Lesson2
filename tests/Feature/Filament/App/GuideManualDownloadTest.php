<?php

use App\Models\User;
use App\Services\GuideManualService;
use App\Support\GuideContent;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('manual download route returns 403 for unauthenticated requests', function () {
    $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertForbidden();
});

test('manual download route returns 403 for unverified users', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertForbidden();
});

test('english manual download serves the committed PDF file', function () {
    $teacher = makeTeacher();
    $this->actingAs($teacher);

    $manuals = app(GuideManualService::class);

    $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertOk()
        ->assertDownload($manuals->pdfFilename('en'))
        ->assertHeader('content-type', 'application/pdf');
});

test('swahili manual download serves the committed PDF file', function () {
    $this->actingAs(makeTeacher());

    $manuals = app(GuideManualService::class);

    $this->get(route('guide.manual.download', ['lang' => 'sw']))
        ->assertOk()
        ->assertDownload($manuals->pdfFilename('sw'))
        ->assertHeader('content-type', 'application/pdf');
});

test('teachers see viewing and translation sections but not admin sections', function () {
    $teacher = makeTeacher();
    $titles = collect(GuideContent::visibleSections($teacher, 'en'))->pluck('title')->all();

    expect($titles)
        ->toContain('Viewing Lessons')
        ->toContain('Translate to Swahili')
        ->not->toContain('Ask AI')
        ->not->toContain('Editing Lessons')
        ->not->toContain('Official Versions')
        ->not->toContain('Deletion Requests')
        ->not->toContain('Administration');
});

test('swahili guide content returns swahili section titles for teachers', function () {
    $teacher = makeTeacher();
    $titles = collect(GuideContent::visibleSections($teacher, 'sw'))->pluck('title')->all();

    expect($titles)
        ->toContain('Kutazama Masomo')
        ->toContain('Tafsiri kwa Kiswahili')
        ->not->toContain('Ask AI')
        ->not->toContain('Kuhariri Masomo')
        ->not->toContain('Matoleo Rasmi')
        ->not->toContain('Maombi ya Kufuta')
        ->not->toContain('Utawala');
});

test('manual download route returns 404 for invalid language', function () {
    $this->actingAs(makeTeacher());

    $this->get(route('guide.manual.download', ['lang' => 'fr']))
        ->assertNotFound();
});
