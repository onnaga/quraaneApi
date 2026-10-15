<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            // وقت انتهاء الفترة (لحساب التأخير بدقة)
            $table->time('period_end_time')->nullable()->after('period_index');
            // ساعات التأخير الفعلية بعد انقضاء فترة السماح (ساعتان)
            $table->float('delay_hours')->nullable()->default(null)->after('notified_2_hours');
            // وقت نسخ التقرير الفعلي
            $table->timestamp('copied_at')->nullable()->default(null)->after('delay_hours');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_daily_tasks', function (Blueprint $table) {
            $table->dropColumn(['period_end_time', 'delay_hours', 'copied_at']);
        });
    }
};
