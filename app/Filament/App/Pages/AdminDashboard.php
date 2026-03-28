<?php

namespace App\Filament\App\Pages;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AdminDashboard extends Page
{
    protected string $view = 'filament.app.pages.admin-dashboard';

    protected static ?string $navigationLabel = 'Admin';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSiteAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSiteAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);
    }

    public function getStats(): array
    {
        return once(function (): array {
            $siteAdmins = User::role('site_administrator')->where('is_system', false)->count();
            $subjectAdmins = SubjectGrade::whereNotNull('subject_admin_user_id')
                ->distinct('subject_admin_user_id')
                ->count('subject_admin_user_id');
            $editors = DB::table('subject_grade_user')->distinct('user_id')->count('user_id');
            $totalUsers = User::where('is_system', false)->count();
            $families = LessonPlanFamily::count();
            $versions = LessonPlanVersion::count();

            return compact('siteAdmins', 'subjectAdmins', 'editors', 'totalUsers', 'families', 'versions');
        });
    }
}
