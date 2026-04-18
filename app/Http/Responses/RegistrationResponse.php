<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class RegistrationResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('app');

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return redirect()->to($panel?->getEmailVerificationPromptUrl() ?? $panel?->getLoginUrl() ?? url('/'));
        }

        return redirect()->intended($panel?->getUrl() ?? Filament::getUrl());
    }
}
