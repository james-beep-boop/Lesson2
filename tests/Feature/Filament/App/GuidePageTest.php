<?php

use App\Filament\App\Pages\Guide;
use Filament\Facades\Filament;
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

test('switching to Swahili shows Swahili content', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->assertSee('Kutazama Masomo');
});

test('switching back to English restores English content', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(Guide::class)
        ->call('switchLanguage', 'sw')
        ->call('switchLanguage', 'en')
        ->assertSet('language', 'en')
        ->assertSee('Viewing Lessons');
});

test('teacher sees base sections only', function () {
    $this->actingAs(makeTeacher());

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $roles = array_column($sections, 'roles');

    // All returned sections should be visible to all (roles=null)
    foreach ($sections as $section) {
        expect($section['roles'])->toBeNull();
    }
});

test('site administrator sees admin-only sections', function () {
    $admin = makeSiteAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $titles = array_column($sections, 'title');

    expect($titles)->toContain('Administration');
});

test('editor sees editing sections', function () {
    $sg = makeSubjectGrade();
    $editor = makeEditor($sg);
    $this->actingAs($editor);

    $component = Livewire::test(Guide::class);
    $sections = $component->instance()->sections();
    $titles = array_column($sections, 'title');

    // Editor should see the editing section
    expect($titles)->toContain('Editing & Saving a New Version');
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
