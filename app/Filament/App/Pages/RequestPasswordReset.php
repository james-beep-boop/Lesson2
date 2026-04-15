<?php

namespace App\Filament\App\Pages;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;

/**
 * Overrides the base password-reset request page with two fixes:
 *
 * 1. Generic response regardless of outcome — prevents user enumeration by
 *    ensuring valid and invalid email addresses produce identical UI feedback.
 *
 * 2. Notification visibility — Filament 5's Notification::send() writes to the
 *    PHP session; the notifications component only reads it on mount() or when
 *    it receives a 'notificationsSent' Livewire event. The base page calls
 *    $this->form->fill() for the success case which can interfere with
 *    notification display; by overriding both notification methods we ensure
 *    the same notification fires via the failure path (which skips form->fill())
 *    when the email is invalid, and via the success path (with the dispatch)
 *    when it is valid.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        parent::request();

        $this->dispatch('notificationsSent');
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title('Reset link sent')
            ->body('If an account exists for that email address, a password reset link has been sent. Check your inbox.')
            ->success();
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title('Reset link sent')
            ->body('If an account exists for that email address, a password reset link has been sent. Check your inbox.')
            ->success();
    }
}
