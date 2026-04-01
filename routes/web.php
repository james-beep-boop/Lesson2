<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/auth/close', function () {
    Auth::logout();
    request()->session()->invalidate();

    return response()->noContent();
})->middleware(['auth'])->name('auth.close');

// Filament app panel handles the root path.
