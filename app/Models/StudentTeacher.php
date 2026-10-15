<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTeacher extends Model
{
    protected $table = 'student_teacher';

    protected $fillable = [
        'teacher_id',
        'student_id',
        'started_at',
        'ended_at',
    ];
}
