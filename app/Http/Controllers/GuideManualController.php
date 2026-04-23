<?php

namespace App\Http\Controllers;

use App\Services\GuideManualService;

class GuideManualController extends Controller
{
    public function download(string $lang, GuideManualService $manuals)
    {
        abort_unless(in_array($lang, ['en', 'sw']), 404);
        abort_unless(auth()->check(), 403);

        // Get cached PDF (generated once), or generate if missing
        $pdfPath = $manuals->pdfPath($lang);
        if (! file_exists($pdfPath)) {
            $pdf = $manuals->renderIllustratedPdf($lang);
            file_put_contents($pdfPath, $pdf);
        }

        return response(file_get_contents($pdfPath))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$manuals->pdfFilename($lang).'"');
    }
}
