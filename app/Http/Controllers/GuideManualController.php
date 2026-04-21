<?php

namespace App\Http\Controllers;

use App\Services\GuideManualService;

class GuideManualController extends Controller
{
    public function download(string $lang, GuideManualService $manuals)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(in_array($lang, ['en', 'sw']), 404);

        $paths = $manuals->ensureSaved($lang);

        return response()->download($paths['pdf'], $manuals->pdfFilename($lang));
    }
}
