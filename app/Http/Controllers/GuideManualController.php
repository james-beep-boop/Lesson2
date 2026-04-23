<?php

namespace App\Http\Controllers;

use App\Services\GuideManualService;
use Illuminate\Support\Facades\File;

class GuideManualController extends Controller
{
    public function download(string $lang, GuideManualService $manuals)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->hasVerifiedEmail(), 403);
        abort_unless(in_array($lang, ['en', 'sw'], true), 404);

        $path = $manuals->pdfPath($lang);
        abort_unless(File::exists($path), 404);

        return response()->download($path, $manuals->pdfFilename($lang), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
