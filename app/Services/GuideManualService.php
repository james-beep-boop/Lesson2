<?php

namespace App\Services;

use App\Models\User;
use App\Support\GuideContent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

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

    /**
     * Path to the illustrated manual markdown (with images)
     */
    public function illustratedMarkdownPath(string $lang = 'en'): string
    {
        return $this->outputDirectory()."/kenya-lesson-plan-manual-{$lang}-illustrated.md";
    }

    /**
     * Get illustrated markdown content with images
     */
    public function illustratedMarkdown(string $lang = 'en'): string
    {
        $path = $this->illustratedMarkdownPath($lang);
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        return $this->markdown($lang); // Fallback to text-only
    }

    /**
     * Render illustrated PDF with embedded images
     */
    public function renderIllustratedPdf(string $lang = 'en'): string
    {
        $md = $this->illustratedMarkdown($lang);

        // Convert markdown to HTML
        $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter();
        $html = $converter->convert($md)->getContent();

        // Fix image paths to absolute file:// URLs for DomPDF
        $storagePath = storage_path('app/manuals');
        $html = preg_replace_callback(
            '!src=["\'](\./screenshots/[^"\']+)["\']!',
            function($m) use ($storagePath) {
                $relativePath = $m[1];
                $absolutePath = $storagePath . '/' . substr($relativePath, 2);
                // Use file:// URL for local file access
                return 'src="file://' . $absolutePath . '"';
            },
            $html
        );

        // Add styling
        $css = '<style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 20px; }
        img { max-width: 100%; height: auto; margin: 15px 0; border: 1px solid #ddd; padding: 8px; }
        h1 { color: #1e40af; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
        h2 { color: #1e40af; margin-top: 30px; }
        h3 { color: #374151; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
        strong { color: #111827; }
        </style>';

        $fullHtml = $css . $html;

        // Generate PDF with DomPDF directly to ensure image support
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('enable_remote', false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($fullHtml);
        $dompdf->render();

        return $dompdf->output();
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

    public function renderPdf(string $lang = 'en', ?User $user = null): string
    {
        return Pdf::loadView('pdf.guide-manual', [
            'title' => $this->title($lang),
            'language' => $lang,
            'sections' => $user ? GuideContent::visibleSections($user, $lang) : GuideContent::sections($lang),
            'exportedAt' => now(),
        ])->output();
    }

    public function generateAndSave(string $lang = 'en'): void
    {
        File::ensureDirectoryExists($this->outputDirectory());

        File::put($this->markdownPath($lang), $this->markdown($lang));
        File::put($this->pdfPath($lang), $this->renderPdf($lang));
    }

    /**
     * @return array{markdown:string,pdf:string}
     */
    public function generateAndSaveAll(string $lang = 'en'): array
    {
        $this->generateAndSave($lang);

        return [
            'markdown' => $this->markdownPath($lang),
            'pdf' => $this->pdfPath($lang),
        ];
    }
}
