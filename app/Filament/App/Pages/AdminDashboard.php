<?php

namespace App\Filament\App\Pages;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\BackupService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminDashboard extends Page
{
    protected string $view = 'filament.app.pages.admin-dashboard';

    protected static ?string $navigationLabel = 'Admin';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 10;

    /** The filename selected for restore; bound to the restore select in the view. */
    public ?string $restoreFilename = null;

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

    public function backupNow(): void
    {
        try {
            ['filename' => $filename, 'counts' => $counts] = app(BackupService::class)->create();

            $summary = collect([
                'users' => 'user',
                'lesson_plan_versions' => 'lesson plan',
                'subjects' => 'subject',
                'messages' => 'message',
            ])
                ->filter(fn ($_, $table) => ($counts[$table] ?? 0) > 0)
                ->map(fn ($label, $table) => $counts[$table].' '.str($label)->plural($counts[$table]))
                ->values()
                ->implode(' · ');

            Notification::make()
                ->title('Backup created')
                ->body($filename.($summary ? "\n".$summary : ''))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Backup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function restoreBackup(): void
    {
        if (blank($this->restoreFilename)) {
            Notification::make()
                ->title('No backup selected')
                ->warning()
                ->send();

            return;
        }

        try {
            app(BackupService::class)->restore($this->restoreFilename);

            // Enqueue the redirect before invalidating the session.
            // Livewire delivers redirects as a browser-side JS instruction; the
            // session operations below must not prevent it from being set.
            $this->redirect(route('filament.app.auth.login'));

            // Invalidate the server-side session so the user is fully logged out
            // even on file-based sessions (which survive the DB restore).
            auth()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Restore failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteBackup(): void
    {
        if (blank($this->restoreFilename)) {
            Notification::make()
                ->title('No backup selected')
                ->warning()
                ->send();

            return;
        }

        if (count(app(BackupService::class)->list()) <= 1) {
            Notification::make()
                ->title('Cannot delete the last backup')
                ->body('At least one backup must be kept.')
                ->warning()
                ->send();

            return;
        }

        $filename = basename($this->restoreFilename);

        try {
            Storage::disk('local')->delete('backups/'.$filename);

            $this->restoreFilename = null;

            Notification::make()
                ->title('Backup deleted')
                ->body($filename)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Delete failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int, array{filename: string, created_at: int, size: int}>
     */
    public function getAvailableBackups(): array
    {
        return app(BackupService::class)->list();
    }
}
