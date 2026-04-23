<?php

namespace App\Services;

class GuideManualService
{
    public function outputDirectory(): string
    {
        return storage_path('app/manuals');
    }

    public function pdfFilename(string $lang = 'en'): string
    {
        return "kenya-lesson-plan-manual-{$lang}.pdf";
    }

    public function pdfPath(string $lang = 'en'): string
    {
        return $this->outputDirectory().'/'.$this->pdfFilename($lang);
    }
}
