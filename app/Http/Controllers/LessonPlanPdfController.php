<?php

namespace App\Http\Controllers;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\LessonPlanPdfService;

class LessonPlanPdfController extends Controller
{
    /**
     * Download the lesson plan version as a PDF.
     * The version must belong to the given family.
     */
    public function download(LessonPlanFamily $family, LessonPlanVersion $version, LessonPlanPdfService $pdf)
    {
        abort_unless(auth()->check(), 403);
        abort_unless((int) $version->lesson_plan_family_id === $family->id, 404);

        set_time_limit(60);

        return response($pdf->render($family, $version))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$pdf->filename($version).'"');
    }
}
