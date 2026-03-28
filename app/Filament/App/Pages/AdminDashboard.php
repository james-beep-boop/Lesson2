<?php

namespace App\Filament\App\Pages;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\VersionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboard extends Page
{
    protected string $view = 'filament.app.pages.admin-dashboard';

    protected static ?string $navigationLabel = 'Admin';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 10;

    // Lesson plan table state
    public string $lessonTab = 'all';

    public string $lessonSearch = '';

    public array $selectedVersionIds = [];

    // User table state
    public array $selectedUserIds = [];

    public array $userStatusChanges = []; // [user_id => 'administrator'|'user']

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
        $this->initUserStatuses();
    }

    private function initUserStatuses(): void
    {
        foreach ($this->getUsers() as $user) {
            $this->userStatusChanges[$user->id] = $user->isSiteAdmin() ? 'administrator' : 'user';
        }
    }

    // -------------------------------------------------------------------------
    // Data accessors
    // -------------------------------------------------------------------------

    public function getStats(): array
    {
        $siteAdmins = User::role('site_administrator')->where('is_system', false)->count();
        $subjectAdmins = SubjectGrade::whereNotNull('subject_admin_user_id')
            ->distinct('subject_admin_user_id')
            ->count('subject_admin_user_id');
        $editors = DB::table('subject_grade_user')->distinct('user_id')->count('user_id');
        $totalUsers = User::where('is_system', false)->count();
        $families = LessonPlanFamily::count();
        $versions = LessonPlanVersion::count();

        return compact('siteAdmins', 'subjectAdmins', 'editors', 'totalUsers', 'families', 'versions');
    }

    public function getLessonVersions(): Collection
    {
        $query = LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor'])
            ->join('lesson_plan_families', 'lesson_plan_versions.lesson_plan_family_id', '=', 'lesson_plan_families.id')
            ->join('subject_grades', 'lesson_plan_families.subject_grade_id', '=', 'subject_grades.id')
            ->join('subjects', 'subject_grades.subject_id', '=', 'subjects.id')
            ->select('lesson_plan_versions.*');

        match ($this->lessonTab) {
            'official' => $query->whereIn(
                'lesson_plan_versions.id',
                DB::table('lesson_plan_families')
                    ->whereNotNull('official_version_id')
                    ->pluck('official_version_id')
            ),
            'latest' => $query->whereIn(
                'lesson_plan_versions.id',
                DB::table('lesson_plan_versions')
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('lesson_plan_family_id')
            ),
            'favorites' => $query->whereHas(
                'favorites',
                fn ($q) => $q->where('user_id', auth()->id())
            ),
            default => null,
        };

        if (filled($this->lessonSearch)) {
            $search = $this->lessonSearch;
            $query->where(function ($q) use ($search) {
                $q->where('subjects.name', 'like', "%{$search}%")
                    ->orWhere('subject_grades.grade', 'like', "%{$search}%")
                    ->orWhere('lesson_plan_families.day', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy('subjects.name')
            ->orderBy('subject_grades.grade')
            ->orderBy('lesson_plan_families.day')
            ->orderBy('lesson_plan_versions.version')
            ->get();
    }

    public function getUsers(): Collection
    {
        return User::where('is_system', false)
            ->orderBy('name')
            ->get();
    }

    public function hasStatusChanges(): bool
    {
        if (empty($this->userStatusChanges)) {
            return false;
        }

        $users = User::whereIn('id', array_keys($this->userStatusChanges))->get()->keyBy('id');

        foreach ($this->userStatusChanges as $userId => $newStatus) {
            $user = $users->get((int) $userId);
            if (! $user) {
                continue;
            }

            if ($user->isSiteAdmin() !== ($newStatus === 'administrator')) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Lesson plan actions
    // -------------------------------------------------------------------------

    public function setLessonTab(string $tab): void
    {
        $this->lessonTab = $tab;
        $this->selectedVersionIds = [];
    }

    public function setOfficial(int $familyId, int $versionId): void
    {
        $family = LessonPlanFamily::findOrFail($familyId);
        $version = LessonPlanVersion::findOrFail($versionId);

        // Toggle: if already official, unset; otherwise set.
        $newOfficial = ((int) $family->official_version_id === $versionId) ? null : $version;

        app(VersionService::class)->setOfficialVersion($family, $newOfficial);

        Notification::make('official-updated')
            ->title($newOfficial ? 'Official version set.' : 'Official status removed.')
            ->success()
            ->send();
    }

    public function deleteSelectedVersions(): void
    {
        if (empty($this->selectedVersionIds)) {
            return;
        }

        $ids = array_map('intval', $this->selectedVersionIds);

        DB::transaction(function () use ($ids) {
            $versions = LessonPlanVersion::whereIn('id', $ids)->with('family')->get();

            foreach ($versions as $version) {
                $family = $version->family;

                // Clear the official pointer before deleting.
                if ($family && (int) $family->official_version_id === $version->id) {
                    $family->official_version_id = null;
                    $family->save();
                }

                // Favorites cascade via FK; deletion_requests nullOnDelete.
                $version->delete();

                // Remove orphaned family (favorites cascade via FK).
                if ($family && $family->fresh()?->versions()->doesntExist()) {
                    $family->delete();
                }
            }
        });

        $count = count($ids);
        $this->selectedVersionIds = [];

        Notification::make('versions-deleted')
            ->title('Deleted '.$count.' '.str('version')->plural($count).'.')
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // User actions
    // -------------------------------------------------------------------------

    public function deleteSelectedUsers(): void
    {
        if (empty($this->selectedUserIds)) {
            return;
        }

        $ids = array_filter(
            array_map('intval', $this->selectedUserIds),
            fn ($id) => $id !== auth()->id()
        );

        if (empty($ids)) {
            Notification::make('cannot-self-delete')
                ->title('You cannot delete your own account.')
                ->warning()
                ->send();

            return;
        }

        User::whereIn('id', $ids)->delete();

        $count = count($ids);
        $this->selectedUserIds = [];
        $this->initUserStatuses();

        Notification::make('users-deleted')
            ->title('Deleted '.$count.' '.str('user')->plural($count).'.')
            ->success()
            ->send();
    }

    public function confirmStatusChanges(): void
    {
        if (! $this->hasStatusChanges()) {
            return;
        }

        $users = $this->getUsers()->keyBy('id');
        $changed = 0;

        foreach ($this->userStatusChanges as $userId => $newStatus) {
            $user = $users->get((int) $userId);
            if (! $user) {
                continue;
            }

            $shouldBeAdmin = $newStatus === 'administrator';

            if ($user->isSiteAdmin() === $shouldBeAdmin) {
                continue;
            }

            if ($shouldBeAdmin) {
                $user->syncRoles(['site_administrator']);
            } else {
                $user->removeRole('site_administrator');
            }

            $changed++;
        }

        Notification::make('status-changes-applied')
            ->title('Updated status for '.$changed.' '.str('user')->plural($changed).'.')
            ->success()
            ->send();

        $this->initUserStatuses();
    }
}
