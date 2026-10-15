<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'teacher_id',
        'category',
        'goal_text',
        'status',
        'comments'
    ];

    /**
     * Get the student that owns the goal.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the teacher who set the goal.
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
