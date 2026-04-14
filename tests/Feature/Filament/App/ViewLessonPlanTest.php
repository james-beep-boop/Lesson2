<?php

use App\Ai\Agents\LessonPlanAdvisor;
use App\Ai\Agents\LessonPlanTranslator;
use App\Filament\App\Resources\LessonPlanFamilyResource\Pages\ViewLessonPlanFamily;
use App\Models\DeletionRequest;
use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('view page loads for authenticated user', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertOk();
});

test('view page selects the official version by default', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);
    $family->official_version_id = $v1->id;
    $family->save();

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family]);

    expect($component->get('selectedVersion')->id)->toBe($v1->id);
});

test('view page honors the version query parameter', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.2',
    ]);
    $family->official_version_id = $v1->id;
    $family->save();

    $this->actingAs(makeTeacher());

    $component = Livewire::withQueryParams(['version' => $v2->id])
        ->test(ViewLessonPlanFamily::class, ['record' => $family]);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
});

test('mark official sets the official version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('markOfficial')
        ->assertNotified();

    expect($family->fresh()->official_version_id)->toBe($version->id);
});

test('save new version creates a new version and sends notification', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterEditMode')
        ->set('editContent', '# Updated content')
        ->set('versionBump', 'patch')
        ->call('saveNewVersion')
        ->assertNotified();

    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->count())->toBe(2);
    expect(LessonPlanVersion::where('lesson_plan_family_id', $family->id)->where('version', '1.0.1')->exists())->toBeTrue();
    $component->assertSet('versionId', LessonPlanVersion::where('lesson_plan_family_id', $family->id)->where('version', '1.0.1')->value('id'));
});

test('teacher cannot save a new version', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $teacher = makeTeacher();

    $this->actingAs($teacher);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('editContent', '# Unauthorized change')
        ->call('saveNewVersion')
        ->assertForbidden();
});

test('favoriting a version records the user favorite', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);
    $user = makeTeacher();

    $this->actingAs($user);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('favorite')
        ->assertNotified();

    expect(Favorite::where('user_id', $user->id)
        ->where('lesson_plan_family_id', $family->id)
        ->exists()
    )->toBeTrue();
});

test('request deletion creates a pending deletion request', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('deletionReason', 'Outdated content')
        ->call('requestDeletion')
        ->assertNotified();

    expect(DeletionRequest::where('lesson_plan_version_id', $version->id)
        ->whereNull('resolved_at')
        ->exists()
    )->toBeTrue();
});

test('duplicate deletion request is rejected with a warning', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);

    // Create the first deletion request manually
    $dr = new DeletionRequest([
        'lesson_plan_version_id' => $version->id,
        'reason' => 'First request',
    ]);
    $dr->requested_by_user_id = $subjectAdmin->id;
    $dr->save();

    $this->actingAs($subjectAdmin);

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('hasPendingDeletion', true)
        ->set('deletionReason', 'Duplicate')
        ->call('requestDeletion')
        ->assertNotified();

    // Should still be only one deletion request
    expect(DeletionRequest::where('lesson_plan_version_id', $version->id)->count())->toBe(1);
});

test('select version switches the displayed version', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
    $component->assertSet('versionId', $v2->id);
});

// ---------------------------------------------------------------------------
// Rendered compare view
// ---------------------------------------------------------------------------

test('enterCompareMode sets compareMode true and compareView to rendered by default', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->assertSet('compareMode', true)
        ->assertSet('compareView', 'rendered');
});

test('toggleCompareView cycles between rendered and source', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->assertSet('compareView', 'rendered')
        ->call('toggleCompareView')
        ->assertSet('compareView', 'source')
        ->call('toggleCompareView')
        ->assertSet('compareView', 'rendered');
});

test('rendered compare view shows version labels for both panes', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->assertSee('1.0.0')
        ->assertSee('1.0.1')
        ->assertSee('Left')
        ->assertSee('Right')
        ->assertSee('data-toast-viewer-left', false)
        ->assertSee('data-toast-viewer-right', false);
});

test('rendered compare view shows Rendered View button to switch to source diff', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->assertSee('Source Diff')
        ->call('toggleCompareView')
        ->assertSee('Rendered View')
        ->assertSee('Stacked');
});

