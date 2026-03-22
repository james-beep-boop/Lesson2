<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('lesson_plan_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_grade_id')->constrained();
            $table->string('day');
            $table->string('language', 5);
            $table->foreignId('official_version_id')->nullable()->constrained('lesson_plan_versions')->nullOnDelete();
            $table->unique(['subject_grade_id', 'day', 'language']);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_families');
    }
};
