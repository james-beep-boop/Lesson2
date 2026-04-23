<?php

namespace App\Http\Controllers;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\LessonPlanPdfService;
use Illuminate\Support\Facades\Cache;

class LessonPlanPdfController extends Controller
{
    /**
     * Download the lesson plan version as a PDF.
     * The version must belong to the given family.
     */
    public function download(LessonPlanFamily $family, LessonPlanVersion $version, LessonPlanPdfService $pdf)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->hasVerifiedEmail(), 403);
        abort_unless((int) $version->lesson_plan_family_id === $family->id, 404);

        set_time_limit(60);

        return response($pdf->render($family, $version))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$pdf->filename($version).'"');
    }

    /**
     * Render a short-lived translation preview as an inline PDF for browser printing.
     */
    public function printPreview(string $token, LessonPlanPdfService $pdf)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->hasVerifiedEmail(), 403);

        $payload = Cache::get("translation-preview-pdf:{$token}");

        abort_unless(is_array($payload), 404);
        abort_unless((int) ($payload['user_id'] ?? null) === (int) auth()->id(), 403);

        $version = LessonPlanVersion::with('family')->find($payload['lesson_plan_version_id'] ?? null);

        abort_unless($version instanceof LessonPlanVersion, 404);
        abort_unless(auth()->user()->can('translate', $version), 403);

        abort_unless(isset($payload['translated_content']) && $payload['translated_content'] !== '', 422);

        set_time_limit(60);

        return response($pdf->renderTranslation(
            $version->family,
            $version,
            (string) $payload['translated_content'],
        ))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$pdf->translationFilename($version).'"');
    }
}
