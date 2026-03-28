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
use Illuminate\Support\Facades\DB;

class UsersWidget extends TableWidget
{
    /**
     * Tracks pending role changes per user ID.
     * Populated when the user changes the Status select; cleared after confirm.
     *
     * @var array<int, string>
     */
    public array $pendingRoleChanges = [];

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
                SelectColumn::make('role_key')
                    ->label('Status')
                    ->options([
                        'user' => 'User',
                        'editor' => 'Editor',
                        'subject_admin' => 'Subject Admin',
                        'site_admin' => 'Site Admin',
                    ])
                    ->getStateUsing(fn (User $record): string => $this->pendingRoleChanges[$record->id] ?? $this->roleKey($record))
                    ->updateStateUsing(function (User $record, string $state): void {
                        $pending = $this->pendingRoleChanges;
                        if ($state === $this->roleKey($record)) {
                            unset($pending[$record->id]);
                        } else {
                            $pending[$record->id] = $state;
                        }
                        $this->pendingRoleChanges = $pending;
                    })
                    ->disabled(fn (User $record): bool => $record->id === auth()->id()),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('confirmRole')
                    ->label('Confirm')
                    ->button()
                    ->size('xs')
                    ->color(fn (User $record): string => isset($this->pendingRoleChanges[$record->id]) ? 'primary' : 'gray')
                    ->disabled(fn (User $record): bool => ! isset($this->pendingRoleChanges[$record->id]))
                    ->action(function (User $record): void {
                        $newRole = $this->pendingRoleChanges[$record->id];
                        $this->applyRoleChange($record, $newRole);
                        $pending = $this->pendingRoleChanges;
                        unset($pending[$record->id]);
                        $this->pendingRoleChanges = $pending;
                    })
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
    // Role key helper
    // -------------------------------------------------------------------------

    /**
     * Map a User record to its canonical role key for the Status select.
     * Subject Admin and Editor can only be assigned via subject-grade context
     * (Team Management), so they appear in the select for display only.
     */
    private function roleKey(User $record): string
    {
        return match ($record->role_label) {
            'Administrator' => 'site_admin',
            'Subject Admin' => 'subject_admin',
            'Editor' => 'editor',
            default => 'user',
        };
    }

    // -------------------------------------------------------------------------
    // Role change — confirm action
    // -------------------------------------------------------------------------

    private function applyRoleChange(User $record, string $state): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        match ($state) {
            'site_admin' => $this->promoteToSiteAdmin($record),
            'user' => $this->demoteToUser($record),
            // Editor and Subject Admin are subject-grade-scoped; assign via Team Management.
            default => Notification::make('scoped-role-required')
                ->title(($state === 'editor' ? 'Editor' : 'Subject Admin').' is a subject-grade role. Assign it via Team Management.')
                ->warning()
                ->send(),
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