test('rendered compare view shows Highlight changes button', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->assertSee('Highlight changes');
});

test('source diff mode does not show Highlight changes button', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->call('toggleCompareView')
        ->assertDontSee('Highlight changes');
});

test('rendered compare view wires Highlight button to toggleHighlights via Alpine', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v1->id)
        ->assertSee('toggleHighlights()', false);
});

// ---------------------------------------------------------------------------
// Server-rendered fallback markup (robustness / JS-failure path)
// ---------------------------------------------------------------------------

test('single viewer emits server-rendered fallback with Alpine mounted binding', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('ares-print-content', false)
        ->assertSee("mounted ? 'display:none' : 'display:block'", false);
});

test('compare fallback emits two-column grid style until viewers mount', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v1->id)
        ->assertSee('ares-print-compare', false)
        ->assertSee('display:grid;grid-template-columns:1fr 1fr', false);
});

// ---------------------------------------------------------------------------
// Swahili translation preview
// ---------------------------------------------------------------------------

test('translate button visible to editor when AI flag enabled', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('Translate to Swahili');
});

test('translate button visible to subject admin when AI flag enabled', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSubjectAdmin($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('Translate to Swahili');
});

test('translate button hidden when AI flag disabled', function () {
    config(['features.ai_suggestions' => false]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeSubjectAdmin($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertDontSee('Translate to Swahili');
});

test('translate button hidden from plain teacher', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertDontSee('Translate to Swahili');
});

test('openTranslationPanel opens panel and clears previous content', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translatedContent', 'stale content')
        ->call('openTranslationPanel')
        ->assertSet('translationPanelOpen', true)
        ->assertSet('translatedContent', '');
});

test('openTranslationPanel is forbidden for plain teacher', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('openTranslationPanel')
        ->assertForbidden();
});

test('translatePreview sets translatedContent and keeps panel open', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanTranslator::fake(['Mpango wa Somo']);

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview')
        ->assertSet('translationPanelOpen', true)
        ->assertSet('translatedContent', 'Mpango wa Somo');
});

test('translatePreview is forbidden for plain teacher', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('translatePreview')
        ->assertForbidden();
});

test('translatePreview writes nothing to the database', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanTranslator::fake(['Mpango wa Somo']);

    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $originalContent = $version->content;
    $versionCountBefore = LessonPlanVersion::count();
    $familyCountBefore = LessonPlanFamily::count();

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview');

    expect(LessonPlanVersion::count())->toBe($versionCountBefore);
    expect(LessonPlanFamily::count())->toBe($familyCountBefore);
    expect($version->fresh()->content)->toBe($originalContent);
});

test('translatePreview shows danger notification and closes panel when translation fails', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanTranslator::fake(fn () => throw new RuntimeException('API unavailable'));

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview')
        ->assertNotified('Translation unavailable')
        ->assertSet('translationPanelOpen', false);
});

// ---------------------------------------------------------------------------
// aiResponseComplete / translationComplete completion flags
// ---------------------------------------------------------------------------

test('submitAiPrompt sets aiResponseComplete false before streaming and true after', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanAdvisor::fake(['Here is some advice.']);

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('aiPanelOpen', true)
        ->set('aiPrompt', 'Suggest improvements')
        ->call('submitAiPrompt');

    $component
        ->assertSet('aiResponse', 'Here is some advice.')
        ->assertSet('aiResponseComplete', true);
});

test('closeAiPanel clears aiResponse and resets aiResponseComplete', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('aiPanelOpen', true)
        ->set('aiResponse', '## Previous response')
        ->set('aiResponseComplete', true)
        ->call('closeAiPanel')
        ->assertSet('aiPanelOpen', false)
        ->assertSet('aiResponse', '')
        ->assertSet('aiResponseComplete', false);
});

test('openTranslationPanel resets translationComplete to false', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationComplete', true)
        ->set('translatedContent', 'stale')
        ->call('openTranslationPanel')
        ->assertSet('translationPanelOpen', true)
        ->assertSet('translatedContent', '')
        ->assertSet('translationComplete', false);
});

