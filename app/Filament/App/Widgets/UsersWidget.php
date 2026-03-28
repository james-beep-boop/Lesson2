<?php

namespace App\Filament\App\Widgets;

use App\Models\Message;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
                TextColumn::make('role_display')
                    ->label('Status')
                    ->state(fn (User $record): string => match ($record->role_label) {
                        'Administrator' => 'Site Admin',
                        'Subject Admin' => 'Subject Admin',
                        'Editor' => 'Editor',
                        default => 'User',
                    })
                    ->badge()
                    ->color(fn (User $record): string => match ($record->role_label) {
                        'Administrator' => 'success',
                        'Subject Admin' => 'warning',
                        'Editor' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change Role')
                    ->button()
                    ->size('xs')
                    ->color('gray')
                    ->modalHeading(fn (User $record): string => 'Change role for '.$record->name)
                    ->modalDescription('Changing a user to "User" permanently removes all their Editor and Subject Admin assignments across all subject-grades.')
                    ->modalSubmitActionLabel('Change Role')
                    ->schema([
                        Select::make('new_role')
                            ->label('New role')
                            ->options([
                                'user' => 'User',
                                'site_admin' => 'Site Admin',
                            ])
                            ->required(),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'new_role' => $record->isSiteAdmin() ? 'site_admin' : 'user',
                    ])
                    ->action(function (User $record, array $data): void {
                        $this->applyRoleChange($record, $data['new_role']);
                    })
                    // Hidden for the current logged-in admin — cannot change own role.
                    ->hidden(fn (User $record): bool => $record->id === auth()->id()),

                Action::make('message')
                    ->label('Message')
                    ->button()
                    ->size('xs')
                    ->modalHeading(fn (User $record): string => 'Message '.$record->name)
                    ->modalSubmitActionLabel('Send')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Subject')
                            ->required(),
                        Textarea::make('body')
                            ->label('Message')
                            ->rows(4)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        abort_unless(auth()->user()?->isSiteAdmin(), 403);

                        $message = new Message([
                            'to_user_id' => $record->id,
                            'subject' => $data['subject'],
                            'body' => $data['body'],
                        ]);
                        $message->from_user_id = auth()->id();
                        $message->save();

                        Notification::make('message-sent')
                            ->title('Message sent to '.$record->name.'.')
                            ->success()
                            ->send();
                    })
                    // Hidden for the current logged-in admin — messaging yourself is meaningless.
                    ->hidden(fn (User $record): bool => $record->id === auth()->id()),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->button()
                    ->label('Delete')
                    ->color('primary')
                    ->modalHeading('Delete selected items?')
                    ->modalDescription('This cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->modalSubmitAction(fn ($action) => $action->color('danger'))
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->deleteUsers($records))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    // -------------------------------------------------------------------------
    // Role change — modal action
    // -------------------------------------------------------------------------

    private function applyRoleChange(User $record, string $state): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        match ($state) {
            'site_admin' => $this->promoteToSiteAdmin($record),
            'user' => $this->demoteToUser($record),
            default => null, // Defensive — form options are constrained to the two above.
        };
    }

    private function promoteToSiteAdmin(User $record): void
    {
        // No-op: user is already a Site Admin. Modal was filled with current state
        // and submitted unchanged. Silently ignore — no notification, no re-render.
        if ($record->isSiteAdmin()) {
            return;
        }

        $record->assignRole('site_administrator');

        Notification::make('role-updated')
            ->title('Status updated to Site Admin.')
            ->success()
            ->send();

        $this->resetTable();
    }

    private function demoteToUser(User $record): void
    {
        // Last-admin guard: refuse if this would remove the only site admin.
        if ($record->isSiteAdmin()) {
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
        }

        // Single transaction: remove Spatie role + all subject-grade pivot entries
        // + clear any subject_admin_user_id pointers. Mirrors the service-layer
        // transaction rule from CLAUDE.md for Subject Admin changes.
        DB::transaction(function () use ($record): void {
            if ($record->isSiteAdmin()) {
                $record->removeRole('site_administrator');
            }

            // Revoke all subject_grade_user pivot entries (editor roles).
            $record->subjectGrades()->detach();

            // Clear subject_admin_user_id on all subject_grades this user owned.
            SubjectGrade::where('subject_admin_user_id', $record->id)
                ->update(['subject_admin_user_id' => null]);
        });

        Notification::make('role-updated')
            ->title('Status updated to User.')
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
