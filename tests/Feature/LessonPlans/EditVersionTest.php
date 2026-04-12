<?php

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Models\LessonPlanVersion;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('editor can enter edit mode — editMode becomes true, editContent equals current version content, baseLatestVersionId is set', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode');

    expect($component->get('editMode'))->toBeTrue();
    expect($component->get('editContent'))->toBe($v1->content);
    expect($component->get('baseLatestVersionId'))->toBe($v1->id);
});

test('the lesson editor opens in markdown mode to preserve table structure', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->assertSee("initialEditType: 'markdown'", false);
});

test('teacher with no role cannot enter edit mode', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();

    $this->actingAs($teacher);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->assertForbidden();
});

test('unauthorized user cannot call saveNewVersion() directly even without entering edit mode', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();

    $this->actingAs($teacher);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('editContent', '# Injected content')
        ->call('saveNewVersion')
        ->assertForbidden();
});

test('saving normalizes line endings before storing', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', "# Title\r\nWindows line endings\r\nAnother line")
        ->call('saveNewVersion');

    $latest = $family->fresh()->latestVersion;
    expect($latest->content)->toBe("# Title\nWindows line endings\nAnother line\n");
});

test('saving content identical to current version after normalization sends info notification and does not create a new version', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    // Set v1 content to already-normalized content
    $v1->content = "# Unchanged content\n";
    $v1->save();

    $this->actingAs($editor);

    $count = LessonPlanVersion::where('lesson_plan_family_id', $family->id)->count();

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', "# Unchanged content\r\n") // CRLF, same content after normalizing
        ->call('saveNewVersion')
        ->assertNotified('No changes detected — content is identical to the current version.');

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->count())->toBe($count);
});

test('saving changed content creates a new version', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Changed content')
        ->call('saveNewVersion')
        ->assertNotified('New version saved.');

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->count())->toBe(2);
});

test('the new version stores the normalized content, not the raw editor output', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', "# New content\r\nNo trailing newline")
        ->call('saveNewVersion');

    $latest = $family->fresh()->latestVersion;
    expect($latest->content)->toBe("# New content\nNo trailing newline\n");
    expect($latest->content)->not->toContain("\r");
});

test('saving content with a markdown table preserves the table structure', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    $content = <<<'MD'
# Lesson

| Column A | Column B |
| --- | --- |
| One | Two |
MD;

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', $content)
        ->call('saveNewVersion');

    $latest = $family->fresh()->latestVersion;

    expect($latest->content)->toContain('| Column A | Column B |');
    expect($latest->content)->toContain('| --- | --- |');
    expect($latest->content)->toContain('| One | Two |');
});

test('saving a complex GFM lesson preserves the full markdown structure', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    $content = file_get_contents(base_path('tests/fixtures/TestComplexMarkdown.md'));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', $content)
        ->call('saveNewVersion');

    $latest = $family->fresh()->latestVersion;

    expect($latest->content)->toBe($content);
    expect($latest->content)->toContain('| Grade Level | Subject | Strand |');
    expect($latest->content)->toContain('| :--- | :---: | ---: |');
    expect($latest->content)->toContain('```markdown');
});

test('cancelEditMode resets editMode, revisionNote, versionBump, editContent, and baseLatestVersionId', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Some in-progress work')
        ->set('revisionNote', 'Work in progress')
        ->set('versionBump', 'major')
        ->call('cancelEditMode');

    expect($component->get('editMode'))->toBeFalse();
    expect($component->get('revisionNote'))->toBeNull();
    expect($component->get('versionBump'))->toBe('patch');
    expect($component->get('baseLatestVersionId'))->toBeNull();
    expect($component->get('editContent'))->toBe($v1->content);
});

test('saveNewVersion resets all edit state after a successful save', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Content that differs')
        ->set('revisionNote', 'My note')
        ->set('versionBump', 'minor')
        ->call('saveNewVersion');

    expect($component->get('editMode'))->toBeFalse();
    expect($component->get('revisionNote'))->toBeNull();
    expect($component->get('versionBump'))->toBe('patch');
    expect($component->get('baseLatestVersionId'))->toBeNull();
});

test('saveNewVersion sends warning notification and keeps edit mode open when a newer version exists in the database', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode');

    // Simulate another user saving a version while this editor was open
    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $component
        ->set('editContent', '# My unsaved changes')
        ->call('saveNewVersion')
        ->assertNotified('The lesson plan was updated while you were editing.');

    // Edit mode must remain open — content must not be discarded
    expect($component->get('editMode'))->toBeTrue();
    expect($component->get('editContent'))->toBe('# My unsaved changes');
});

test('version bump selection major is respected on save', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Major change')
        ->set('versionBump', 'major')
        ->call('saveNewVersion');

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->where('version', '2.0.0')->exists())->toBeTrue();
});

test('version bump selection minor is respected on save', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Minor change')
        ->set('versionBump', 'minor')
        ->call('saveNewVersion');

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->where('version', '1.1.0')->exists())->toBeTrue();
});

test('version bump selection patch is respected on save', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Patch change')
        ->set('versionBump', 'patch')
        ->call('saveNewVersion');

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->where('version', '1.0.1')->exists())->toBeTrue();
});

test('revision note is stored with the new version', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Updated with note')
        ->set('revisionNote', 'Fixed a typo')
        ->call('saveNewVersion');

    $latest = $family->fresh()->latestVersion;
    expect($latest->revision_note)->toBe('Fixed a typo');
});

test('saving empty content fails validation', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $editor = makeEditor($sg);

    $this->actingAs($editor);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '')
        ->call('saveNewVersion')
        ->assertHasErrors(['editContent']);
});
