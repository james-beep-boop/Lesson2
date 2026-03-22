<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LessonPlanFamilyResource\Pages;
use App\Models\LessonPlanFamily;
use App\Models\SubjectGrade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LessonPlanFamilyResource extends Resource
{
    protected static ?string $model = LessonPlanFamily::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Lessons';
    protected static ?string $label = 'Lesson Plan';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('subject_grade_id')
                ->label('Subject Grade')
                ->relationship('subjectGrade', 'id', fn ($query) => $query->with('subject'))
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->subject->name . ' — Grade ' . $record->grade)
                ->searchable()
                ->required(),
            TextInput::make('day')->required(),
            Select::make('language')
                ->options(['en' => 'English', 'sw' => 'Swahili'])
                ->default('en')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subjectGrade.subject.name')->label('Subject')->sortable()->searchable(),
                TextColumn::make('subjectGrade.grade')->label('Grade')->formatStateUsing(fn ($state) => 'Grade ' . $state)->sortable(),
                TextColumn::make('day')->sortable(),
                TextColumn::make('language')->formatStateUsing(fn ($state) => $state === 'en' ? 'English' : 'Swahili'),
                TextColumn::make('officialVersion.version')->label('Official Version')->default('—'),
                TextColumn::make('versions_count')->counts('versions')->label('Versions'),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->relationship('subjectGrade.subject', 'name')
                    ->label('Subject'),
                SelectFilter::make('language')
                    ->options(['en' => 'English', 'sw' => 'Swahili']),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessonPlanFamilies::route('/'),
            'view' => Pages\ViewLessonPlanFamily::route('/{record}'),
            'create' => Pages\CreateLessonPlanFamily::route('/create'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->isSiteAdmin()
            || SubjectGrade::where('subject_admin_user_id', $user->id)->exists();
    }
}
