<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'People';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('username')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->required(fn ($record) => $record === null)
                ->dehydrated(fn ($state) => filled($state))
                ->helperText(fn ($record) => $record ? 'Leave blank to keep the current password.' : null)
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('is_system', false)->with('roles'))
            ->columns([
                TextColumn::make('username')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role_label')
                    ->label('Role')
                    ->state(fn (User $record): string => $record->getRoleLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Administrator'  => 'danger',
                        'Subject Admin'  => 'warning',
                        'Editor'         => 'info',
                        default          => 'gray',
                    }),
                TextColumn::make('email_verified_at')->dateTime()->label('Verified')->sortable(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('grantSiteAdmin')
                    ->label('Grant Site Admin')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Grant Site Administrator role?')
                    ->modalDescription(fn (User $record) => "This will give {$record->name} full administrative access to the system.")
                    ->action(function (User $record): void {
                        $record->assignRole('site_administrator');
                        Notification::make('site-admin-granted')
                            ->title('Site Admin role granted.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (User $record): bool => ! $record->isSiteAdmin()),
                Action::make('revokeSiteAdmin')
                    ->label('Revoke Site Admin')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Site Administrator role?')
                    ->modalDescription(fn (User $record) => "This will remove {$record->name}'s administrative access.")
                    ->action(function (User $record): void {
                        $record->removeRole('site_administrator');
                        Notification::make('site-admin-revoked')
                            ->title('Site Admin role revoked.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (User $record): bool => $record->isSiteAdmin()),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSiteAdmin() ?? false;
    }
}
