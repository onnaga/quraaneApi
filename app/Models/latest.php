<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class latest extends Model
{
    use HasFactory;

    protected $fillable = [
        'quran',
        'hadith',
        'activities',
        'note',
        'q_homework',
        'h_homework',
        'achievement_date',
        'student_user_id',
    ];

    protected $casts = [
        'achievement_date' => 'date:Y-m-d',
    ];

    /**
     * العلاقة مع المستخدم (الطالب).
     */
    public function student()
    {
        return $this->belongsTo(\App\Models\User::class, 'student_user_id');
    }
}
