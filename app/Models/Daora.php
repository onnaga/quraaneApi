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
            $images = app(\App\Services\ImageStorageService::class);
            if ($daora->photo) {
                $images->delete('uploadsDaoras', $daora->photo);
            }

            // حذف صور الطلاب المرتبطين بالدورة
            $students = User::where('daora_id', $daora->id)->get();
            foreach ($students as $student) {
                if ($student->photo) {
                    $images->delete('uploadsUser', $student->photo);
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

    protected $appends = ['photo_url', 'thumb_url'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? url('media/uploadsDaoras/' . $this->photo) : null;
    }

    public function getThumbUrlAttribute()
    {
        return $this->photo ? url('media/uploadsDaoras/thumb_' . $this->photo) : null;
    }

    /**
     * فترات الدورة المرنة.
     */
    public function periods()
    {
        return $this->hasMany(DaoraPeriod::class, 'daora_id');
    }
}
