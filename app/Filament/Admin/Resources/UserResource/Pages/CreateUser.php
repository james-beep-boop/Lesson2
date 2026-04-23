<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Extend the resource form with a password field.
     * The User model's 'hashed' cast on password means we pass plain text here.
     * Admin-created accounts are auto-verified — no email confirmation needed.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('username')
                ->required()
                ->unique(User::class, 'username'),
            TextInput::make('name')
                ->required(),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(User::class, 'email'),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->required()
                ->minLength(8)
                ->helperText('Minimum 8 characters. The user can change this after logging in.'),
            Checkbox::make('is_site_admin')
                ->label('Make Site Administrator')
                ->helperText('Gives this user full administrative access immediately after creation.')
                ->default(false),
        ]);
    }

    /**
     * Auto-verify email — site admin controls who gets accounts,
     * so email verification is not required for admin-created users.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $isSiteAdmin = (bool) ($data['is_site_admin'] ?? false);

        unset($data['is_site_admin']);

        $user = User::create([
            ...$data,
            'email_verified_at' => now(),
        ]);

        if ($isSiteAdmin) {
            $user->assignRole('site_administrator');
        }

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'User created.';
    }
}
