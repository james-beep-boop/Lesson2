<?php

namespace App\Filament\App\Resources\MessageResource\Pages;

use App\Filament\App\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ComposeMessage extends CreateRecord
{
    protected static string $resource = MessageResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        // Pre-fill To/Subject from query params (used by Reply)
        $defaultTo = request()->query('to');
        $defaultSubject = request()->query('subject');

        return $schema->schema([
            Select::make('to_user_id')
                ->label('To')
                ->options(fn () => User::where('is_system', false)
                    ->where('id', '!=', auth()->id())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                )
                ->default($defaultTo ? (int) $defaultTo : null)
                ->searchable()
                ->required(),
            TextInput::make('subject')
                ->label('Subject')
                ->default($defaultSubject)
                ->required()
                ->maxLength(255),
            Textarea::make('body')
                ->label('Message')
                ->required()
                ->rows(8),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $message = new Message([
            'to_user_id' => $data['to_user_id'],
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);
        $message->from_user_id = auth()->id();
        $message->save();

        return $message;
    }

    protected function getRedirectUrl(): string
    {
        return MessageResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Message sent.';
    }

    public function getTitle(): string
    {
        return 'Compose Message';
    }
}
