<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\CreateLessonPlanFamily;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Services\VersionService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function fakeLessonPlanUpload(string $content = '# Lesson Plan', string $name = 'lesson-plan.md'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

// ----------------------------------------------------------------
// Page title
// ----------------------------------------------------------------

test('create page heading is "Add Lesson Plan"', function () {
    $this->actingAs(makeSiteAdmin());

    Livewire::test(CreateLessonPlanFamily::class)
        ->assertSee('Add Lesson Plan')
        ->assertSee('Add to Library');
});

// ----------------------------------------------------------------
// Content editor and preview panels are not present
// ----------------------------------------------------------------

test('create page does not include the markdown editor or preview panels', function () {
    $this->actingAs(makeSiteAdmin());

    Livewire::test(CreateLessonPlanFamily::class)
        ->assertDontSee('Lesson Plan Content (Markdown)')
        ->assertDontSee('Review and edit');
});

// ----------------------------------------------------------------
// Markdown table preservation through create
// ----------------------------------------------------------------

test('creating a lesson plan preserves markdown table structure in the saved version', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $this->actingAs(makeSiteAdmin());

    $content = <<<'MD'
# Enzyme Lesson

| Factor | Effect |
| --- | --- |
| Temperature | Increases then stops |
| pH | Decreases at extremes |
MD;

    Livewire::test(CreateLessonPlanFamily::class)
        ->fillForm([
            'subject_id' => $sg->subject_id,
            'grade' => 10,
            'day' => 5,
            'content' => $content,
        ])
        ->set('data.lesson_file', fakeLessonPlanUpload($content))
        ->call('create')
        ->assertHasNoFormErrors();

    $version = LessonPlanVersion::latest('id')->first();
    expect($version->content)->toContain('| Factor | Effect |');
    expect($version->content)->toContain('| --- | --- |');
    expect($version->content)->toContain('| Temperature | Increases then stops |');
    expect($version->family->fresh()->official_version_id)->toBe($version->id);
});

// ----------------------------------------------------------------
// allMetadataFilled — zero-value version fields
// ----------------------------------------------------------------

test('create form submits successfully when version_major and version_minor are zero', function () {
    // Grade must be 10/11/12 to match the Select options in the form.
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $siteAdmin = makeSiteAdmin();

    $this->actingAs($siteAdmin);

    Livewire::test(CreateLessonPlanFamily::class)
        ->fillForm([
            'subject_id' => $sg->subject_id,
            'grade' => 10,
            'day' => 1,
            'version_number' => 1,
            'version_major' => 0,
            'version_minor' => 0,
            'content' => '# Lesson Plan',
        ])
        ->set('data.lesson_file', fakeLessonPlanUpload())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirectContains('version=');

    expect(LessonPlanFamily::count())->toBe(1);
    expect(LessonPlanFamily::first()->versions()->first()->version)->toBe('1.0.0');
    expect(LessonPlanFamily::first()->official_version_id)->toBe(LessonPlanFamily::first()->versions()->first()->id);
});

// ----------------------------------------------------------------
// First version is always 1.0.0
// ----------------------------------------------------------------

test('VersionService always creates first version as 1.0.0', function () {
    $sg = makeSubjectGrade();
    $contributor = makeTeacher();
    $service = new VersionService;

    $version = $service->createFamilyWithFirstVersion(
        $sg->id, '5', '# Content', null, $contributor,
    );

    expect($version->version)->toBe('1.0.0');
    expect($version->fresh()->version)->toBe('1.0.0');
    expect($version->family->fresh()->official_version_id)->toBe($version->id);
});

// ----------------------------------------------------------------
// Duplicate-family halt path
// ----------------------------------------------------------------

test('creating a duplicate family shows warning notification and does not fatal', function () {
    // Grade must be 10/11/12 to pass the Select validation.
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $siteAdmin = makeSiteAdmin();

    // Pre-create the family so the duplicate UniqueConstraintViolation fires.
    $family = LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'day' => '3',
    ]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);

    $this->actingAs($siteAdmin);

    Livewire::test(CreateLessonPlanFamily::class)
        ->fillForm([
            'subject_id' => $sg->subject_id,
            'grade' => 10,
            'day' => 3,
            'version_number' => 1,
            'version_major' => 0,
            'version_minor' => 0,
            'content' => '# Duplicate',
        ])
        ->set('data.lesson_file', fakeLessonPlanUpload('# Duplicate'))
        ->call('create')
        ->assertNotified('A lesson plan already exists for this subject grade and day.');
});

