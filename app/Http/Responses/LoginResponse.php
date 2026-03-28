<?php

namespace App\Http\Responses;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    /**
     * Always land on the Lesson Plans index after login, regardless of any
     * previously stored "intended" URL (e.g. from a session timeout).
     * Admin-panel logins fall back to the standard Filament home URL.
     */
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'app') {
            return redirect()->to(LessonPlanFamilyResource::getUrl('index'));
        }

        return redirect()->intended(Filament::getUrl());
    }
}
