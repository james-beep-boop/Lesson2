<?php

namespace App\Http\Controllers;

use App\Services\GuideManualService;

class GuideManualController extends Controller
{
    public function download(string $lang, GuideManualService $manuals)
    {
        abort_unless(in_array($lang, ['en', 'sw']), 404);
        abort_unless(auth()->check(), 403);

        return response($manuals->renderPdf($lang))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$manuals->pdfFilename($lang).'"');
    }
}
