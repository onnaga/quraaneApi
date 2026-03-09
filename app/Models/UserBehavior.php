<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBehavior extends Model
{
    protected $fillable = [
        'user_id',
        'teacher_id',
        'traits',
        'habits',
        'discipline',
        'morals',
        'positive_actions',
        'negative_actions',
    ];
}
