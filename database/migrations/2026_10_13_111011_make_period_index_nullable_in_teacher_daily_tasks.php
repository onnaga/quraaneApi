<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            $table->index('teacher_id', 'idx_teacher_id');
        });

        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            $table->dropUnique('unique_teacher_task_period');
            $table->integer('period_index')->nullable()->change();
            $table->unique(['teacher_id', 'task_date', 'daora_period_id'], 'unique_teacher_task_daora_period');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            $table->dropUnique('unique_teacher_task_daora_period');
            $table->integer('period_index')->nullable(false)->change();
            $table->unique(['teacher_id', 'task_date', 'period_index'], 'unique_teacher_task_period');
            $table->dropIndex('idx_teacher_id');
        });
    }
};
