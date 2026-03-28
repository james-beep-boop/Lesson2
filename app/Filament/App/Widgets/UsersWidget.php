<?php

namespace App\Filament\App\Widgets;

use App\Models\Message;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        'editor' => 'Editor',
                        'subject_admin' => 'Subject Admin',
                        'site_admin' => 'Site Admin',
                    ])
                    ->state(fn (User $record): string => match ($record->role_label) {
                        'Administrator' => 'site_admin',
                        'Subject Admin' => 'subject_admin',
                        'Editor' => 'editor',
                        default => 'user',
                    })
                    ->updateStateUsing(fn (User $record, string $state) => $this->applyRoleChange($record, $state))
                    ->disabled(fn (User $record): bool => $record->id === auth()->id()),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->recordActions([
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
                    }),
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

        match ($state) {
            'site_admin' => $this->promoteToSiteAdmin($record),
            'user' => $this->demoteToUser($record),
            // Editor and Subject Admin are per-subject-grade assignments and cannot
            // be set from this global dropdown — inform the admin instead.
            default => Notification::make('scoped-role-notice')
                ->title('Editor and Subject Admin roles are managed per subject-grade, not globally.')
                ->info()
                ->send(),
        };

        $this->resetTable();
    }

    private function promoteToSiteAdmin(User $record): void
    {
        if ($record->isSiteAdmin()) {
            return;
        }

        $record->assignRole('site_administrator');

        Notification::make('role-updated')
            ->title('Status updated to Site Admin.')
            ->success()
            ->send();
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

            $record->removeRole('site_administrator');
        }

        // Revoke all subject_grade_user pivot entries (editor roles).
        $record->subjectGrades()->detach();

        // Clear subject_admin_user_id on all subject_grades this user owned.
        SubjectGrade::where('subject_admin_user_id', $record->id)
            ->update(['subject_admin_user_id' => null]);

        Notification::make('role-updated')
            ->title('Status updated to User.')
            ->success()
            ->send();
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
