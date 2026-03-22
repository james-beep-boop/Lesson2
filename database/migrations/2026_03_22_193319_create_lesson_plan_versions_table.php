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

        Schema::create('lesson_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_plan_family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contributor_id')->constrained('users')->restrictOnDelete();
            $table->string('version', 20);
            $table->longText('content');
            $table->text('revision_note')->nullable();
            $table->unique(['lesson_plan_family_id', 'version']);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_versions');
    }
};
