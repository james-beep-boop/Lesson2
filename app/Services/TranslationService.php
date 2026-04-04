<?php

namespace App\Services;

use App\Ai\Agents\LessonPlanTranslator;

class TranslationService
{
    /**
     * Stream a translation from LessonPlanTranslator.
     * Returns a StreamableAgentResponse.
     */
    public function streamTranslation(string $content)
    {
        set_time_limit(60);

        return LessonPlanTranslator::make()->stream($content);
    }
}
