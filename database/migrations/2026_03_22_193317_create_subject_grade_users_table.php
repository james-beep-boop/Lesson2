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

        Schema::create('subject_grade_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_grade_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->enum('role', ["editor"]);
            $table->unique(['subject_grade_id', 'user_id']);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_grade_user');
    }
};
