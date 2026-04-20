<?php

namespace App\Filament\App\Pages;

use App\Models\SubjectGrade;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ManageTeam extends Page
{
    protected string $view = 'filament.app.pages.manage-team';

    protected static ?string $navigationLabel = 'Manage Subject Editors';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    private ?Collection $cachedSubjectGrades = null;

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
        return 'Manage Subject Editors';
    }

    public function getSubjectGrades(): Collection
    {
        return $this->cachedSubjectGrades ??= SubjectGrade::query()
            ->with('subject')
            ->where('subject_admin_user_id', auth()->id())
            ->orderBy('grade')
            ->get()
            ->sortBy(fn (SubjectGrade $subjectGrade): string => sprintf(
                '%s-%04d',
                $subjectGrade->subject->name,
                $subjectGrade->grade
            ))
            ->values();
    }
}
