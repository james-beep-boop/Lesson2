<?php

use App\Services\GuideManualService;
use Illuminate\Support\Facades\File;

test('manual download route returns 403 for unauthenticated requests', function () {
    $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertForbidden();
});

test('english manual download saves markdown and pdf files and returns a download', function () {
    $this->actingAs(makeTeacher());

    $manuals = app(GuideManualService::class);

    File::delete([
        $manuals->markdownPath('en'),
        $manuals->pdfPath('en'),
    ]);

    $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertOk()
        ->assertDownload($manuals->pdfFilename('en'));

    expect(File::exists($manuals->markdownPath('en')))->toBeTrue();
    expect(File::exists($manuals->pdfPath('en')))->toBeTrue();
    expect(File::get($manuals->markdownPath('en')))
        ->toContain('# Kenya Lesson Plan Manual')
        ->toContain('## Viewing Lessons');
});

test('swahili manual download saves markdown and pdf files and returns a download', function () {
    $this->actingAs(makeTeacher());

    $manuals = app(GuideManualService::class);

    File::delete([
        $manuals->markdownPath('sw'),
        $manuals->pdfPath('sw'),
    ]);

    $this->get(route('guide.manual.download', ['lang' => 'sw']))
        ->assertOk()
        ->assertDownload($manuals->pdfFilename('sw'));

    expect(File::exists($manuals->markdownPath('sw')))->toBeTrue();
    expect(File::exists($manuals->pdfPath('sw')))->toBeTrue();
    expect(File::get($manuals->markdownPath('sw')))
        ->toContain('# Mwongozo wa Mpango wa Somo wa Kenya')
        ->toContain('## Kutazama Masomo');
});

test('manual download route returns 404 for invalid language', function () {
    $this->actingAs(makeTeacher());

    $this->get(route('guide.manual.download', ['lang' => 'fr']))
        ->assertNotFound();
});
