<?php

namespace App\Filament\App\Pages;

use App\Models\SubjectGrade;
use Filament\Auth\Pages\EditProfile;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Password;

class Profile extends EditProfile
{
    public bool $editing = false;
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

    /** Human-readable role label for this user. */
    public function getRoleLabel(): string
    {
        $user = $this->getUser();

        if ($user->hasRole('site_administrator')) {
            return 'Site Administrator';
        }

        $asSubjectAdmin = SubjectGrade::where('subject_admin_user_id', $user->id)
            ->with('subject')
            ->get();

        if ($asSubjectAdmin->isNotEmpty()) {
            return $asSubjectAdmin
                ->map(fn ($sg) => 'Subject Administrator — ' . $sg->subject->name . ' Grade ' . $sg->grade)
                ->join(', ');
        }

        $asEditor = $user->subjectGrades()->with('subject')->get();

        if ($asEditor->isNotEmpty()) {
            return $asEditor
                ->map(fn ($sg) => 'Editor — ' . $sg->subject->name . ' Grade ' . $sg->grade)
                ->join(', ');
        }

        return 'Teacher';
    }
}
