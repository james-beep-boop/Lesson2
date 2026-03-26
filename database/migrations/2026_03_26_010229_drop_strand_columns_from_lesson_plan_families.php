<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only drop if the columns exist — on fresh installs the prior migration
        // no longer adds them, so this migration is a no-op on clean databases.
        if (Schema::hasColumn('lesson_plan_families', 'strand_number')) {
            Schema::table('lesson_plan_families', function (Blueprint $table) {
                $table->dropColumn(['strand_number', 'strand_name', 'substrand_number', 'substrand_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('lesson_plan_families', function (Blueprint $table) {
            $table->unsignedSmallInteger('strand_number')->nullable()->after('day');
            $table->string('strand_name')->nullable()->after('strand_number');
            $table->unsignedSmallInteger('substrand_number')->nullable()->after('strand_name');
            $table->string('substrand_name')->nullable()->after('substrand_number');
        });
    }
};
