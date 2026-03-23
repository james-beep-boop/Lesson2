<?php

namespace App\Filament\App\Resources\MessageResource\Pages;

use App\Filament\App\Resources\MessageResource;
use App\Models\Message;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ViewMessage extends Page
{
    protected static string $resource = MessageResource::class;
    protected string $view = 'filament.app.pages.view-message';

    public Message $record;

    public function mount(int|string $record): void
    {
        $this->record = Message::with('fromUser')->findOrFail($record);

        // Authorization — only the recipient may view
        abort_unless($this->record->to_user_id === auth()->id(), 403);

        // Mark as read the first time it is opened
        if (! $this->record->isRead()) {
            $this->record->update(['read_at' => now()]);
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
                    'to'      => $this->record->from_user_id,
                    'subject' => 'Re: ' . $this->record->subject,
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
