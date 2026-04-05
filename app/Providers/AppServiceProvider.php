<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Policies\LessonPlanFamilyPolicy;
use App\Policies\LessonPlanVersionPolicy;
use App\Policies\SubjectGradePolicy;
use App\Policies\UserPolicy;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Always redirect to the Lesson Plans index after login,
        // bypassing any stored "intended" URL from a timed-out session.
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        // Centralised GFM renderer — strips raw HTML to prevent XSS.
        // Uses GitHub Flavored Markdown (tables, strikethrough, task lists).
        // html_input:'strip' removes any raw HTML in the source before parsing,
        // so {!! !!} output is safe without pre-escaping with e().
        Blade::directive('markdown', function (string $expression): string {
            return "<?php echo (new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert({$expression}); ?>";
        });

        Gate::policy(LessonPlanFamily::class, LessonPlanFamilyPolicy::class);
        Gate::policy(LessonPlanVersion::class, LessonPlanVersionPolicy::class);
        Gate::policy(SubjectGrade::class, SubjectGradePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
