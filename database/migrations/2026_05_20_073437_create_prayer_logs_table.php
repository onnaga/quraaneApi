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
        Schema::create('prayer_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prayer_program_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('daora_id'); // Denormalized for fast fetching by daora
            $table->date('date');

            // Prayers & Sunnah
            $table->enum('fajr', ['jamaah', 'present', 'qada', 'none'])->default('none');
            $table->enum('fajr_sunnah', ['present', 'qada', 'none'])->default('none');
            $table->enum('dhuhr', ['jamaah', 'present', 'qada', 'none'])->default('none');
            $table->enum('dhuhr_sunnah', ['present', 'qada', 'none'])->default('none');
            $table->enum('asr', ['jamaah', 'present', 'qada', 'none'])->default('none');
            $table->enum('maghrib', ['jamaah', 'present', 'qada', 'none'])->default('none');
            $table->enum('maghrib_sunnah', ['present', 'qada', 'none'])->default('none');
            $table->enum('isha', ['jamaah', 'present', 'qada', 'none'])->default('none');
            $table->enum('isha_sunnah', ['present', 'qada', 'none'])->default('none');

            // Athkar & Others
            $table->unsignedInteger('istighfar_count')->default(0);
            $table->unsignedInteger('tahleel_count')->default(0);
            $table->unsignedInteger('salat_ala_nabi_count')->default(0);
            $table->unsignedTinyInteger('parents_evaluation')->default(0)->comment('Rating 0 to 10');
            $table->integer('charity_amount')->default(0);
            $table->unsignedInteger('quran_pages_count')->default(0);
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('prayer_program_id')->references('id')->on('prayer_programs')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('daora_id')->references('id')->on('daoras')->onDelete('cascade');

            // Indexes & Constraints
            $table->unique(['student_id', 'prayer_program_id', 'date'], 'student_program_date_unique');
            $table->index(['student_id', 'prayer_program_id']);
            $table->index(['student_id', 'date']);
            $table->index(['daora_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_logs');
    }
};
