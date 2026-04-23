<?php

namespace App\Http\Controllers;

use App\Services\GuideManualService;

class GuideManualController extends Controller
{
    public function download(string $lang, GuideManualService $manuals)
    {
        abort_unless(in_array($lang, ['en', 'sw']), 404);
        $lang = 'en'; // Swahili PDF not yet available; serve English for all languages
        abort_unless(auth()->check(), 403);

        $path = $manuals->pdfPath($lang);
        abort_unless(file_exists($path), 404);

        return response()->download($path, $manuals->pdfFilename($lang), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
