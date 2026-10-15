<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrayerLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'prayer_program_id',
        'student_id',
        'daora_id',
        'date',
        'fajr',
        'fajr_sunnah',
        'dhuhr',
        'dhuhr_sunnah',
        'asr',
        'maghrib',
        'maghrib_sunnah',
        'isha',
        'isha_sunnah',
        'istighfar_count',
        'tahleel_count',
        'salat_ala_nabi_count',
        'parents_evaluation',
        'charity_amount',
        'quran_pages_count',
    ];

    protected $casts = [
        'date' => 'date',
        'istighfar_count' => 'integer',
        'tahleel_count' => 'integer',
        'salat_ala_nabi_count' => 'integer',
        'parents_evaluation' => 'integer',
        'charity_amount' => 'integer',
        'quran_pages_count' => 'integer',
    ];

    protected $appends = ['points'];

    public function getPointsAttribute()
    {
        $points = 0;
        
        $faridh_points = ['jamaah' => 100, 'present' => 50, 'qada' => 25, 'none' => -25];
        $sunnah_points = ['present' => 30, 'qada' => 15, 'none' => 0];

        foreach(['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'] as $p) {
            $val = $this->$p;
            if ($val !== null && $val !== 'empty') {
                $points += $faridh_points[$val] ?? 0;
            }
        }

        foreach(['fajr_sunnah', 'dhuhr_sunnah', 'maghrib_sunnah', 'isha_sunnah'] as $p) {
            $val = $this->$p;
            if ($val !== null && $val !== 'empty') {
                $points += $sunnah_points[$val] ?? 0;
            }
        }

        $points += floor(($this->istighfar_count ?? 0) / 100) * 25;
        $points += floor(($this->tahleel_count ?? 0) / 100) * 25;
        $points += floor(($this->salat_ala_nabi_count ?? 0) / 100) * 25;

        if ($this->parents_evaluation !== null) {
            if ($this->parents_evaluation >= 8) {
                $points += 100;
            } elseif ($this->parents_evaluation >= 5) {
                $points += 30;
            } else {
                $points -= 100;
            }
        }

        $points += floor(($this->charity_amount ?? 0) / 1000) * 10;
        $points += ($this->quran_pages_count ?? 0) * 10;

        return $points;
    }

    public function program()
    {
        return $this->belongsTo(PrayerProgram::class, 'prayer_program_id');
    }

    public function student()
    {
        return $this->belongsTo(student::class, 'student_id');
    }

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }
}
