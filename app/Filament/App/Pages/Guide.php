<?php

namespace App\Filament\App\Pages;

use App\Support\GuideContent;
use Filament\Pages\Page;

class Guide extends Page
{
    protected string $view = 'filament.app.pages.guide';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Guide';

    protected static ?int $navigationSort = 99;

    public string $language = 'en';

    public function getTitle(): string
    {
        return 'User Guide';
    }

    /**
     * Return the guide sections for the currently selected language,
     * filtered by the authenticated user's highest role.
     */
    public function sections(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $all = GuideContent::sections($this->language);

        return array_filter($all, function (array $section) use ($user): bool {
            if ($section['roles'] === null) {
                return true;
            }

            foreach ($section['roles'] as $role) {
                if ($role === 'site_administrator' && $user->isSiteAdmin()) {
                    return true;
                }

                if ($role === 'subject_admin' && $user->subjectGradeAsAdmin()->exists()) {
                    return true;
                }

                if ($role === 'editor' && $user->subjectGrades()->wherePivot('role', 'editor')->exists()) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * A short role-specific heading shown at the top of the guide,
     * so users immediately understand what they can do in the system.
     */
    public function orientationText(): string
    {
        // role_label is a cached Eloquent attribute — no extra DB queries after the first call.
        return match (auth()->user()?->role_label) {
            'Administrator' => 'You are a **Site Administrator**. You can manage all lesson plans, users, and subject-grade assignments across the entire system.',
            'Subject Admin' => 'You are a **Subject Administrator**. You can create, edit, and publish official lesson plans for your assigned subject-grades.',
            'Editor'        => 'You are an **Editor**. You can create and submit new lesson plan versions for your assigned subject-grades.',
            default         => 'You are a **Teacher**. You can browse, favourite, and comment on lesson plans.',
        };
    }

    public function switchLanguage(string $lang): void
    {
        $this->language = in_array($lang, ['en', 'sw']) ? $lang : 'en';
    }
}
