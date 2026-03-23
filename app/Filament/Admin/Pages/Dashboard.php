<?php

namespace App\Filament\Admin\Pages;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;

class Dashboard extends \Filament\Pages\Dashboard
{
    // Non-static: matches the parent Page class declaration
    protected string $view = 'filament.admin.pages.dashboard';

    public int $userCount    = 0;
    public int $subjectCount = 0;
    public int $gradeCount   = 0;
    public int $familyCount  = 0;
    public int $versionCount = 0;
    public int $officialCount = 0;

    public function mount(): void
    {
        $this->userCount    = User::where('is_system', false)->count();
        $this->subjectCount = Subject::count();
        $this->gradeCount   = SubjectGrade::count();
        $this->familyCount  = LessonPlanFamily::count();
        $this->versionCount = LessonPlanVersion::count();
        $this->officialCount = LessonPlanFamily::whereNotNull('official_version_id')->count();
    }
}
