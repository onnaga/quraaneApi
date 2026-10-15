<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * نقل البيانات من daora_times (الفترات الثابتة) إلى daora_periods (الفترات المرنة).
 * لكل فترة موجودة (1, 2, 3) يتم إنشاء صف في daora_periods،
 * ثم تعيين جميع أساتذة الدورة الحاليين لكل فترة تلقائياً،
 * وأخيراً تحديث teacher_daily_tasks بـ daora_period_id المناسب.
 */
return new class extends Migration
{
    public function up(): void
    {
        $daoraTimes = DB::table('daora_times')->get();

        foreach ($daoraTimes as $dt) {
            $daysOfWeek = $dt->days_of_week; // already JSON string
            $daoraId = $dt->daora_id;

            // جلب جميع أساتذة الدورة من الحلقات
            $teacherIds = DB::table('halakas')
                ->where('daora_id', $daoraId)
                ->pluck('teacher_id')
                ->unique()
                ->values()
                ->toArray();

            $periods = [
                ['index' => 1, 'start' => $dt->start_time_1, 'end' => $dt->end_time_1, 'name' => 'الفترة الأولى'],
                ['index' => 2, 'start' => $dt->start_time_2, 'end' => $dt->end_time_2, 'name' => 'الفترة الثانية'],
                ['index' => 3, 'start' => $dt->start_time_3, 'end' => $dt->end_time_3, 'name' => 'الفترة الثالثة'],
            ];

            foreach ($periods as $period) {
                if (!$period['start'] || !$period['end']) {
                    continue;
                }

                // إنشاء الفترة الجديدة
                $periodId = DB::table('daora_periods')->insertGetId([
                    'daora_id'    => $daoraId,
                    'period_name' => $period['name'],
                    'start_time'  => $period['start'],
                    'end_time'    => $period['end'],
                    'days_of_week' => $daysOfWeek,
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // تعيين جميع أساتذة الدورة لهذه الفترة (سلوك مطابق للوضع القديم)
                foreach ($teacherIds as $teacherId) {
                    DB::table('daora_period_teacher')->insert([
                        'daora_period_id' => $periodId,
                        'teacher_id'      => $teacherId,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }

                // تحديث السجلات اليومية القديمة بـ daora_period_id
                DB::table('teacher_daily_tasks')
                    ->where('daora_id', $daoraId)
                    ->where('period_index', $period['index'])
                    ->update(['daora_period_id' => $periodId]);
            }
        }
    }

    public function down(): void
    {
        // لا يمكن عكس نقل البيانات تلقائياً
        // السجلات في daora_periods ستُحذف بالـ cascade عند حذف الجدول
        DB::table('daora_period_teacher')->truncate();
        DB::table('teacher_daily_tasks')->update(['daora_period_id' => null]);
        DB::table('daora_periods')->truncate();
    }
};
