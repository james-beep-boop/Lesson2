<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_plan_families', function (Blueprint $table) {
            // MariaDB uses the composite unique index to support the FK on subject_grade_id.
            // We must drop the FK first, then the index, then rebuild both without language.
            $table->dropForeign(['subject_grade_id']);
            $table->dropUnique(['subject_grade_id', 'day', 'language']);
            $table->dropColumn('language');
            $table->unique(['subject_grade_id', 'day']);
            $table->foreign('subject_grade_id')->references('id')->on('subject_grades');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plan_families', function (Blueprint $table) {
            $table->dropForeign(['subject_grade_id']);
            $table->dropUnique(['subject_grade_id', 'day']);
            $table->string('language', 5)->after('day');
            $table->unique(['subject_grade_id', 'day', 'language']);
            $table->foreign('subject_grade_id')->references('id')->on('subject_grades');
        });
    }
};
