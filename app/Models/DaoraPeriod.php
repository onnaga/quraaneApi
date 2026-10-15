<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaoraPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'daora_id',
        'period_name',
        'start_time',
        'end_time',
        'days_of_week',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'is_active'    => 'boolean',
    ];

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }

    /**
     * الأساتذة المعينون لهذه الفترة.
     */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'daora_period_teacher', 'daora_period_id', 'teacher_id')
                    ->withTimestamps();
    }

    /**
     * المهام اليومية المرتبطة بهذه الفترة.
     */
    public function dailyTasks()
    {
        return $this->hasMany(TeacherDailyTask::class, 'daora_period_id');
    }
}
