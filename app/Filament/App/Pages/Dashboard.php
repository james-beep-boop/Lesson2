<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;

/**
 * The app panel has no meaningful dashboard content — the lesson list IS the
 * home screen. Redirect automatically so users land on something useful.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    public function mount(): void
    {
        $this->redirect(LessonPlanFamilyResource::getUrl('index'));
    }
}
