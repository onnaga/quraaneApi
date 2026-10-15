<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrayerProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'daora_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }

    public function logs()
    {
        return $this->hasMany(PrayerLog::class, 'prayer_program_id');
    }
}
