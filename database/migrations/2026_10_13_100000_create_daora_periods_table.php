<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول الفترات المرنة (بديل daora_times الثابتة)
        Schema::create('daora_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daora_id');
            $table->foreign('daora_id')->references('id')->on('daoras')->onDelete('cascade');

            $table->string('period_name')->nullable(); // اسم وصفي مثل "دورة الكبار المسائية"
            $table->time('start_time');
            $table->time('end_time');
            $table->json('days_of_week'); // [1,2,3,4,5]
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // 2. جدول ربط Many-to-Many: فترة ↔ أستاذ
        Schema::create('daora_period_teacher', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daora_period_id');
            $table->foreign('daora_period_id')->references('id')->on('daora_periods')->onDelete('cascade');

            $table->unsignedBigInteger('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['daora_period_id', 'teacher_id'], 'unique_period_teacher');
            $table->timestamps();
        });

        // 3. إضافة daora_period_id إلى teacher_daily_tasks
        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('daora_period_id')->nullable()->after('daora_id');
            $table->foreign('daora_period_id')->references('id')->on('daora_periods')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            $table->dropForeign(['daora_period_id']);
            $table->dropColumn('daora_period_id');
        });

        Schema::dropIfExists('daora_period_teacher');
        Schema::dropIfExists('daora_periods');
    }
};
