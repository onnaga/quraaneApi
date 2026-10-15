<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Halaka;

class TeacherDailyTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'daora_id',
        'daora_period_id',
        'task_date',
        'period_index',
        'period_end_time',
        'attendance_taken',
        'quran_tasks_taken',
        'report_copied',
        'notified_at_end',
        'late_1_hour',
        'notified_1_hour',
        'late_2_hours',
        'notified_2_hours',
        'delay_hours',
        'copied_at',
    ];

    protected $casts = [
        'task_date' => 'date',
        'attendance_taken' => 'boolean',
        'quran_tasks_taken' => 'boolean',
        'report_copied' => 'boolean',
        'notified_at_end' => 'boolean',
        'late_1_hour' => 'boolean',
        'notified_1_hour' => 'boolean',
        'late_2_hours' => 'boolean',
        'notified_2_hours' => 'boolean',
        'delay_hours' => 'float',
        'copied_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }

    public function daoraPeriod()
    {
        return $this->belongsTo(DaoraPeriod::class, 'daora_period_id');
    }

    public static function markTaskCompleted($teacherId, $field)
    {
        $today = \Carbon\Carbon::now()->toDateString();
        $tasks = self::where('teacher_id', $teacherId)
            ->where('task_date', $today)
            ->get();
            
        if ($tasks->isNotEmpty()) {
            foreach ($tasks as $task) {
                $task->update([$field => true]);
            }
        } else {
            // Create a backup record in case the cron didn't run or started late
            $user = User::find($teacherId);
            if ($user) {
                $daoraId = $user->daora_id ?? Halaka::where('teacher_id', $teacherId)->value('daora_id');
                if ($daoraId) {
                    self::firstOrCreate([
                        'teacher_id' => $teacherId,
                        'daora_id'   => $daoraId,
                        'task_date'  => $today,
                    ], [
                        $field => true
                    ]);
                }
            }
        }
    }
}
