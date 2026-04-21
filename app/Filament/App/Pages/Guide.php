<?php

namespace App\Filament\App\Pages;

use App\Support\GuideContent;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class Guide extends Page
{
    protected string $view = 'filament.app.pages.guide';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Guide';

    protected static ?int $navigationSort = 99;

    public string $language = 'en';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'User Guide';
    }

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

                if ($role === 'subject_admin' && $user->subjectGradesAsAdmin()->exists()) {
                    return true;
                }

                if ($role === 'editor' && $user->subjectGrades()->wherePivot('role', 'editor')->exists()) {
                    return true;
                }
            }

            return false;
        });
    }

    public function orientationText(): string
    {
        return match (auth()->user()?->role_label) {
            'Site Administrator' => 'You are a **Site Administrator**. You can manage subjects, grades, users, subject-grade assignments, and deletion requests across the entire system.',
            'Subject Administrator' => 'You are a **Subject Administrator**. You can edit lesson plans for your assigned subject-grades, mark versions official, and request deletions.',
            'Editor' => 'You are an **Editor**. You can create and submit new lesson plan versions for your assigned subject-grades.',
            default => 'You are a **Teacher**. You can browse, compare, favorite, message, print, export, and use the Swahili translation preview when it is enabled.',
        };
    }

    public function switchLanguage(string $lang): void
    {
        $this->language = in_array($lang, ['en', 'sw']) ? $lang : 'en';
    }
}
