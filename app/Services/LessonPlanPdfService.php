<?php

namespace App\Services;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use Barryvdh\DomPDF\Facade\Pdf;

class LessonPlanPdfService
{
    /**
     * Render a lesson plan version to PDF and return the raw bytes.
     */
    public function render(LessonPlanFamily $family, LessonPlanVersion $version): string
    {
        $family->loadMissing(['subjectGrade.subject']);
        $version->loadMissing(['contributor']);

        return Pdf::loadView('pdf.lesson-plan', [
            'family' => $family,
            'version' => $version,
            'exportedAt' => now(),
        ])->output();
    }

    /**
     * Build the download filename for a version.
     */
    public function filename(LessonPlanVersion $version): string
    {
        return str_replace('.md', '.pdf', $version->getFilename());
    }

    /**
     * Render a Swahili translation preview to PDF and return the raw bytes.
     */
    public function renderTranslation(LessonPlanFamily $family, LessonPlanVersion $sourceVersion, string $translatedContent): string
    {
        $family->loadMissing(['subjectGrade.subject']);
        $sourceVersion->loadMissing(['contributor']);

        return Pdf::loadView('pdf.translation', [
            'family' => $family,
            'sourceVersion' => $sourceVersion,
            'translatedContent' => $translatedContent,
            'exportedAt' => now(),
        ])->output();
    }

    /**
     * Build the download filename for a translation PDF.
     */
    public function translationFilename(LessonPlanVersion $sourceVersion): string
    {
        return str_replace('.md', '_sw.pdf', $sourceVersion->getFilename());
    }
}
