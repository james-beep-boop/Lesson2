<?php

namespace App\Services;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Support\Collection;
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
        string $content,
        ?string $revisionNote,
        User $contributor,
    ): LessonPlanVersion {
        return DB::transaction(function () use ($subjectGradeId, $day, $content, $revisionNote, $contributor) {
            $family = LessonPlanFamily::create([
                'subject_grade_id' => $subjectGradeId,
                'day' => $day,
            ]);

            $version = new LessonPlanVersion([
                'lesson_plan_family_id' => $family->id,
                'content' => $content,
                'revision_note' => $revisionNote,
            ]);
            $version->contributor_id = $contributor->id;
            $version->version = '1.0.0';
            $version->save();
            $family->official_version_id = $version->id;
            $family->save();

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
            // Lock the family row so concurrent addVersion calls for the same family
            // are serialized. Without this, two simultaneous saves can both read the
            // same max version and collide on the unique constraint.
            $lockedFamily = LessonPlanFamily::where('id', $family->id)->lockForUpdate()->firstOrFail();

            $nextVersion = $this->computeNextVersion($lockedFamily, $bump);

            $version = new LessonPlanVersion([
                'lesson_plan_family_id' => $lockedFamily->id,
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
            $family->official_version_id = $version?->id
                ?? $this->preferredOfficialVersion($family)?->id;
            $family->save();
        });
    }

    public function preferredOfficialVersion(LessonPlanFamily $family, ?int $excludingVersionId = null): ?LessonPlanVersion
    {
        /** @var Collection<int, LessonPlanVersion> $versions */
        $versions = $family->versions()
            ->when($excludingVersionId, fn ($query) => $query->whereKeyNot($excludingVersionId))
            ->get();

        if ($versions->isEmpty()) {
            return null;
        }

        $firstVersion = $versions->firstWhere('version', '1.0.0');

        if ($firstVersion) {
            return $firstVersion;
        }

        return $versions
            ->sort(fn (LessonPlanVersion $left, LessonPlanVersion $right) => version_compare($left->version, $right->version))
            ->first();
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
     * Compute all three next version strings (major/minor/patch) in a single
     * DB round-trip. Use this instead of calling computeNextVersion() three times.
     *
     * @return array{major: string, minor: string, patch: string}
     */
    public function computeAllNextVersions(LessonPlanFamily $family): array
    {
        $highest = $family->versions()
            ->pluck('version')
            ->map(fn ($v) => $this->parseVersion($v))
            ->sort(fn ($a, $b) => $b <=> $a)
            ->first();

        if (! $highest) {
            return ['major' => '1.0.0', 'minor' => '1.0.0', 'patch' => '1.0.0'];
        }

        [$major, $minor, $patch] = $highest;

        return [
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            'patch' => $major.'.'.$minor.'.'.($patch + 1),
        ];
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
