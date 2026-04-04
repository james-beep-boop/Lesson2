<?php

namespace App\Http\Controllers;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use Barryvdh\DomPDF\Facade\Pdf;

class LessonPlanPdfController extends Controller
{
    /**
     * Download the lesson plan version as a PDF.
     * The version must belong to the given family.
     */
    public function download(LessonPlanFamily $family, LessonPlanVersion $version)
    {
        abort_unless(auth()->check(), 403);

        // Ensure the version belongs to this family.
        abort_unless((int) $version->lesson_plan_family_id === $family->id, 404);

        $family->load(['subjectGrade.subject']);
        $version->load(['contributor']);

        $pdf = Pdf::loadView('pdf.lesson-plan', [
            'family' => $family,
            'version' => $version,
            'exportedAt' => now(),
        ]);

        $filename = $version->getFilename();
        $filename = str_replace('.md', '.pdf', $filename);

        return $pdf->download($filename);
    }
}
