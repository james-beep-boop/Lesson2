<?php

namespace App\Filament\App\Widgets;

use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class UsersWidget extends TableWidget
{
    /**
     * Enforce site-admin access at mount time.
     * Widgets are standalone Livewire components; their methods are reachable
     * via HTTP independently of the parent page's abort_unless guard.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);
    }

    /** Return empty string so TableWidget::makeTable() sets no visible heading. */
    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::where('is_system', false)->orderBy('name'))
            ->queryStringIdentifier('users')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->id === auth()->id() ? '(you)' : null),
                SelectColumn::make('site_role')
                    ->label('Status')
                    ->options([
                        'user' => 'User',
                        'administrator' => 'Administrator',
                    ])
                    ->state(fn (User $record): string => $record->isSiteAdmin() ? 'administrator' : 'user')
                    ->updateStateUsing(fn (User $record, string $state) => $this->applyRoleChange($record, $state))
                    ->disabled(fn (User $record): bool => $record->id === auth()->id()),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(fn (Collection $records) => $this->deleteUsers($records))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    // -------------------------------------------------------------------------
    // Status column inline update
    // -------------------------------------------------------------------------

    private function applyRoleChange(User $record, string $state): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        // Self-modification guard.
        if ($record->id === auth()->id()) {
            Notification::make('cannot-self-change')
                ->title('You cannot change your own administrator status.')
                ->warning()
                ->send();

            return;
        }

        $shouldBeAdmin = $state === 'administrator';

        // No-op if already in the desired state.
        if ($record->isSiteAdmin() === $shouldBeAdmin) {
            return;
        }

        if (! $shouldBeAdmin) {
            // Last-admin guard: refuse if this would remove the only site admin.
            $remainingAdmins = User::role('site_administrator')
                ->where('is_system', false)
                ->where('id', '!=', $record->id)
                ->exists();

            if (! $remainingAdmins) {
                Notification::make('last-admin')
                    ->title('Cannot remove the last Site Administrator.')
                    ->danger()
                    ->send();

                return;
            }

            $record->removeRole('site_administrator');
        } else {
            // assignRole is additive — does not strip unrelated Spatie roles.
            $record->assignRole('site_administrator');
        }

        $label = $shouldBeAdmin ? 'Administrator' : 'User';

        Notification::make('role-updated')
            ->title("Status updated to {$label}.")
            ->success()
            ->send();

        $this->resetTable();
    }

    // -------------------------------------------------------------------------
    // Bulk delete
    // -------------------------------------------------------------------------

    private function deleteUsers(Collection $records): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        $currentUserId = auth()->id();

        $toDelete = $records->reject(fn (User $user) => $user->id === $currentUserId);

        if ($toDelete->isEmpty()) {
            Notification::make('cannot-self-delete')
                ->title('You cannot delete your own account.')
                ->warning()
                ->send();

            return;
        }

        $count = $toDelete->count();
        $toDelete->each->delete();

        Notification::make('users-deleted')
            ->title('Deleted '.$count.' '.str('user')->plural($count).'.')
            ->success()
            ->send();
    }
}
