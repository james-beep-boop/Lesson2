<?php

namespace App\Services;

use App\Ai\Agents\LessonPlanTranslator;
use App\Models\LessonPlanVersion;
use App\Models\User;

class TranslationService
{
    /**
     * Create or add to the Swahili family for the given English source version.
     *
     * Returns the created Swahili version.
     */
    public function translate(LessonPlanVersion $sourceVersion, string $translatedContent, User $contributor): LessonPlanVersion
    {
        // Translation requires a language identifier to distinguish the target family.
        // This feature must be redesigned now that the language column has been removed.
        throw new \RuntimeException('Translation feature is not yet available — pending redesign.');
    }

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
