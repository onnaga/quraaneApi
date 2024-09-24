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
        'activitis',
        'note',
        'q_homework',
        'h_homework',

    ];
}
