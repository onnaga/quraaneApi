<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesiredGoal extends Model
{
    protected $fillable = [
        'user_id',
        'teacher_id',
        'quranic_goals',
        'educational_goals',
        'scientific_goals',
        'social_goals',
        'preparation_for_advocacy',
    ];
}
