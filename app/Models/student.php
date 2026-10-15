<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class student extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'teacher_id',
        'point_id',
        'latest_id',
        'ended_quraan_in_aukaf',
        'missing_days',
        'last_attendance_status',
        'current_grade', 'school_name', 'father_phone', 'mother_phone', 'landline_phone', 'other_phone', 'preferred_contact', 'previous_mosques',
    ];
}
