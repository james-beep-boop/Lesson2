<?php

use App\Services\GuideManualService;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('manual download route returns 403 for unauthenticated requests', function () {
    $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertForbidden();
});

test('english manual download returns a download with only the sections visible to the user', function () {
    $teacher = makeTeacher();
    $this->actingAs($teacher);

    $manuals = app(GuideManualService::class);

    $response = $this->get(route('guide.manual.download', ['lang' => 'en']))
        ->assertOk()
        ->assertDownload($manuals->pdfFilename('en'));

    $teacherMarkdown = $manuals->markdown('en', $teacher);

    expect($teacherMarkdown)
        ->toContain('# Kenya Lesson Plan Manual')
        ->toContain('## Viewing Lessons')
        ->toContain('## Translate to Swahili')
        ->toContain('click the **Translate to Swahili** button')
        ->not->toContain('## Ask AI')
        ->not->toContain('## Editing Lessons')
        ->not->toContain('## Official Versions')
        ->not->toContain('## Deletion Requests')
        ->not->toContain('## Administration');

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('swahili manual download returns the full manual for all users', function () {
    $teacher = makeTeacher();
    $this->actingAs($teacher);

    $manuals = app(GuideManualService::class);

    $this->get(route('guide.manual.download', ['lang' => 'sw']))
        ->assertOk()
        ->assertDownload($manuals->pdfFilename('sw'));

    expect($manuals->markdown('sw', $teacher))
        ->toContain('# Mwongozo wa Mpango wa Somo wa Kenya')
        ->toContain('## Kutazama Masomo')
        ->toContain('## Tafsiri kwa Kiswahili')
        ->toContain('**Translate to Swahili**')
        ->not->toContain('## Ask AI')
        ->not->toContain('## Kuhariri Masomo')
        ->not->toContain('## Matoleo Rasmi')
        ->not->toContain('## Maombi ya Kufuta')
        ->not->toContain('## Utawala');
});

test('manual service saves canonical manual files into storage', function () {
    $tempDir = sys_get_temp_dir().'/guide-manual-test-'.uniqid();

    $manuals = Mockery::mock(GuideManualService::class)->makePartial();
    $manuals->allows('outputDirectory')->andReturn($tempDir);

    $manuals->generateAndSaveAll('en');
    $manuals->generateAndSaveAll('sw');

    expect(File::exists($tempDir.'/'.$manuals->markdownFilename('en')))->toBeTrue();
    expect(File::exists($tempDir.'/'.$manuals->pdfFilename('en')))->toBeTrue();
    expect(File::exists($tempDir.'/'.$manuals->markdownFilename('sw')))->toBeTrue();
    expect(File::exists($tempDir.'/'.$manuals->pdfFilename('sw')))->toBeTrue();

    File::deleteDirectory($tempDir);
});

test('manual download route returns 404 for invalid language', function () {
    $this->actingAs(makeTeacher());

    $this->get(route('guide.manual.download', ['lang' => 'fr']))
        ->assertNotFound();
});
