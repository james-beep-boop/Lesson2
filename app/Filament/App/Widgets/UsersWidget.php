<?php

namespace App\Filament\App\Widgets;

use App\Filament\Admin\Resources\SubjectGradeResource;
use App\Models\Message;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            ->query(fn () => User::where('is_system', false)->with(['roles', 'subjectGradesAsAdmin', 'subjectGrades'])->orderBy('name'))
            ->queryStringIdentifier('users')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->id === auth()->id() ? '(you)' : null),
                TextColumn::make('role_label')
                    ->label('Role')
                    ->state(fn (User $record): string => $record->role_label)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Site Administrator' => 'danger',
                        'Subject Administrator' => 'warning',
                        'Editor' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('scoped_assignments')
                    ->label('Assignments')
                    ->state(fn (User $record): string => $record->scopedAssignmentSummary())
                    ->wrap(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('grantSiteAdmin')
                    ->label('Grant Site Admin')
                    ->button()
                    ->size('xs')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Grant Site Administrator role?')
                    ->modalDescription(fn (User $record): string => "This will give {$record->name} full administrative access to the system.")
                    ->action(function (User $record): void {
                        $this->grantSiteAdmin($record);
                    })
                    ->visible(fn (User $record): bool => ! $record->isSiteAdmin()),

                Action::make('revokeSiteAdmin')
                    ->label('Revoke Site Admin')
                    ->button()
                    ->size('xs')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Site Administrator role?')
                    ->modalDescription(fn (User $record): string => "This will remove {$record->name}'s full administrative access. Subject-grade assignments are unchanged.")
                    ->action(function (User $record): void {
                        $this->revokeSiteAdmin($record);
                    })
                    ->visible(fn (User $record): bool => $record->isSiteAdmin() && $record->id !== auth()->id()),

                Action::make('manageAssignments')
                    ->label('Manage Assignments')
                    ->button()
                    ->size('xs')
                    ->color('gray')
                    ->url(fn (): string => SubjectGradeResource::getUrl('index', panel: 'admin')),

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
                    ->deselectRecordsAfterCompletion()
                    ->extraAttributes(['x-show' => '1']),
            ]);
    }

    private function grantSiteAdmin(User $record): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        $record->assignRole('site_administrator');

        Notification::make('role-updated')
            ->title('Status updated to Site Administrator.')
            ->success()
            ->send();

        $this->resetTable();
    }

    private function revokeSiteAdmin(User $record): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        if ($record->id === auth()->id()) {
            Notification::make('self-revoke-blocked')
                ->title('You cannot revoke your own Site Administrator role.')
                ->danger()
                ->send();

            return;
        }

        if (User::isLastSiteAdmin($record)) {
            Notification::make('last-admin')
                ->title('Cannot remove the last Site Administrator.')
                ->danger()
                ->send();

            return;
        }

        $record->removeRole('site_administrator');

        Notification::make('role-updated')
            ->title('Site Administrator role revoked.')
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
