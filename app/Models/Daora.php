<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class Daora extends Model
{
    use HasFactory;
protected static function booted()
{
    static::deleting(function ($daora) {
        if ($daora->photo) {
            Storage::delete('public/uploadsDaoras/' . $daora->photo);
        }

        // حذف صور الطلاب المرتبطين بالدورة
        $students = User::where('daora_id', $daora->id)->get();
        foreach ($students as $student) {
            if ($student->photo) {
                Storage::delete('public/uploadsUser/' . $student->photo);
            }
        }
    });
}

    protected $fillable = [
        'name',
        'photo',
        'photo_hash',
        'admin_id',
        'number_of_students',
        'showable'


    ];
}
