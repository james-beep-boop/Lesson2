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
                'official_version_id' => null,
            ]);

            $version = LessonPlanVersion::create([
                'lesson_plan_family_id' => $family->id,
                'contributor_id' => $contributor->id,
                'version' => '1.0.0',
                'content' => $content,
                'revision_note' => $revisionNote,
            ]);

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

            return LessonPlanVersion::create([
                'lesson_plan_family_id' => $family->id,
                'contributor_id' => $contributor->id,
                'version' => $nextVersion,
                'content' => $content,
                'revision_note' => $revisionNote,
            ]);
        });
    }

    /**
     * Set the official version for a family atomically.
     * Pass null to unset.
     */
    public function setOfficialVersion(LessonPlanFamily $family, ?LessonPlanVersion $version): void
    {
        DB::transaction(function () use ($family, $version) {
            $family->update(['official_version_id' => $version?->id]);
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
            'major' => ($major + 1) . '.0.0',
            'minor' => $major . '.' . ($minor + 1) . '.0',
            default => $major . '.' . $minor . '.' . ($patch + 1),
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
}
