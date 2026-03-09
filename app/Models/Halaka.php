<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Halaka extends Model
{
    protected $fillable = [
        'name',
        'teacher_id',
        'daora_id',
        'students_count',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }
}
