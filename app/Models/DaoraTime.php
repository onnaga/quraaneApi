<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaoraTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'daora_id',
        'days_of_week',
        'start_time_1', 'end_time_1',
        'start_time_2', 'end_time_2',
        'start_time_3', 'end_time_3',
    ];

    protected $casts = [
        'days_of_week' => 'array',
    ];

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }
}
