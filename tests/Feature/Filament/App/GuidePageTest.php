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
        ->assertSee('Guide');
});

test('guide defaults to English language', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);

    expect($component->get('language'))->toBe('en');
});

test('guide shows English content by default', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(Guide::class)
        ->assertSee('Viewing Lessons');
});

test('guide uses full content width', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);

    expect($component->instance()->getMaxContentWidth())->toBe(Width::Full);
});

test('guide shows the english sections in the new order', function () {
    $this->actingAs(makeTeacher());

    $sections = Livewire::test(Guide::class)->instance()->sections();

    expect(array_column($sections, 'title'))->toBe([
        'Viewing Lessons',
        'User Types and Permissions',
        'Comparing Versions',
        'Favorites',
        'Messaging',
        'Print & Export',
    ]);
});

test('switching to Swahili shows Swahili content', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->assertSee('Kutazama Masomo')
        ->assertSee('Aina za Watumiaji na Ruhusa');
});

test('guide shows the swahili sections in the new order', function () {
    $this->actingAs(makeTeacher());

    $sections = Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->instance()
        ->sections();

    expect(array_column($sections, 'title'))->toBe([
        'Kutazama Masomo',
        'Aina za Watumiaji na Ruhusa',
        'Kulinganisha Matoleo',
        'Vipendwa',
        'Ujumbe',
        'Chapisha na Hamisha',
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

    expect(array_column($component->instance()->sections(), 'title'))
        ->toContain('User Types and Permissions');
    expect(array_column($component->instance()->sections(), 'title'))
        ->not->toContain('Editing Lessons');
});

test('site administrator sees admin-only sections', function () {
    $admin = makeSiteAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $titles = array_column($sections, 'title');

    expect($titles)->toContain('Administration');
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
