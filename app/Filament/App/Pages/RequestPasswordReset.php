<?php

namespace App\Filament\App\Pages;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

/**
 * Filament 5's Notification::send() writes to the PHP session; the Notifications
 * Livewire component only reads that session key on mount() or when it receives
 * a 'notificationsSent' event. Because the password-reset form stays on the same
 * page after submit (no redirect, no mount), the event is never dispatched and the
 * toast never appears. This subclass dispatches it so the notification shows.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        parent::request();

        $this->dispatch('notificationsSent');
    }
}