test('translatePreview sets translationComplete true after successful completion', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanTranslator::fake(['Mpango wa Somo']);

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview')
        ->assertSet('translatedContent', 'Mpango wa Somo')
        ->assertSet('translationComplete', true)
        ->assertSet('translationPanelOpen', true);
});

test('translatePreview does not set translationComplete true when translation fails', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanTranslator::fake(fn () => throw new RuntimeException('API unavailable'));

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview')
        ->assertSet('translationComplete', false);
});

test('closeTranslationPanel resets translationComplete to false', function () {
    config(['features.ai_suggestions' => true]);
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->set('translationComplete', true)
        ->set('translatedContent', 'Mpango wa Somo')
        ->call('closeTranslationPanel')
        ->assertSet('translationPanelOpen', false)
        ->assertSet('translationComplete', false)
        ->assertSet('translatedContent', '');
});

// ---------------------------------------------------------------------------
// Compare UI — control panel, action bar hiding, new methods
// ---------------------------------------------------------------------------

test('action bar is hidden when compare mode is active', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeEditor($sg));

    // Confirm action bar is visible in normal mode
    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('Compare Two Plans')
        ->assertSee('Save / Send Options')
        // Enter compare mode and confirm action bar is gone
        ->call('enterCompareMode', $v2->id)
        ->assertDontSee('Compare Two Plans')
        ->assertDontSee('Save / Send Options')
        ->assertDontSee('Edit This Plan');
});

test('version panel is hidden when compare mode is active', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('Now Viewing')
        ->call('enterCompareMode', $v2->id)
        ->assertDontSee('Now Viewing')
        ->assertDontSee('Other Versions');
});

test('compare control panel is shown with both dropdowns when in compare mode', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id)
        ->assertSee('Left Panel')
        ->assertSee('Right Panel')
        ->assertSee('Cancel Compare');
});

test('cancelCompare exits compare mode and clears compare state', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id);

    expect($component->get('compareMode'))->toBeTrue();

    $component->call('cancelCompare');

    expect($component->get('compareMode'))->toBeFalse();
    expect($component->get('compareVersion'))->toBeNull();
    expect($component->get('diffHtml'))->toBe('');
});

test('cancelCompare restores the normal view action bar', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id)
        ->call('cancelCompare')
        ->assertSee('Compare Two Plans')
        ->assertSee('Now Viewing');
});

test('runCompare sets both left and right versions and keeps compare mode active', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);
    $v3 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.2',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id)
        ->call('runCompare', $v2->id, $v3->id);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
    expect($component->get('compareVersion')->id)->toBe($v3->id);
    expect($component->get('compareMode'))->toBeTrue();
    expect($component->get('versionId'))->toBe($v2->id);
});

test('runCompare ignores calls where both versions are the same', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id);

    $originalLeft = $component->get('selectedVersion')->id;

    // Attempt to compare a version with itself — should be a no-op
    $component->call('runCompare', $v1->id, $v1->id);

    expect($component->get('selectedVersion')->id)->toBe($originalLeft);
});

test('runCompare rejects versions not belonging to the current family', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    [$otherFamily, $otherVersion] = makeFamilyWithVersion(makeSubjectGrade());

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id);

    $originalLeft = $component->get('selectedVersion')->id;

    $component->call('runCompare', $v1->id, $otherVersion->id);

    // Right version is from another family — should be a no-op
    expect($component->get('selectedVersion')->id)->toBe($originalLeft);
    expect($component->get('compareVersion')->id)->toBe($v2->id);
});

test('left pane shows the user-chosen left version regardless of version number order', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
        'content' => '# Newer lesson content',
    ]);

    $this->actingAs(makeTeacher());

    // Select the NEWER version as left, older as right — the opposite of version-number order.
    // The left pane must show the newer version, not have it silently swapped back.
    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)   // left = v1.0.1
        ->call('enterCompareMode', $v1->id); // right = v1.0.0

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
    expect($component->get('compareVersion')->id)->toBe($v1->id);

    // The rendered HTML must have the newer version labelled Left and the older labelled Right,
    // not swapped by version_compare.
    $component
        ->assertSeeInOrder(['1.0.1', 'Left'])
        ->assertSeeInOrder(['1.0.0', 'Right']);
});
