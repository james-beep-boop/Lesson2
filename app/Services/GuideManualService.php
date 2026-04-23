<?php

namespace App\Services;

use App\Models\User;
use App\Support\GuideContent;

class GuideManualService
{
    public function title(string $lang = 'en'): string
    {
        return $lang === 'sw' ? 'Mwongozo wa Mpango wa Somo wa Kenya' : 'Kenya Lesson Plan Manual';
    }

    public function outputDirectory(): string
    {
        return storage_path('app/manuals');
    }

    public function markdownFilename(string $lang = 'en'): string
    {
        return "kenya-lesson-plan-manual-{$lang}.md";
    }

    public function pdfFilename(string $lang = 'en'): string
    {
        return "kenya-lesson-plan-manual-{$lang}.pdf";
    }

    public function markdownPath(string $lang = 'en'): string
    {
        return $this->outputDirectory().'/'.$this->markdownFilename($lang);
    }

    public function pdfPath(string $lang = 'en'): string
    {
        return $this->outputDirectory().'/'.$this->pdfFilename($lang);
    }

    public function markdown(string $lang = 'en', ?User $user = null): string
    {
        $sections = $user ? GuideContent::visibleSections($user, $lang) : GuideContent::sections($lang);
        $intro = $lang === 'sw'
            ? 'Mwongozo huu unaeleza jinsi ya kutumia Lesson2 kutazama, kuhariri, kulinganisha, kushiriki, na kusimamia mipango ya masomo nchini Kenya.'
            : 'This manual explains how to use Lesson2 to view, edit, compare, share, and administer lesson plans in Kenya.';

        $markdown = ['# '.$this->title($lang), '', $intro];

        foreach ($sections as $section) {
            $markdown[] = '';
            $markdown[] = '## '.$section['title'];
            $markdown[] = '';
            $markdown[] = $section['body'];
        }

        return implode("\n", $markdown)."\n";
    }

}
