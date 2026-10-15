<?php

namespace App\Console\Commands;

use App\Models\DaoraPeriod;
use App\Models\Halaka;
use App\Models\TeacherDailyTask;
use App\Models\User;
use App\Notifications\TeacherTaskNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckDaoraTimes extends Command
{
    protected $signature = 'daora:check-times';
    protected $description = 'Check flexible course periods and generate daily tasks or send notifications for missing tasks';

    public function handle()
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        // يوم الأسبوع (1 = الاثنين, 7 = الأحد)
        $dayOfWeek = $now->dayOfWeekIso;

        $periods = DaoraPeriod::with(['daora', 'teachers'])
            ->where('is_active', true)
            ->get();

        foreach ($periods as $period) {
            $days = $period->days_of_week ?? [];
            if (!in_array($dayOfWeek, $days)) {
                continue; // اليوم ليس من أيام هذه الفترة
            }

            $this->processPeriod($period, $today, $now);
        }

        $this->info('Daora periods checked successfully.');
    }

    private function processPeriod(DaoraPeriod $period, string $today, Carbon $now)
    {
        $startTime = $period->start_time;
        $endTime   = $period->end_time;

        if (!$startTime || !$endTime) {
            return;
        }

        $start = Carbon::parse($today . ' ' . $startTime);
        $end   = Carbon::parse($today . ' ' . $endTime);

        $diffMinutesStart = $now->diffInMinutes($start, false);
        $diffMinutesEnd   = $now->diffInMinutes($end, false); // if > 0, now is before end. if < 0, now is after end.

        $daoraId = $period->daora_id;
        $adminId = $period->daora?->admin_id;

        // جلب الأساتذة المعينين لهذه الفترة
        $teachers = $period->teachers;
        if ($teachers->isEmpty()) {
            // في حال عدم تعيين أساتذة للفترة بعد، نأخذ أساتذة الحلقات للدورة كاحتياط
            $teacherIds = Halaka::where('daora_id', $daoraId)->pluck('teacher_id')->unique();
            $teachers = User::whereIn('id', $teacherIds)->get();
        }

        // 1. عند أو بعد البداية: إنشاء المهام (تُنشأ مرة واحدة فقط)
        if ($diffMinutesStart <= 0) {
            foreach ($teachers as $teacher) {
                // تثبيت السجلات السابقة غير المكتملة كفترات مفقودة
                $this->finalizePreviousUncompletedTasks($teacher->id, $daoraId, $period, $today, $now);

                TeacherDailyTask::firstOrCreate([
                    'teacher_id'      => $teacher->id,
                    'daora_id'        => $daoraId,
                    'daora_period_id' => $period->id,
                    'task_date'       => $today,
                ], [
                    'period_end_time' => $endTime,
                ]);
            }
        }

        // 2. بعد انتهاء الفترة
        if ($diffMinutesEnd < 0) {
            $minutesPassed = abs($diffMinutesEnd);
            $periodName = $period->period_name ? " ({$period->period_name})" : '';

            foreach ($teachers as $teacher) {
                $task = TeacherDailyTask::where('teacher_id', $teacher->id)
                    ->where('task_date', $today)
                    ->where('daora_period_id', $period->id)
                    ->first();

                if (!$task) {
                    continue;
                }

                // تحديث وقت انتهاء الفترة إذا لم يكن محفوظاً
                if (!$task->period_end_time) {
                    $task->update(['period_end_time' => $endTime]);
                }

                $missing = [];
                if (!$task->attendance_taken) {
                    $missing[] = 'الغياب والحضور';
                }
                if (!$task->quran_tasks_taken) {
                    $missing[] = 'الإنجازات';
                }
                if (!$task->report_copied) {
                    $missing[] = 'نسخ التقرير وإرساله';
                }

                if (empty($missing)) {
                    continue; // أكمل كل المهام
                }

                $missingStr = implode('، ', $missing);

                // أ. الإشعار الفوري بعد النهاية
                if ($minutesPassed >= 0 && !$task->notified_at_end) {
                    $msg = "لقد انتهى وقت الدورة{$periodName} نرجو إدخال بيانات ($missingStr) لجميع الطلاب. سيتم إشعار المدير في حال لم يتم إنجاز المهام. تقبل الله منا ومنكم.";
                    $teacher->notify(new TeacherTaskNotification($msg));
                    $task->update(['notified_at_end' => true]);
                }

                // ب. الإشعار بعد ساعة
                if ($minutesPassed >= 60 && !$task->notified_1_hour) {
                    $msg = "تذكير: لم تقم بإدخال ($missingStr) بعد مرور ساعة على انتهاء الدورة{$periodName}.";
                    $teacher->notify(new TeacherTaskNotification($msg));
                    $task->update(['late_1_hour' => true, 'notified_1_hour' => true]);
                }

                // ج. الإشعار لمدير الدورة بعد ساعتين
                if ($minutesPassed >= 120 && !$task->notified_2_hours) {
                    if ($adminId) {
                        $admin = User::find($adminId);
                        if ($admin) {
                            $adminMsg = "الأستاذ {$teacher->name} متأخر بأكثر من ساعتين عن إدخال ($missingStr) لدورة{$periodName}.";
                            $admin->notify(new TeacherTaskNotification($adminMsg));
                        }
                    }
                    $task->update(['late_2_hours' => true, 'notified_2_hours' => true]);
                }
            }
        }
    }

    /**
     * تثبيت السجلات السابقة غير المكتملة كفترات مفقودة.
     *
     * 1. أي سجل من أيام سابقة لم يُنسخ فيه التقرير يُعتبر مفقوداً ويُسجل عليه التأخير.
     * 2. أي سجل من نفس اليوم لفترة سابقة انتهت قبل بدء الفترة الحالية ولم يُنسخ فيه التقرير،
     *    يُعتبر قد فاتت مهلته بانطلاق الفترة الجديدة، ويُثبَّت فوراً مع احتساب ساعات التأخير.
     */
    private function finalizePreviousUncompletedTasks(int $teacherId, int $daoraId, DaoraPeriod $currentPeriod, string $today, Carbon $now): void
    {
        // 1. مهام الأيام السابقة
        $oldTasks = TeacherDailyTask::where('teacher_id', $teacherId)
            ->where('daora_id', $daoraId)
            ->where('task_date', '<', $today)
            ->where('report_copied', false)
            ->whereNull('delay_hours') // لم يُثبَّت بعد
            ->get();

        foreach ($oldTasks as $oldTask) {
            $delayHours = 2.0;

            if ($oldTask->period_end_time) {
                $endDateTime = Carbon::parse(
                    $oldTask->task_date->toDateString() . ' ' . $oldTask->period_end_time
                );
                $graceEnd = $endDateTime->copy()->addHours(2);

                if ($now->greaterThan($graceEnd)) {
                    $delayHours = round($now->diffInMinutes($graceEnd) / 60, 1);
                }
            }

            $oldTask->update([
                'delay_hours'  => max(2.0, $delayHours),
                'late_1_hour'  => true,
                'late_2_hours' => true,
            ]);
        }

        // 2. مهام الفترات السابقة من نفس اليوم
        $sameDayPriorTasks = TeacherDailyTask::where('teacher_id', $teacherId)
            ->where('daora_id', $daoraId)
            ->where('task_date', $today)
            ->where('daora_period_id', '!=', $currentPeriod->id)
            ->where('report_copied', false)
            ->whereNull('delay_hours')
            ->get();

        foreach ($sameDayPriorTasks as $priorTask) {
            if ($priorTask->period_end_time && $currentPeriod->start_time) {
                $prevEndTime   = Carbon::parse($today . ' ' . $priorTask->period_end_time);
                $currStartTime = Carbon::parse($today . ' ' . $currentPeriod->start_time);

                // إذا كانت الفترة السابقة انتهت قبل أو عند وقت بدء الفترة الحالية
                if ($prevEndTime->lessThanOrEqualTo($currStartTime)) {
                    // حساب التأخير من وقت نهاية الفترة السابقة حتى بداية الفترة الحالية
                    $diffMinutes = max(0, $currStartTime->diffInMinutes($prevEndTime));
                    $delayHours  = round($diffMinutes / 60, 1);

                    $priorTask->update([
                        'delay_hours'  => max(1.0, $delayHours), // على الأقل ساعة تأخير لأن مهلتها انتهت بدخول دورة جديدة
                        'late_1_hour'  => true,
                        'late_2_hours' => $delayHours >= 2.0,
                    ]);
                }
            }
        }
    }
}
