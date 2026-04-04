<?php

use App\Http\Controllers\LessonPlanPdfController;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

// Filament app panel handles the root path.

/**
 * Tab-guard logout — triggered by a JS form POST when the sessionStorage marker is
 * absent, indicating the tab/browser was closed and reopened. Using POST (not GET)
 * prevents CSRF-based forced logout via embedded images or links.
 */
Route::post('/tab-guard-logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->to(Filament::getPanel('app')->getLoginUrl());
})->middleware(['web'])->name('tab-guard-logout');

/**
 * PDF download for a specific lesson plan version.
 * The version must belong to the given family — validated in the controller.
 */
// Auth check is done inside the controller via abort_unless().
Route::get('/lesson-pdf/{family}/{version}', [LessonPlanPdfController::class, 'download'])
    ->middleware(['web'])
    ->name('lesson-plan.pdf');
