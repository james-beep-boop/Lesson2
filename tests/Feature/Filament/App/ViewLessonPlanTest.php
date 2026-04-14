<?php

use App\Ai\Agents\LessonPlanAdvisor;
use App\Ai\Agents\MarkdownSegmentTranslator;
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

function fakeMarkdownSegmentTranslation(string $translatedText = 'Mpango wa Somo'): void
{
    MarkdownSegmentTranslator::fake(function ($prompt) use ($translatedText) {
        $promptText = is_object($prompt) && property_exists($prompt, 'prompt')
            ? $prompt->prompt
            : (string) $prompt;

        preg_match('/\[\s*(.*)\s*\]\s*$/s', $promptText, $matches);

        $segments = json_decode('['.($matches[1] ?? '').']', true, 512, JSON_THROW_ON_ERROR);

        return [
            'translations' => array_map(fn () => $translatedText, $segments),
        ];
    });
}

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

test('enterCompareMode sets compareMode true and seeds the right pane with an adjacent version', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v2->id)
        ->assertSet('compareMode', true);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
    expect($component->get('compareVersion')->id)->toBe($v1->id);
});

test('rendered compare view shows the new compare heading and return action', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v2->id)
        ->assertSee("Compare Versions: {$sg->subject->name} Grade {$sg->grade} Day {$family->day}")
        ->assertSee('Return to View / Edit')
        ->assertDontSee('Source Diff');
});

test('rendered compare view shows the fixed left version label and right version dropdown', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v2->id)
        ->assertSee('1.0.0')
        ->assertSee('1.0.1')
        ->assertSee("Version {$v2->version}")
        ->assertSee('Compare to')
        ->assertSee('compare-version-select')
        ->assertSeeHtml('ares-compare-actions')
        ->assertSeeHtml('ares-compare-meta-label')
        ->assertSeeHtml('ares-compare-meta-select')
        ->assertSee('data-toast-viewer-left', false)
        ->assertSee('data-toast-viewer-right', false);
});

test('rendered compare view starts with highlights enabled', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v2->id)
        ->assertSee('Hide Highlights');
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
        ->call('enterCompareMode', $v2->id)
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
    fakeMarkdownSegmentTranslation();

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview')
        ->assertSet('translationPanelOpen', true)
        ->assertSet('translatedContent', fn (string $content): bool => str_contains($content, 'Mpango wa Somo'));
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
    fakeMarkdownSegmentTranslation();

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
    MarkdownSegmentTranslator::fake(fn () => throw new RuntimeException('API unavailable'));

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

test('submitAiPrompt shows danger notification instead of crashing when AI fails', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanAdvisor::fake(fn () => throw new RuntimeException('API unavailable'));

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('aiPanelOpen', true)
        ->set('aiPrompt', 'Suggest improvements')
        ->call('submitAiPrompt')
        ->assertNotified('Ask AI unavailable')
        ->assertSet('aiResponse', '')
        ->assertSet('aiResponseComplete', false);
});

test('useAiPrompt sets the prompt and submits immediately', function () {
    config(['features.ai_suggestions' => true]);
    LessonPlanAdvisor::fake(['Here is some advice.']);

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('aiPanelOpen', true)
        ->call('useAiPrompt', 'Check for clarity')
        ->assertSet('aiPrompt', 'Check for clarity')
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
    fakeMarkdownSegmentTranslation();

    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeEditor($sg));

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->set('translationPanelOpen', true)
        ->call('translatePreview')
        ->assertSet('translatedContent', fn (string $content): bool => str_contains($content, 'Mpango wa Somo'))
        ->assertSet('translationComplete', true)
        ->assertSet('translationPanelOpen', true);
});

test('translatePreview does not set translationComplete true when translation fails', function () {
    config(['features.ai_suggestions' => true]);
    MarkdownSegmentTranslator::fake(fn () => throw new RuntimeException('API unavailable'));

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

test('single-version families show a disabled compare hint', function () {
    $sg = makeSubjectGrade();
    [$family] = makeFamilyWithVersion($sg);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->assertSee('Compare Two Plans')
        ->assertSee('Need at least 2 versions to compare');
});

test('compare mode shows a fixed left label and a right-side dropdown', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v2->id)
        ->assertSee("Version {$v2->version}")
        ->assertSeeHtml('id="compare-version-select"')
        ->assertSeeHtml('wire:change="selectCompareVersion($event.target.value)"')
        ->assertSee('Return to View / Edit')
        ->assertDontSee('Cancel Compare')
        ->assertDontSee('Source Diff');
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

test('selectCompareVersion updates only the right compare panel', function () {
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
        ->call('selectCompareVersion', $v3->id);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
    expect($component->get('compareVersion')->id)->toBe($v3->id);
    expect($component->get('compareMode'))->toBeTrue();
    expect($component->get('versionId'))->toBe($v2->id);
});

test('selectCompareVersion allows the same version on both panels', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('enterCompareMode', $v1->id);

    expect($component->get('selectedVersion')->id)->toBe($v1->id);
    expect($component->get('compareVersion')->id)->toBe($v2->id);

    $component->call('selectCompareVersion', $v1->id);

    expect($component->get('compareVersion')->id)->toBe($v1->id);
});

test('selectCompareVersion rejects versions not belonging to the current family', function () {
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
    $originalRight = $component->get('compareVersion')->id;

    $component->call('selectCompareVersion', $otherVersion->id);

    expect($component->get('selectedVersion')->id)->toBe($originalLeft);
    expect($component->get('compareVersion')->id)->toBe($originalRight);
});

test('compare mode keeps the left pane fixed while the right pane changes', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
        'content' => '# Newer lesson content',
    ]);

    $this->actingAs(makeTeacher());

    $component = Livewire::test(ViewLessonPlanFamily::class, ['record' => $family])
        ->call('selectVersion', $v2->id)
        ->call('enterCompareMode', $v2->id)
        ->call('selectCompareVersion', $v1->id);

    expect($component->get('selectedVersion')->id)->toBe($v2->id);
    expect($component->get('compareVersion')->id)->toBe($v1->id);

    $component
        ->assertSee("Version {$v2->version}")
        ->assertSee("Version {$v1->version}");
});
