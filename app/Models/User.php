<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject; // تأكد من وجود هذا السطر إذا كنت تستخدم JWT

class User extends Authenticatable implements JWTSubject // تأكد من وجود implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    // Role Constants
    public const ROLE_STUDENT = 1;

    public const ROLE_TEACHER = 2;

    public const ROLE_SUPERVISOR = 3;

    public const ROLE_ADMIN = 4;

    public const ROLE_SUPER_ADMIN = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone_number',
        'password',
        'privilege',
        'age',
        'photo',
        'photo_hash',
        'daora_id',
        'family_status',
        'fcm_token',
        'job_id', // ✅ --- إضافة الحقول الجديدة هنا
        'area_id', // ✅ --- إضافة الحقول الجديدة هنا
        'enrollment_date', // تاريخ الانتساب
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ✅ --- إضافة العلاقات الجديدة ---

    /**
     * Get the job associated with the user.
     */
    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the area associated with the user.
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    // --- نهاية إضافة العلاقات ---

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * تعريف العلاقة بين المستخدم واختباراته.
     * * The tests that belong to the user.
     */
    public function user_tests()
    {
        // المستخدم الواحد يمتلك العديد من سجلات user_test
        // 'user_id' هو المفتاح الأجنبي في جدول user_tests
        // 'id' هو المفتاح الأساسي في جدول users
        return $this->hasMany(user_test::class, 'user_id', 'id');
    }
}
