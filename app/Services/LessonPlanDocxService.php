<?php

namespace App\Services;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

class LessonPlanDocxService
{
    /**
     * Render a lesson plan version to DOCX and return the raw bytes.
     */
    public function render(LessonPlanFamily $family, LessonPlanVersion $version): string
    {
        $family->loadMissing(['subjectGrade.subject']);
        $version->loadMissing(['contributor']);

        $sg = $family->subjectGrade;

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Title
        $section->addTitle(
            $sg->subject->name.' — Grade '.$sg->grade.' · Day '.$family->day,
            1
        );

        // Meta: version, contributor, date — mirrors pdf/lesson-plan.blade.php header
        $section->addText(
            'Version: v'.$version->version
            .'  |  Contributor: '.($version->contributor?->name ?? '—')
            .'  |  Date: '.$version->created_at->format('d M Y'),
        );

        if ($version->revision_note) {
            $section->addText('Revision note: '.$version->revision_note);
        }

        if ($family->official_version_id === $version->id) {
            $section->addText('✓ Official Version');
        }

        $section->addTextBreak();

        // Convert markdown content to HTML then inject into the DOCX section.
        // html_input => 'allow' matches the PDF export path (pdf/lesson-plan.blade.php line 94).
        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'allow']);
        $html = (string) $converter->convert($version->content);

        Html::addHtml($section, $html, false, false);

        // Export footer — mirrors PDF footer
        $section->addTextBreak();
        $section->addText('Exported '.now()->format('d M Y H:i').' · ARES Kenya Lesson Repository');

        return $this->phpWordToBytes($phpWord);
    }

    /**
     * Build the download filename for a version.
     */
    public function filename(LessonPlanVersion $version): string
    {
        return str_replace('.md', '.docx', $version->getFilename());
    }

    /**
     * Write the PhpWord object to a temp file and return the raw bytes.
     */
    private function phpWordToBytes(PhpWord $phpWord): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lesson_docx_');

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempFile);

            return file_get_contents($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
