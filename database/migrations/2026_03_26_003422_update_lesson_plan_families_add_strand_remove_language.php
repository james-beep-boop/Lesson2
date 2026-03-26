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

            // New unique constraint on subject_grade_id + day
            $table->unique(['subject_grade_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plan_families', function (Blueprint $table) {
            $table->dropUnique(['subject_grade_id', 'day']);
            $table->string('language', 5)->after('day');
            $table->unique(['subject_grade_id', 'day', 'language']);
        });
    }
};
