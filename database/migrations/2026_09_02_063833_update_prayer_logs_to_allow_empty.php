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
        Schema::table('prayer_logs', function (Blueprint $table) {
            $table->string('fajr', 20)->nullable()->default(null)->change();
            $table->string('fajr_sunnah', 20)->nullable()->default(null)->change();
            $table->string('dhuhr', 20)->nullable()->default(null)->change();
            $table->string('dhuhr_sunnah', 20)->nullable()->default(null)->change();
            $table->string('asr', 20)->nullable()->default(null)->change();
            $table->string('maghrib', 20)->nullable()->default(null)->change();
            $table->string('maghrib_sunnah', 20)->nullable()->default(null)->change();
            $table->string('isha', 20)->nullable()->default(null)->change();
            $table->string('isha_sunnah', 20)->nullable()->default(null)->change();

            $table->unsignedTinyInteger('parents_evaluation')->nullable()->default(null)->comment('Rating 0 to 10')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prayer_logs', function (Blueprint $table) {
            // It's hard to revert to enum without knowing previous state and data, but we can try
            // For simplicity, we just leave it as string in down or revert to enum
            $table->enum('fajr', ['jamaah', 'present', 'qada', 'none'])->default('none')->change();
            $table->enum('fajr_sunnah', ['present', 'qada', 'none'])->default('none')->change();
            $table->enum('dhuhr', ['jamaah', 'present', 'qada', 'none'])->default('none')->change();
            $table->enum('dhuhr_sunnah', ['present', 'qada', 'none'])->default('none')->change();
            $table->enum('asr', ['jamaah', 'present', 'qada', 'none'])->default('none')->change();
            $table->enum('maghrib', ['jamaah', 'present', 'qada', 'none'])->default('none')->change();
            $table->enum('maghrib_sunnah', ['present', 'qada', 'none'])->default('none')->change();
            $table->enum('isha', ['jamaah', 'present', 'qada', 'none'])->default('none')->change();
            $table->enum('isha_sunnah', ['present', 'qada', 'none'])->default('none')->change();

            $table->unsignedTinyInteger('parents_evaluation')->default(0)->change();
        });
    }
};
