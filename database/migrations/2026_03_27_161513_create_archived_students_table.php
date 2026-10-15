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
        Schema::create('archived_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Original user ID
            $table->string('student_name');
            $table->unsignedBigInteger('daora_id');
            $table->string('daora_name')->nullable();
            $table->date('archived_at');
            
            // Shared Demographics
            $table->string('family_status')->nullable();
            $table->string('job')->nullable();
            $table->string('region')->nullable();

            // Teachers linked at the time of archiving
            $table->text('teachers_names')->nullable();

            // Behaviors
            $table->text('traits')->nullable();
            $table->text('habits')->nullable();
            $table->text('discipline')->nullable();
            $table->text('morals')->nullable();
            $table->text('skills')->nullable();
            $table->text('positive_actions')->nullable();
            $table->text('negative_actions')->nullable();

            // Goals
            $table->text('goal_category')->nullable();
            $table->text('goal_text')->nullable();
            $table->text('goal_status')->nullable();
            $table->text('goal_comments')->nullable();

            // Quran Awqaf
            $table->text('ended_quraan_in_aukaf')->nullable();
            $table->string('quraan_rating')->nullable();
            $table->text('quraan_notes')->nullable();

            // Report extracted data
            $table->text('ended_quraan_this_course')->nullable();
            $table->text('ended_hadith_this_course')->nullable();
            $table->text('activities_this_course')->nullable();
            $table->text('report_notes')->nullable();

            // Total Points
            $table->integer('points')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_students');
    }
};
