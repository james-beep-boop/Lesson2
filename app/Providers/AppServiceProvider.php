<?php

namespace App\Providers;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Policies\LessonPlanFamilyPolicy;
use App\Policies\LessonPlanVersionPolicy;
use App\Policies\SubjectGradePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(LessonPlanFamily::class, LessonPlanFamilyPolicy::class);
        Gate::policy(LessonPlanVersion::class, LessonPlanVersionPolicy::class);
        Gate::policy(SubjectGrade::class, SubjectGradePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
