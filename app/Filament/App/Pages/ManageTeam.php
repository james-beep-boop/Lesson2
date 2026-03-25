<?php

namespace App\Filament\App\Pages;

use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\SubjectAdminService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageTeam extends Page
{
    protected string $view = 'filament.app.pages.manage-team';

    protected static ?string $navigationLabel = 'My Team';

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
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
    }

    public function addEditor(): void
    {
        $sg = $this->getSubjectGrade();

        abort_unless(auth()->user()->isSubjectAdminFor($sg), 403);

        $this->validate(['addUserId' => 'required|exists:users,id']);

        $user = User::findOrFail($this->addUserId);
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
