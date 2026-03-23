<?php

namespace App\Filament\Admin\Resources\DeletionRequestResource\Pages;

use App\Filament\Admin\Resources\DeletionRequestResource;
use App\Models\DeletionRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDeletionRequests extends ListRecords
{
    protected static string $resource = DeletionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $pending  = DeletionRequest::whereNull('resolved_at')->count();
        $resolved = DeletionRequest::whereNotNull('resolved_at')->count();

        return [
            'all' => Tab::make('All'),

            'pending' => Tab::make("Pending ({$pending})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('resolved_at')),

            'resolved' => Tab::make("Resolved ({$resolved})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('resolved_at')),
        ];
    }
}
