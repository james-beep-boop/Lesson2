<?php

namespace App\Filament\App\Resources\MessageResource\Pages;

use App\Filament\App\Resources\MessageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compose')
                ->label('Compose')
                ->icon('heroicon-o-pencil-square')
                ->url(MessageResource::getUrl('compose')),
        ];
    }
}
