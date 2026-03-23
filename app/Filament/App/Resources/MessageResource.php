<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\MessageResource\Pages;
use App\Models\Message;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationLabel = 'Inbox';
    protected static ?string $label = 'Message';
    protected static ?int $navigationSort = 2;

    /**
     * Scope to messages received by the current user only.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('to_user_id', auth()->id())
            ->with('fromUser')
            ->latest();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('unread')
                    ->label('')
                    ->state(fn (Message $record) => ! $record->isRead())
                    ->boolean()
                    ->trueIcon('heroicon-s-envelope')
                    ->falseIcon('heroicon-o-envelope-open')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->width('2rem'),
                TextColumn::make('fromUser.name')
                    ->label('From')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->weight(fn (Message $record) => $record->isRead() ? null : 'bold'),
                TextColumn::make('body')
                    ->label('Preview')
                    ->limit(60)
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Message $record): string => static::getUrl('view', ['record' => $record->id]))
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading('Your inbox is empty')
            ->emptyStateDescription('Messages from other users will appear here.');
    }

    /**
     * Unread count badge shown on the navigation item.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Message::where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListMessages::route('/'),
            'compose' => Pages\ComposeMessage::route('/compose'),
            'view'    => Pages\ViewMessage::route('/{record}'),
        ];
    }

    /** No policy needed — query is always scoped to auth user. */
    public static function canCreate(): bool
    {
        return (bool) auth()->id();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->to_user_id === auth()->id();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