test('creating a duplicate family redirects to the existing family view URL', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $siteAdmin = makeSiteAdmin();

    $family = LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'day' => '4',
    ]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);
    // Set official version so we can assert it's preferred as the redirect target.
    $family->official_version_id = $version->id;
    $family->save();

    $this->actingAs($siteAdmin);

    Livewire::test(CreateLessonPlanFamily::class)
        ->fillForm([
            'subject_id' => $sg->subject_id,
            'grade' => 10,
            'day' => 4,
            'content' => '# Duplicate',
        ])
        ->set('data.lesson_file', fakeLessonPlanUpload('# Duplicate'))
        ->call('create')
        ->assertRedirectContains((string) $family->id)
        ->assertRedirectContains('version='.$version->id);
});

test('creating a lesson plan requires an uploaded lesson file', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $this->actingAs(makeSiteAdmin());

    Livewire::test(CreateLessonPlanFamily::class)
        ->fillForm([
            'subject_id' => $sg->subject_id,
            'grade' => 10,
            'day' => 6,
            'content' => '# Lesson Plan',
        ])
        ->call('create')
        ->assertHasFormErrors(['lesson_file']);
});

test('subject admin cannot access the create lesson plan page', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin)
        ->get(CreateLessonPlanFamily::getUrl())
        ->assertForbidden();
});

test('site admin can access the create lesson plan page', function () {
    $this->actingAs(makeSiteAdmin())
        ->get(CreateLessonPlanFamily::getUrl())
        ->assertOk();
});

test('processed upload stays usable after later metadata changes', function () {
    $siteAdmin = makeSiteAdmin();
    $subjectA = Subject::factory()->create(['name' => 'Agriculture']);
    $subjectB = Subject::factory()->create(['name' => 'Biology']);
    SubjectGrade::factory()->create(['subject_id' => $subjectA->id, 'grade' => 10]);
    SubjectGrade::factory()->create(['subject_id' => $subjectB->id, 'grade' => 10]);

    $this->actingAs($siteAdmin);

    Livewire::test(CreateLessonPlanFamily::class)
        ->fillForm([
            'subject_id' => $subjectA->id,
            'grade' => 10,
            'day' => 6,
        ])
        ->set('data.lesson_file', fakeLessonPlanUpload('# Imported lesson', 'imported-plan.md'))
        ->assertSet('processedLessonFilename', 'imported-plan.md')
        ->fillForm([
            'subject_id' => $subjectB->id,
            'grade' => 10,
            'day' => 7,
        ])
        ->assertSet('processedLessonFilename', 'imported-plan.md')
        ->call('create')
        ->assertHasNoFormErrors();

    $version = LessonPlanVersion::latest('id')->first();

    expect($version->content)->toBe('# Imported lesson');
    expect($version->family->day)->toBe('7');
    expect($version->family->subjectGrade->subject_id)->toBe($subjectB->id);
});

// ----------------------------------------------------------------
// Subject createOptionUsing — site-admin gate
// ----------------------------------------------------------------

test('site admin can create a new subject via the inline form', function () {
    $this->actingAs(makeSiteAdmin());
    $countBefore = Subject::count();

    Livewire::test(CreateLessonPlanFamily::class)
        ->call('createSubjectOption', [
            'name' => 'NewSubjectSiteAdmin',
        ]);

    $subject = Subject::where('name', 'NewSubjectSiteAdmin')->firstOrFail();

    expect(Subject::count())->toBe($countBefore + 1);
    expect($subject->name)->toBe('NewSubjectSiteAdmin');
});

test('site admin cannot create a blank subject via the inline form', function () {
    $this->actingAs(makeSiteAdmin());

    Livewire::test(CreateLessonPlanFamily::class)
        ->call('createSubjectOption', ['name' => null])
        ->assertHasErrors(['name']);
});
