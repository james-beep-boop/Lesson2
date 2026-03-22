<?php

use App\Models\User;
use Filament\Panel;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

// ----------------------------------------------------------------
// Registration / Verification status
// ----------------------------------------------------------------

test('registered users start with unverified email', function () {
    $user = User::factory()->unverified()->create();

    expect($user->email_verified_at)->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('unverified users cannot access app panel', function () {
    $user = User::factory()->unverified()->create();

    $panel = Panel::make()->id('app')->default();
    expect($user->canAccessPanel($panel))->toBeFalse();
});

test('verified users can access app panel', function () {
    $user = User::factory()->create(); // verified by default

    $panel = Panel::make()->id('app')->default();
    expect($user->canAccessPanel($panel))->toBeTrue();
});

// ----------------------------------------------------------------
// Email Verification
// ----------------------------------------------------------------

test('email verification sets email_verified_at', function () {
    $user = User::factory()->unverified()->create();
    expect($user->hasVerifiedEmail())->toBeFalse();

    $user->markEmailAsVerified();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

// ----------------------------------------------------------------
// System user
// ----------------------------------------------------------------

test('system user cannot access app panel', function () {
    $systemUser = User::factory()->system()->create();

    $panel = Panel::make()->id('app')->default();
    expect($systemUser->canAccessPanel($panel))->toBeFalse();
});

test('system user does not appear in non-system user query', function () {
    User::factory()->system()->create();
    $regularUser = User::factory()->create();

    $users = User::where('is_system', false)->get();

    expect($users->pluck('id'))->not->toContain(
        User::where('is_system', true)->value('id')
    );
    expect($users->pluck('id'))->toContain($regularUser->id);
});

// ----------------------------------------------------------------
// Logout
// ----------------------------------------------------------------

test('logout clears auth state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    expect(auth()->check())->toBeTrue();

    auth()->logout();

    expect(auth()->check())->toBeFalse();
});

// ----------------------------------------------------------------
// Site Admin panel access
// ----------------------------------------------------------------

test('site admin can access admin panel', function () {
    $admin = makeSiteAdmin();

    $adminPanel = Panel::make()->id('admin');
    expect($admin->canAccessPanel($adminPanel))->toBeTrue();
});

test('regular user cannot access admin panel', function () {
    $user = makeTeacher();

    $adminPanel = Panel::make()->id('admin');
    expect($user->canAccessPanel($adminPanel))->toBeFalse();
});
