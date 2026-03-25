<?php

namespace App\Services;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VersionService
{
    /**
     * Create a new family + first version (1.0.0) in a single transaction.
     * Returns the created version.
     */
    public function createFamilyWithFirstVersion(
        int $subjectGradeId,
        string $day,
        string $language,
        string $content,
        ?string $revisionNote,
        User $contributor
    ): LessonPlanVersion {
        return DB::transaction(function () use ($subjectGradeId, $day, $language, $content, $revisionNote, $contributor) {
            $family = LessonPlanFamily::create([
                'subject_grade_id' => $subjectGradeId,
                'day' => $day,
                'language' => $language,
            ]);

            $version = new LessonPlanVersion([
                'lesson_plan_family_id' => $family->id,
                'content' => $content,
                'revision_note' => $revisionNote,
            ]);
            $version->contributor_id = $contributor->id;
            $version->version = '1.0.0';
            $version->save();

            return $version;
        });
    }

    /**
     * Add a new version to an existing family.
     * $bump: 'patch', 'minor', or 'major'
     */
    public function addVersion(
        LessonPlanFamily $family,
        string $content,
        string $bump,
        ?string $revisionNote,
        User $contributor
    ): LessonPlanVersion {
        return DB::transaction(function () use ($family, $content, $bump, $revisionNote, $contributor) {
            $nextVersion = $this->computeNextVersion($family, $bump);

            $version = new LessonPlanVersion([
                'lesson_plan_family_id' => $family->id,
                'content' => $content,
                'revision_note' => $revisionNote,
            ]);
            $version->contributor_id = $contributor->id;
            $version->version = $nextVersion;
            $version->save();

            return $version;
        });
    }

    /**
     * Set the official version for a family atomically.
     * Pass null to unset.
     */
    public function setOfficialVersion(LessonPlanFamily $family, ?LessonPlanVersion $version): void
    {
        DB::transaction(function () use ($family, $version) {
            $family->official_version_id = $version?->id;
            $family->save();
        });
    }

    /**
     * Compute the next version string for a family.
     * $bump: 'patch', 'minor', or 'major'
     */
    public function computeNextVersion(LessonPlanFamily $family, string $bump = 'patch'): string
    {
        $highest = $family->versions()
            ->pluck('version')
            ->map(fn ($v) => $this->parseVersion($v))
            ->sort(fn ($a, $b) => $b <=> $a)
            ->first();

        if (! $highest) {
            return '1.0.0';
        }

        [$major, $minor, $patch] = $highest;

        return match ($bump) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    /**
     * Parse a semver string into [major, minor, patch].
     */
    public function parseVersion(string $version): array
    {
        $parts = explode('.', $version);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * Compare two version strings. Returns -1, 0, or 1.
     */
    public function compareVersions(string $a, string $b): int
    {
        [$aMaj, $aMin, $aPat] = $this->parseVersion($a);
        [$bMaj, $bMin, $bPat] = $this->parseVersion($b);

        return [$aMaj, $aMin, $aPat] <=> [$bMaj, $bMin, $bPat];
    }

    /**
     * Return true if a Swahili family already has a version matching $sourceVersion,
     * indicating a conflict that requires the user to choose a fallback bump.
     */
    public function translationHasVersionConflict(LessonPlanFamily $englishFamily, string $sourceVersion): bool
    {
        $swahiliFamily = LessonPlanFamily::where('subject_grade_id', $englishFamily->subject_grade_id)
            ->where('day', $englishFamily->day)
            ->where('language', 'sw')
            ->first();

        if ($swahiliFamily === null) {
            return false;
        }

        return $swahiliFamily->versions()->where('version', $sourceVersion)->exists();
    }

    /**
     * Create (or add to) the Swahili family for a translated lesson plan.
     *
     * - No Swahili family exists → create one, first version = source version number.
     * - Swahili family exists, source version not yet used → add at source version number.
     * - Swahili family exists, source version already used (conflict) → bump from highest
     *   Swahili version using $fallbackBump ('patch', 'minor', or 'major').
     */
    public function createTranslatedVersion(
        LessonPlanFamily $englishFamily,
        LessonPlanVersion $sourceVersion,
        string $content,
        User $contributor,
        string $fallbackBump = 'patch',
    ): LessonPlanVersion {
        return DB::transaction(function () use ($englishFamily, $sourceVersion, $content, $contributor, $fallbackBump) {
            $swahiliFamily = LessonPlanFamily::where('subject_grade_id', $englishFamily->subject_grade_id)
                ->where('day', $englishFamily->day)
                ->where('language', 'sw')
                ->first();

            if ($swahiliFamily === null) {
                $swahiliFamily = LessonPlanFamily::create([
                    'subject_grade_id' => $englishFamily->subject_grade_id,
                    'day' => $englishFamily->day,
                    'language' => 'sw',
                ]);

                $swahiliVersion = new LessonPlanVersion([
                    'lesson_plan_family_id' => $swahiliFamily->id,
                    'content' => $content,
                    'revision_note' => "Translated from English {$sourceVersion->version}",
                ]);
                $swahiliVersion->contributor_id = $contributor->id;
                $swahiliVersion->version = $sourceVersion->version;
                $swahiliVersion->save();

                return $swahiliVersion;
            }

            // Swahili family already exists — use source version number if not taken.
            $conflict = $swahiliFamily->versions()
                ->where('version', $sourceVersion->version)
                ->exists();

            $targetVersion = $conflict
                ? $this->computeNextVersion($swahiliFamily, $fallbackBump)
                : $sourceVersion->version;

            $swahiliVersion = new LessonPlanVersion([
                'lesson_plan_family_id' => $swahiliFamily->id,
                'content' => $content,
                'revision_note' => "Translated from English {$sourceVersion->version}",
            ]);
            $swahiliVersion->contributor_id = $contributor->id;
            $swahiliVersion->version = $targetVersion;
            $swahiliVersion->save();

            return $swahiliVersion;
        });
    }
}
