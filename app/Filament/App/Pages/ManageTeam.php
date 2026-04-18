<?php

namespace App\Filament\App\Pages;

use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\SubjectAdminService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ManageTeam extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected string $view = 'filament.app.pages.manage-team';

    protected static ?string $navigationLabel = 'Manage Team';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    public ?int $addUserId = null;

    private ?SubjectGrade $cachedSubjectGrade = null;

    /** Only Subject Admins see this page in the nav. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && SubjectGrade::where('subject_admin_user_id', $user->id)->exists();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getHeading(): string
    {
        $sg = $this->getSubjectGrade();

        return "Manage Team: {$sg->subject->name}, Grade {$sg->grade}";
    }

    /** The Subject Admin's one subject_grade. Cached for the lifetime of this request. */
    public function getSubjectGrade(): SubjectGrade
    {
        return $this->cachedSubjectGrade ??= SubjectGrade::with(['subject', 'users', 'subjectAdmin'])
            ->where('subject_admin_user_id', auth()->id())
            ->firstOrFail();
    }

    /** Users eligible to be added as editors: excludes system, current editors, and the subject admin. */
    public function getAvailableUsers()
    {
        $sg = $this->getSubjectGrade();

        $excludeIds = $sg->users->pluck('id')
            ->push($sg->subject_admin_user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return User::where('is_system', false)
            ->whereNotNull('email_verified_at')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
    }

    public function table(Table $table): Table
    {
        $sg = $this->getSubjectGrade();

        return $table
            ->query(
                User::query()->whereHas('subjectGrades', fn ($q) => $q->where('subject_grades.id', $sg->id))
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Full name'),
                Tables\Columns\TextColumn::make('username')->label('Username'),
                Tables\Columns\TextColumn::make('email')->label('Email'),
            ])
            ->bulkActions([
                BulkAction::make('remove')
                    ->label('Remove from team')
                    ->color('danger')
                    ->icon('heroicon-o-user-minus')
                    ->requiresConfirmation()
                    ->modalHeading('Remove editors')
                    ->modalDescription('Remove the selected editors from this team?')
                    ->action(function (Collection $records) use ($sg): void {
                        abort_unless(auth()->user()->isSubjectAdminFor($sg), 403);
                        $service = app(SubjectAdminService::class);
                        foreach ($records as $user) {
                            $service->removeUser($user, $sg);
                        }
                        $count = $records->count();
                        Notification::make('editors-removed')
                            ->title($count === 1
                                ? "{$records->first()->name} removed from team."
                                : "{$count} editors removed from team.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->heading('Current Editors')
            ->emptyStateHeading('No editors assigned yet.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->paginated(false);
    }

    public function addEditor(): void
    {
        $sg = $this->getSubjectGrade();

        abort_unless(auth()->user()->isSubjectAdminFor($sg), 403);

        $this->validate([
            'addUserId' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('is_system', false)
                        ->whereNotNull('email_verified_at')),
            ],
        ]);

        $user = User::where('is_system', false)
            ->whereNotNull('email_verified_at')
            ->findOrFail($this->addUserId);
        app(SubjectAdminService::class)->assignEditor($user, $sg);

        $this->addUserId = null;

        Notification::make('editor-added')
            ->title("{$user->name} added as Editor.")
            ->success()
            ->send();
    }

    public function removeEditor(int $userId): void
    {
        $sg = $this->getSubjectGrade();

        abort_unless(auth()->user()->isSubjectAdminFor($sg), 403);

        $user = User::findOrFail($userId);
        app(SubjectAdminService::class)->removeUser($user, $sg);

        Notification::make('editor-removed')
            ->title("{$user->name} removed from team.")
            ->success()
            ->send();
    }
}
