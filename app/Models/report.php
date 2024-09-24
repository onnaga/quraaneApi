<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class report extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'teacher_id',
        'ended_quraan_this_course',
        'ended_hadith_this_course',
        'activitis_this_course',
        'notes'
    ];
}
