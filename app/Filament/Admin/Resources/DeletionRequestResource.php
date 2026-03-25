<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeletionRequestResource\Pages;
use App\Models\DeletionRequest;
use App\Services\DeletionRequestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeletionRequestResource extends Resource
{
    protected static ?string $model = DeletionRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationLabel = 'Deletion Requests';

    protected static ?string $label = 'Deletion Request';

    protected static ?int $navigationSort = 4;

    /** Site Admins only. */
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->isSiteAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (DeletionRequest $r) => $r->isResolved() ? 'Resolved' : 'Pending')
                    ->badge()
                    ->color(fn (DeletionRequest $r) => $r->isResolved() ? 'success' : 'warning'),
                TextColumn::make('lesson_plan')
                    ->label('Lesson Plan')
                    ->state(fn (DeletionRequest $r) => $r->version
                        ? ($r->version->family?->subjectGrade?->subject?->name ?? '?')
                          .' — Grade '.($r->version->family?->subjectGrade?->grade ?? '?')
                          .' · Day '.($r->version->family?->day ?? '?')
                        : '(version deleted)'
                    ),
                TextColumn::make('version.version')
                    ->label('Version')
                    ->default('—'),
                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(60)
                    ->default('—'),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('resolvedBy.name')
                    ->label('Resolved By')
                    ->default('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('hard_delete')
                    ->label('Hard Delete Version')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Permanently delete this version?')
                    ->modalDescription('The lesson plan version will be permanently deleted. This cannot be undone. The deletion request will be marked resolved.')
                    ->action(function (DeletionRequest $record) {
                        app(DeletionRequestService::class)->resolve($record, auth()->user());
                        Notification::make('deletion-resolved')
                            ->title('Version permanently deleted.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DeletionRequest $record) => ! $record->isResolved()),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['version.family.subjectGrade.subject', 'requestedBy', 'resolvedBy'])
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeletionRequests::route('/'),
        ];
    }
}
