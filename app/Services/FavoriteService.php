<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\LessonPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FavoriteService
{
    /**
     * Upsert a favorite: one per (user, family). Replaces the previous version if any.
     * version_id must belong to family_id — enforced here.
     */
    public function upsert(User $user, LessonPlanVersion $version): Favorite
    {
        return DB::transaction(function () use ($user, $version) {
            $family = $version->family;

            throw_unless(
                $family !== null,
                \InvalidArgumentException::class,
                'Version has no associated family.'
            );

            $favorite = Favorite::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'lesson_plan_family_id' => $family->id,
                ],
                [
                    'lesson_plan_version_id' => $version->id,
                ]
            );

            return $favorite;
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
}
