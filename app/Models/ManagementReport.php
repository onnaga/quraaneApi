<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagementReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'author_id',
        'daora_id',
        'halaka_id',
        'statistics_snapshot',
        'field_notes',
        'students_without_homework',
        'absent_students_justifications',
        'media_file_notes',
        'admin_file_notes',
        'events_file_notes',
        'logistics_file_notes',
        'teachers_notes',
        'general_notes',
        'notes_for_management',
        'status',
        'requested_by',
    ];

    protected $casts = [
        'statistics_snapshot' => 'array',
        'field_notes' => 'array',
        'students_without_homework' => 'array',
        'absent_students_justifications' => 'array',
        'teachers_notes' => 'array',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function daora()
    {
        return $this->belongsTo(Daora::class, 'daora_id');
    }

    public function halaka()
    {
        return $this->belongsTo(Halaka::class, 'halaka_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
