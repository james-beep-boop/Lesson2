<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('lesson_plan_families')
            ->select('id')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $family): void {
                /** @var Collection<int, object> $versions */
                $versions = DB::table('lesson_plan_versions')
                    ->select('id', 'version')
                    ->where('lesson_plan_family_id', $family->id)
                    ->orderBy('id')
                    ->get();

                if ($versions->isEmpty()) {
                    return;
                }

                $official = $versions->firstWhere('version', '1.0.0')
                    ?? $versions
                        ->sort(fn (object $left, object $right) => version_compare($left->version, $right->version))
                        ->first();

                DB::table('lesson_plan_families')
                    ->where('id', $family->id)
                    ->update(['official_version_id' => $official?->id]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lesson_plan_families')->update(['official_version_id' => null]);
    }
};
