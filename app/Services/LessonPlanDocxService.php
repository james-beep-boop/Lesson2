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

        // Register Heading1 style so addTitle() emits a proper <w:pStyle w:val="Heading1"/> element.
        // Without this call, PhpWord cannot find "Heading_1" in its style registry and silently drops
        // the style reference, rendering the title as a plain Normal paragraph.
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 16, 'color' => '1e40af'], ['spaceAfter' => 80]);

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

        // Convert markdown to HTML.
        // Use html_input => 'strip' for DOCX: raw HTML pass-through (html_input => 'allow') can produce
        // non-self-closing void elements such as <br> that are invalid XML and crash PhpWord's XML parser.
        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip']);
        $html = (string) $converter->convert($version->content);

        // Add visible grid borders and cell padding to all tables so the DOCX matches the PDF style.
        // PhpWord reads the HTML border attribute in parseTable() and sets all six border edges.
        $html = $this->styleTablesForDocx($html);

        // Suppress E_DEPRECATED notices emitted by PhpWord (null array-offset in Style::getStyle).
        // If display_errors is On on the host, those notices would be written to the output stream
        // before the response headers are sent, prepending text to the binary DOCX and corrupting it.
        $previousErrorLevel = error_reporting(error_reporting() & ~E_DEPRECATED);
        $previousLibxmlErrors = libxml_use_internal_errors(true);

        try {
            Html::addHtml($section, $html, false, false);
        } finally {
            error_reporting($previousErrorLevel);
            libxml_use_internal_errors($previousLibxmlErrors);
            libxml_clear_errors();
        }

        // Export footer — mirrors PDF footer
        $section->addTextBreak();
        $section->addText('Exported '.now()->format('d M Y H:i').' · ARES Kenya Lesson Repository');

        return $this->phpWordToBytes($phpWord);
    }

    /**
     * Inject HTML attributes onto table/th/td elements so PhpWord generates a bordered grid.
     *
     * PhpWord's HTML parser reads the `border` attribute on <table> and calls setBorderSize() which
     * sets all six OOXML table border edges (top, left, right, bottom, insideH, insideV).
     * Cell padding is added via inline CSS that the parser maps to cell margin properties.
     * Header cells receive a light-blue background matching the PDF export style.
     */
    private function styleTablesForDocx(string $html): string
    {
        return str_replace(
            ['<table>', '<th>', '<td>'],
            [
                '<table border="1" width="100%">',
                '<th style="font-weight: bold; background-color: #dbeafe; padding: 4px 8px;">',
                '<td style="padding: 4px 8px;">',
            ],
            $html
        );
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
