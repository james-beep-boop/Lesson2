<?php

namespace App\Filament\App\Pages;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

/**
 * Custom registration page that adds the required `username` field.
 * New registrants are plain teachers — no role assignment needed since
 * Teacher is the default state (no Spatie role attached).
 */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('username')
                ->label('Username')
                ->required()
                ->maxLength(255)
                ->unique('users', 'username')
                ->regex('/^[a-z0-9_.\-]+$/i')
                ->helperText('Letters, numbers, underscores, hyphens and dots only.')
                ->autofocus(),
            TextInput::make('name')
                ->label('Full name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email address')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique('users', 'email'),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->required()
                ->revealable()
                ->rules([Password::min(8)]),
            TextInput::make('passwordConfirmation')
                ->label('Confirm password')
                ->password()
                ->required()
                ->revealable()
                ->same('password')
                ->dehydrated(false),
        ]);
    }

    /**
     * Strip passwordConfirmation, auto-verify email, then create the user.
     * Auto-verification is intentional: DreamHost email delivery is unreliable
     * and the school controls who receives the registration URL. The same
     * pattern is used in CreateUser for admin-created accounts.
     *
     * Teacher behaviour = no Spatie role. Any authenticated user without a
     * site_administrator role or subject_grade_user pivot entry is treated as a
     * plain teacher by all policies — no separate role assignment is needed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        unset($data['passwordConfirmation']);

        $data['email_verified_at'] = now();

        return $this->getUserModel()::create($data);
    }
}
