<?php

namespace App\Http\Controllers;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\LessonPlanDocxService;

class LessonPlanDocxController extends Controller
{
    /**
     * Download the lesson plan version as a DOCX file.
     * The version must belong to the given family.
     */
    public function download(LessonPlanFamily $family, LessonPlanVersion $version, LessonPlanDocxService $docx)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->hasVerifiedEmail(), 403);
        abort_unless((int) $version->lesson_plan_family_id === $family->id, 404);

        set_time_limit(60);

        return response($docx->render($family, $version))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->header('Content-Disposition', 'attachment; filename="'.$docx->filename($version).'"');
    }
}
