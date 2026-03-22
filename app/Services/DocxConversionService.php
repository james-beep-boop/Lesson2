<?php

namespace App\Services;

use League\HTMLToMarkdown\HtmlConverter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class DocxConversionService
{
    /**
     * Convert a .docx file to Markdown.
     * Returns the Markdown string.
     */
    public function convert(string $filePath): string
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = IOFactory::load($filePath);
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');

        $tempFile = tempnam(sys_get_temp_dir(), 'docx_html_');
        try {
            $htmlWriter->save($tempFile);
            $html = file_get_contents($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => false,
        ]);

        return $converter->convert($html);
    }
}
