<?php

namespace App\Filament\App\Pages;

use App\Support\GuideContent;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class Guide extends Page
{
    protected string $view = 'filament.app.pages.guide';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Guide';

    protected static ?int $navigationSort = 99;

    public string $language = 'en';

    public function mount(): void
    {
        if (session()->pull('manual_missing')) {
            Notification::make()
                ->title('Manual not available')
                ->body('The PDF manual is not currently on the server. Please contact an administrator.')
                ->warning()
                ->send();
        }
    }

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
        return GuideContent::visibleSections(auth()->user(), $this->language);
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

    public function manualDownloadUrl(): string
    {
        return route('guide.manual.download', ['lang' => $this->language]);
    }
}
