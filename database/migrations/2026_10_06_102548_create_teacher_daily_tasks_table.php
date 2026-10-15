<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_daily_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unsignedBigInteger('daora_id');
            $table->foreign('daora_id')->references('id')->on('daoras')->onDelete('cascade');
            
            $table->date('task_date'); // تاريخ المهمة
            $table->integer('period_index'); // أي فترة (1, 2, أو 3)
            
            // المهام اليومية
            $table->boolean('attendance_taken')->default(false); // تم التفقد
            $table->boolean('quran_tasks_taken')->default(false); // تم إدخال القرآن
            $table->boolean('report_copied')->default(false); // تم نسخ التقرير
            
            // التأخير والإشعارات
            $table->boolean('notified_at_end')->default(false); // اشعار نهاية الوقت
            $table->boolean('late_1_hour')->default(false); // تأخر ساعة
            $table->boolean('notified_1_hour')->default(false);
            $table->boolean('late_2_hours')->default(false); // تأخر ساعتين
            $table->boolean('notified_2_hours')->default(false);
            
            $table->timestamps();
            
            // فهرس مركب لمنع التكرار
            $table->unique(['teacher_id', 'task_date', 'period_index'], 'unique_teacher_task_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_daily_tasks');
    }
};
