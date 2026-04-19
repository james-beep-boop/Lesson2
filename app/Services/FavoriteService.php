<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FavoriteService
{
    /**
     * Set a favorite: one per (user, family). Replaces the previous version if any.
     */
    public function upsert(User $user, LessonPlanVersion $version): Favorite
    {
        return DB::transaction(function () use ($user, $version) {
            $family = $this->ensureFamily($version);

            return $this->persist($user, $family, $version);
        });
    }

    /**
     * Toggle a favorite on or off for the given user and version.
     * If this exact version is already the favorite, removes it and returns null.
     * Otherwise upserts this version as the favorite and returns it.
     */
    public function toggle(User $user, LessonPlanVersion $version): ?Favorite
    {
        return DB::transaction(function () use ($user, $version): ?Favorite {
            $family = $this->ensureFamily($version);

            $favorite = Favorite::where('user_id', $user->id)
                ->where('lesson_plan_family_id', $family->id)
                ->first();

            if ($favorite && $favorite->lesson_plan_version_id === $version->id) {
                $favorite->delete();

                return null;
            }

            return $this->persist($user, $family, $version);
        });
    }

    /**
     * Remove a user's favorite for a given family.
     */
    public function remove(User $user, int $familyId): void
    {
        Favorite::where('user_id', $user->id)
            ->where('lesson_plan_family_id', $familyId)
            ->delete();
    }

    /**
     * Get a user's favorite for a family, or null.
     */
    public function getFavorite(User $user, int $familyId): ?Favorite
    {
        return Favorite::where('user_id', $user->id)
            ->where('lesson_plan_family_id', $familyId)
            ->first();
    }

    private function ensureFamily(LessonPlanVersion $version): LessonPlanFamily
    {
        $family = $version->family;

        throw_unless(
            $family !== null,
            \InvalidArgumentException::class,
            'Version has no associated family.'
        );

        return $family;
    }

    private function persist(User $user, LessonPlanFamily $family, LessonPlanVersion $version): Favorite
    {
        $favorite = Favorite::where('user_id', $user->id)
            ->where('lesson_plan_family_id', $family->id)
            ->first();

        if ($favorite) {
            $favorite->lesson_plan_version_id = $version->id;
            $favorite->save();

            return $favorite;
        }

        $favorite = new Favorite([
            'lesson_plan_family_id' => $family->id,
            'lesson_plan_version_id' => $version->id,
        ]);
        $favorite->user_id = $user->id;
        $favorite->save();

        return $favorite;
    }
}
