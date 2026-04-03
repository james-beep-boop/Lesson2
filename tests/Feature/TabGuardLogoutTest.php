<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

// ----------------------------------------------------------------
// POST /tab-guard-logout — unauthenticated
// ----------------------------------------------------------------

test('tab-guard-logout redirects guest to login', function () {
    $response = $this->post(route('tab-guard-logout'));

    $response->assertRedirect(route('filament.app.auth.login'));
});

// ----------------------------------------------------------------
// POST /tab-guard-logout — authenticated user
// ----------------------------------------------------------------

test('tab-guard-logout logs out an authenticated user', function () {
    $user = makeTeacher();

    $this->actingAs($user);
    expect(auth()->check())->toBeTrue();

    $this->post(route('tab-guard-logout'));

    expect(auth()->check())->toBeFalse();
});

test('tab-guard-logout redirects authenticated user to login', function () {
    $user = makeTeacher();

    $response = $this->actingAs($user)->post(route('tab-guard-logout'));

    $response->assertRedirect(route('filament.app.auth.login'));
});

test('tab-guard-logout invalidates session', function () {
    $user = makeTeacher();

    $this->actingAs($user);
    $sessionIdBefore = session()->getId();

    $this->post(route('tab-guard-logout'));

    // After invalidation the session ID must be different
    expect(session()->getId())->not->toBe($sessionIdBefore);
});

// ----------------------------------------------------------------
// Site admin is also logged out by the tab guard
// ----------------------------------------------------------------

test('tab-guard-logout logs out a site admin', function () {
    $admin = makeSiteAdmin();

    $this->actingAs($admin);
    expect(auth()->check())->toBeTrue();

    $this->post(route('tab-guard-logout'));

    expect(auth()->check())->toBeFalse();
});
