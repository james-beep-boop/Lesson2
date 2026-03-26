<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_plan_families', function (Blueprint $table) {
            // Drop the old unique index that included language
            $table->dropUnique(['subject_grade_id', 'day', 'language']);

            // Drop the language column
            $table->dropColumn('language');

            // Add strand and substrand fields (nullable to support existing rows)
            $table->unsignedSmallInteger('strand_number')->nullable()->after('day');
            $table->string('strand_name')->nullable()->after('strand_number');
            $table->unsignedSmallInteger('substrand_number')->nullable()->after('strand_name');
            $table->string('substrand_name')->nullable()->after('substrand_number');

            // New unique constraint without language
            $table->unique(['subject_grade_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plan_families', function (Blueprint $table) {
            $table->dropUnique(['subject_grade_id', 'day']);
            $table->dropColumn(['strand_number', 'strand_name', 'substrand_number', 'substrand_name']);
            $table->string('language', 5)->after('day');
            $table->unique(['subject_grade_id', 'day', 'language']);
        });
    }
};
