<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SubjectGradeResource\Pages;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\SubjectAdminService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubjectGradeResource extends Resource
{
    protected static ?string $model = SubjectGrade::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static string|\UnitEnum|null $navigationGroup = 'Curriculum';
    protected static ?string $label = 'Subject Grade';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('subject_id')
                ->relationship('subject', 'name')
                ->required(),
            Select::make('grade')
                ->options(array_combine(range(1, 12), array_map(fn ($g) => 'Grade ' . $g, range(1, 12))))
                ->required(),
            // subject_admin_user_id is intentionally excluded from the form.
            // Use the "Assign Subject Admin" table action, which runs the full
            // SubjectAdminService::promote() transaction (demotion, pivot cleanup, etc.).
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['subject', 'subjectAdmin', 'users']))
            ->columns([
                TextColumn::make('subject.name')->sortable()->searchable()->label('Subject'),
                TextColumn::make('grade')->sortable()->formatStateUsing(fn ($state) => 'Grade ' . $state),
                TextColumn::make('subjectAdmin.username')->label('Subject Admin')->default('—'),
                TextColumn::make('editors_display')
                    ->label('Editors')
                    ->state(fn (SubjectGrade $record): string =>
                        $record->users->isNotEmpty()
                            ? $record->users->pluck('username')->join(', ')
                            : '—'
                    ),
            ])
            ->filters([
                SelectFilter::make('subject')->relationship('subject', 'name'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('assignSubjectAdmin')
                    ->label('Assign Subject Admin')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->options(User::where('is_system', false)->pluck('username', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SubjectGrade $record, array $data) {
                        $user = User::findOrFail($data['user_id']);
                        app(SubjectAdminService::class)->promote($user, $record);
                        Notification::make('subject-admin-assigned')->title('Subject Admin assigned')->success()->send();
                    }),
                Action::make('addEditor')
                    ->label('Add Editor')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->form(fn (SubjectGrade $record): array => [
                        Select::make('user_id')
                            ->label('User')
                            ->options(
                                User::where('is_system', false)
                                    ->whereNotIn('id', $record->users->pluck('id')->push($record->subject_admin_user_id)->filter()->unique()->values()->all())
                                    ->orderBy('name')
                                    ->pluck('username', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SubjectGrade $record, array $data): void {
                        $user = User::findOrFail($data['user_id']);
                        app(SubjectAdminService::class)->assignEditor($user, $record);
                        Notification::make('editor-added')->title('Editor added.')->success()->send();
                    }),
                Action::make('removeEditor')
                    ->label('Remove Editor')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->form(fn (SubjectGrade $record): array => [
                        Select::make('user_id')
                            ->label('Editor to remove')
                            ->options(
                                $record->users->pluck('username', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (SubjectGrade $record, array $data): void {
                        $user = User::findOrFail($data['user_id']);
                        app(SubjectAdminService::class)->removeUser($user, $record);
                        Notification::make('editor-removed')->title('Editor removed.')->success()->send();
                    })
                    ->visible(fn (SubjectGrade $record): bool => $record->users->isNotEmpty()),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjectGrades::route('/'),
            'create' => Pages\CreateSubjectGrade::route('/create'),
            'edit' => Pages\EditSubjectGrade::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSiteAdmin() ?? false;
    }
}
