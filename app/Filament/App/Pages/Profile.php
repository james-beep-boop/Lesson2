<?php

namespace App\Filament\App\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Password;

class Profile extends EditProfile
{
    public bool $editing = false;

    private ?string $cachedRoleLabel = null;

    public string $editName = '';

    public string $editPassword = '';

    public string $editPasswordConfirmation = '';

    protected string $view = 'filament.app.pages.profile';

    public static function isSimple(): bool
    {
        return false;
    }

    public function mount(): void
    {
        // Skip parent fillForm() — we use our own Livewire properties,
        // not the Filament schema form. Initialize $data to satisfy parent.
        $this->data = [];
    }

    public function startEditing(): void
    {
        $this->editName = $this->getUser()->name;
        $this->editPassword = '';
        $this->editPasswordConfirmation = '';
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->editPassword = '';
        $this->editPasswordConfirmation = '';
    }

    public function saveProfile(): void
    {
        $rules = [
            'editName' => ['required', 'string', 'max:255'],
            'editPassword' => ['nullable', 'confirmed'],
            'editPasswordConfirmation' => ['nullable'],
        ];

        if (filled($this->editPassword)) {
            $rules['editPassword'][] = Password::defaults();
        }

        $this->validate($rules, [], [
            'editName' => 'name',
            'editPassword' => 'password',
            'editPasswordConfirmation' => 'password confirmation',
        ]);

        $user = $this->getUser();
        $data = ['name' => $this->editName];

        if (filled($this->editPassword)) {
            $data['password'] = $this->editPassword; // 'hashed' cast applies automatically
        }

        $user->update($data);

        $this->editing = false;
        $this->editPassword = '';
        $this->editPasswordConfirmation = '';

        Notification::make('profile-saved')->title('Profile updated.')->success()->send();
    }

    /** Human-readable role label for this user. Cached per Livewire request. */
    public function getRoleLabel(): string
    {
        return $this->cachedRoleLabel ??= $this->getUser()
            ->loadMissing(['subjectGradesAsAdmin.subject', 'subjectGrades.subject'])
            ->detailed_role_label;
    }
}
