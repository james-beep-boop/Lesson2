<?php

use App\Filament\App\Pages\Guide;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('guide page renders for authenticated users', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(Guide::class)
        ->assertOk()
        ->assertSee('Guide')
        ->assertSee('Download Manual');
});

test('guide defaults to English language', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);

    expect($component->get('language'))->toBe('en');
});

test('guide shows English content by default', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class)
        ->assertSee('Viewing Lessons')
        ->assertSeeHtml('collapsed');

    $viewingLessons = collect($component->instance()->sections())
        ->firstWhere('title', 'Viewing Lessons');

    expect($viewingLessons['body'])
        ->toContain('Browse lessons from the **Lessons** menu and click on a lesson to view it.')
        ->toContain('green checkmark');
});

test('guide uses full content width', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);

    expect($component->instance()->getMaxContentWidth())->toBe(Width::Full);
});

test('guide shows the english sections in the new order', function () {
    $this->actingAs(makeSiteAdmin());

    $sections = Livewire::test(Guide::class)->instance()->sections();

    expect(array_column($sections, 'title'))->toBe([
        'Viewing Lessons',
        'Editing Lessons',
        'Comparing Versions',
        'Official Versions',
        'Favorites',
        'Messaging Other Users',
        'Translate to Swahili',
        'Ask AI',
        'Print & Export',
        'Deletion Requests',
        'User Types and Permissions',
        'Administration',
        'Copyright',
    ]);
});

test('switching to Swahili shows Swahili content', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->assertSee('Kutazama Masomo')
        ->assertSee('Aina za Watumiaji na Ruhusa');

    $viewingLessons = collect($component->instance()->sections())
        ->firstWhere('title', 'Kutazama Masomo');

    expect($viewingLessons['body'])
        ->toContain('Vinjari masomo kutoka menyu ya **Masomo** na ubofye somo ili kulifungua.')
        ->toContain('alama ya tiki ya kijani');
});

test('guide includes the updated editing, messaging, and permissions text in English', function () {
    $this->actingAs(makeSiteAdmin());

    $sections = collect(Livewire::test(Guide::class)->instance()->sections())->keyBy('title');

    expect($sections['Editing Lessons']['body'])->toContain('Make your edits in the edit window.');
    expect($sections['Messaging Other Users']['body'])->toContain('Messages to you appear in your **Inbox**');
    expect($sections['Translate to Swahili']['body'])->toContain('click the **Translate to Swahili** button');
    expect($sections['Ask AI']['body'])->toContain('ask AI to suggest improvements');
    expect($sections['User Types and Permissions']['body'])
        ->toContain('translate to Swahili')
        ->toContain('promote teachers to be editors')
        ->not->toContain('System users are internal accounts');

    expect($sections['Copyright']['body'])
        ->toContain('[ARES Education](https://areseducation.org)')
        ->toContain('[Attribution-ShareAlike 4.0 International](https://creativecommons.org/licenses/by-sa/4.0/deed.en)')
        ->toContain('[appropriate credit](https://creativecommons.org/licenses/by-sa/4.0/deed.en#ref-appropriate-credit)')
        ->toContain('[indicate if changes were made](https://creativecommons.org/licenses/by-sa/4.0/deed.en#ref-indicate-changes)')
        ->toContain('[same license](https://creativecommons.org/licenses/by-sa/4.0/deed.en#ref-same-license)')
        ->toContain('[technological measures](https://creativecommons.org/licenses/by-sa/4.0/deed.en#ref-technological-measures)')
        ->toContain('[exception or limitation](https://creativecommons.org/licenses/by-sa/4.0/deed.en#ref-exception-or-limitation)')
        ->toContain('[publicity, privacy, or moral rights](https://creativecommons.org/licenses/by-sa/4.0/deed.en#ref-publicity-privacy-or-moral-rights)');
});

test('guide includes the updated editing, messaging, and permissions text in Swahili', function () {
    $this->actingAs(makeSiteAdmin());

    $sections = collect(Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->instance()
        ->sections())->keyBy('title');

    expect($sections['Kuhariri Masomo']['body'])->toContain('Fanya mabadiliko yako kwenye dirisha la kuhariri.');
    expect($sections['Ujumbe kwa Watumiaji Wengine']['body'])->toContain('Ujumbe unaokujia unaonekana kwenye **Kisanduku cha Barua**');
    expect($sections['Tafsiri kwa Kiswahili']['body'])->toContain('**Translate to Swahili**');
    expect($sections['Ask AI']['body'])->toContain('unaweza kuuliza AI');
    expect($sections['Aina za Watumiaji na Ruhusa']['body'])
        ->toContain('kutafsiri kwa Kiswahili')
        ->toContain('kuwapa walimu jukumu la kuwa wahariri')
        ->not->toContain('Watumiaji wa mfumo ni akaunti za ndani');

    expect($sections['Copyright']['body'])
        ->toContain('These Lesson Plans are developed by')
        ->toContain('[ARES Education](https://areseducation.org)');
});

test('guide shows the swahili sections in the new order', function () {
    $this->actingAs(makeSiteAdmin());

    $sections = Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->instance()
        ->sections();

    expect(array_column($sections, 'title'))->toBe([
        'Kutazama Masomo',
        'Kuhariri Masomo',
        'Kulinganisha Matoleo',
        'Matoleo Rasmi',
        'Vipendwa',
        'Ujumbe kwa Watumiaji Wengine',
        'Tafsiri kwa Kiswahili',
        'Ask AI',
        'Chapisha na Hamisha',
        'Maombi ya Kufuta',
        'Aina za Watumiaji na Ruhusa',
        'Utawala',
        'Copyright',
    ]);
});

test('switching back to English restores English content', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->call('switchLanguage', 'en')
        ->assertSet('language', 'en')
        ->assertSee('Viewing Lessons');
});

test('teacher sees the new user types section', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);

    $titles = array_column($component->instance()->sections(), 'title');

    expect($titles)->toContain('User Types and Permissions');
    expect($titles)->toContain('Translate to Swahili');
    expect($titles)->toContain('Copyright');
    expect($titles)->not->toContain('Editing Lessons');
    expect($titles)->not->toContain('Ask AI');
    expect($titles)->not->toContain('Official Versions');
});

test('site administrator sees admin-only sections', function () {
    $admin = makeSiteAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $titles = array_column($sections, 'title');

    expect($titles)->toContain('Administration');
    expect(array_slice($titles, -2))->toBe(['Administration', 'Copyright']);
});

test('site administrator sees the canonical orientation text', function () {
    $admin = makeSiteAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(Guide::class);

    expect($component->instance()->orientationText())->toContain('Site Administrator');
});

test('editor sees editing sections', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $this->actingAs($editor);

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $titles = array_column($sections, 'title');

    expect($titles)->toContain('Editing Lessons');
    expect($titles)->toContain('Ask AI');
});

test('teacher does not see admin-only sections', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $titles = array_column($sections, 'title');

    expect($titles)->not->toContain('Administration');
});

test('switchLanguage ignores invalid language codes', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class)
        ->call('switchLanguage', 'fr');

    expect($component->get('language'))->toBe('en');
});

test('manual download url follows the selected guide language', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);

    expect($component->instance()->manualDownloadUrl())
        ->toBe(route('guide.manual.download', ['lang' => 'en']));

    $component->call('switchLanguage', 'sw');

    expect($component->instance()->manualDownloadUrl())
        ->toBe(route('guide.manual.download', ['lang' => 'sw']));
});
