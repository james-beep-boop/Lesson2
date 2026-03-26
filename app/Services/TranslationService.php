<?php

namespace App\Services;

use App\Ai\Agents\LessonPlanTranslator;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    public function __construct(
        private readonly VersionService $versionService
    ) {}

    /**
     * Create or add to the Swahili family for the given English source version.
     *
     * Returns the created Swahili version.
     */
    public function translate(LessonPlanVersion $sourceVersion, string $translatedContent, User $contributor): LessonPlanVersion
    {
        return DB::transaction(function () use ($sourceVersion, $translatedContent, $contributor) {
            $sourceFamily = $sourceVersion->family;

            // TODO: redesign when Swahili translation feature is implemented (language column removed)
            $swahiliFamily = LessonPlanFamily::where('subject_grade_id', $sourceFamily->subject_grade_id)
                ->where('day', $sourceFamily->day)
                ->first();

            $targetVersionNumber = $sourceVersion->version;

            if ($swahiliFamily) {
                // Check for version conflict in existing Swahili family.
                $conflict = $swahiliFamily->versions()
                    ->where('version', $targetVersionNumber)
                    ->exists();

                if ($conflict) {
                    // Fall back to standard bump from highest Swahili version.
                    $targetVersionNumber = $this->versionService->computeNextVersion($swahiliFamily, 'patch');
                }

                $swahiliVersion = new LessonPlanVersion([
                    'lesson_plan_family_id' => $swahiliFamily->id,
                    'content' => $translatedContent,
                    'revision_note' => 'Translated from English version '.$sourceVersion->version,
                ]);
                $swahiliVersion->contributor_id = $contributor->id;
                $swahiliVersion->version = $targetVersionNumber;
                $swahiliVersion->save();
            } else {
                // TODO: redesign when Swahili translation feature is implemented (language column removed)
                $swahiliFamily = LessonPlanFamily::create([
                    'subject_grade_id' => $sourceFamily->subject_grade_id,
                    'day' => $sourceFamily->day,
                ]);

                $swahiliVersion = new LessonPlanVersion([
                    'lesson_plan_family_id' => $swahiliFamily->id,
                    'content' => $translatedContent,
                    'revision_note' => 'Translated from English version '.$sourceVersion->version,
                ]);
                $swahiliVersion->contributor_id = $contributor->id;
                $swahiliVersion->version = $targetVersionNumber;
                $swahiliVersion->save();
            }

            return $swahiliVersion;
        });
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
