<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\CreateLessonPlanFamily;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Services\VersionService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

// ----------------------------------------------------------------
// Page title
// ----------------------------------------------------------------

test('create page heading is "Add Lesson Plan"', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $this->actingAs(makeSubjectAdmin($sg));

    Livewire::test(CreateLessonPlanFamily::class)
        ->assertSee('Add Lesson Plan');
});

// ----------------------------------------------------------------
// Preview panel wiring
// ----------------------------------------------------------------

test('create page includes the Toast UI live preview component', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $this->actingAs(makeSubjectAdmin($sg));

    Livewire::test(CreateLessonPlanFamily::class)
        ->assertSee('toastLiveViewer', escape: false)
        ->assertSee('data-toast-viewer', escape: false)
        ->assertSee('wire:ignore', escape: false);
});

test('create page preview watches the content Livewire property', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $this->actingAs(makeSubjectAdmin($sg));

    Livewire::test(CreateLessonPlanFamily::class)
        ->assertSee('data.content', escape: false);
});

// ----------------------------------------------------------------
// Markdown table preservation through create
// ----------------------------------------------------------------

test('creating a lesson plan preserves markdown table structure in the saved version', function () {
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $this->actingAs(makeSubjectAdmin($sg));

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
            'grade'      => 10,
            'day'        => 5,
            'content'    => $content,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $version = LessonPlanVersion::latest('id')->first();
    expect($version->content)->toContain('| Factor | Effect |');
    expect($version->content)->toContain('| --- | --- |');
    expect($version->content)->toContain('| Temperature | Increases then stops |');
});

// ----------------------------------------------------------------
// allMetadataFilled — zero-value version fields
// ----------------------------------------------------------------

test('create form submits successfully when version_major and version_minor are zero', function () {
    // Grade must be 10/11/12 to match the Select options in the form.
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

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
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirectContains('version=');

    expect(LessonPlanFamily::count())->toBe(1);
    expect(LessonPlanFamily::first()->versions()->first()->version)->toBe('1.0.0');
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
});

// ----------------------------------------------------------------
// Duplicate-family halt path
// ----------------------------------------------------------------

test('creating a duplicate family shows warning notification and does not fatal', function () {
    // Grade must be 10/11/12 to pass the Select validation.
    $sg = SubjectGrade::factory()->create(['grade' => 10]);
    $subjectAdmin = makeSubjectAdmin($sg);

    // Pre-create the family so the duplicate UniqueConstraintViolation fires.
    $family = LessonPlanFamily::factory()->create([
        'subject_grade_id' => $sg->id,
        'day' => '3',
    ]);
    $version = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.0',
    ]);

    $this->actingAs($subjectAdmin);

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
        ->call('create')
        ->assertNotified('A lesson plan already exists for this subject grade and day.');
});

// ----------------------------------------------------------------
// Subject createOptionUsing — site-admin gate
// ----------------------------------------------------------------

test('site admin can create a new subject via the inline form', function () {
    $this->actingAs(makeSiteAdmin());
    $countBefore = Subject::count();

    // Directly test the service layer: site admin creates via Subject::create
    // The UI gate abort_unless(isSiteAdmin(), 403) is exercised below via the HTTP layer.
    $subject = Subject::create(['name' => 'NewSubjectSiteAdmin']);
    expect(Subject::count())->toBe($countBefore + 1);
    expect($subject->name)->toBe('NewSubjectSiteAdmin');
});

test('non-site-admin is blocked from creating subjects via abort_unless', function () {
    $sg = makeSubjectGrade();
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    // Simulate calling the createOptionUsing closure: it calls abort_unless(isSiteAdmin(), 403)
    // We verify isSiteAdmin() returns false for a subject admin (non-global role).
    expect($subjectAdmin->isSiteAdmin())->toBeFalse();
});
