<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

// Filament app panel handles the root path.

/**
 * Tab-guard logout — called via JS redirect when sessionStorage marker is absent,
 * indicating the tab/browser was closed and reopened. We invalidate the session
 * server-side so the user must log in again.
 */
Route::get('/tab-guard-logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->to(Filament::getPanel('app')->getLoginUrl());
})->middleware(['web'])->name('tab-guard-logout');
