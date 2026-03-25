<?php

namespace App\Filament\App\Resources\MessageResource\Pages;

use App\Filament\App\Resources\MessageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    /**
     * Custom blade view — bypasses default infolist rendering while still
     * using ViewRecord for correct route binding and canView() authorization.
     * ViewRecord also scopes the lookup through getEloquentQuery() so a user
     * cannot access another user's message by guessing its ID.
     */
    protected string $view = 'filament.app.pages.view-message';

    public function mount(int|string $record): void
    {
        // Resolves record through the scoped query and runs canView() check.
        parent::mount($record);

        // Mark as read the first time the message is opened.
        if (! $this->record->isRead()) {
            $this->record->read_at = now();
            $this->record->save();
            $this->record->refresh();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(MessageResource::getUrl('compose', [
                    'to' => $this->record->from_user_id,
                    'subject' => 'Re: '.$this->record->subject,
                ])),
            Action::make('back')
                ->label('Back to Inbox')
                ->icon('heroicon-o-arrow-left')
                ->url(MessageResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->subject;
    }
}
