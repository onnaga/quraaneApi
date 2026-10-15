<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'admin_id',
        'daora_id',
        'is_public',
        'allow_teachers',
        'status',
        'difficulty',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function daora()
    {
        return $this->belongsTo(Daora::class);
    }

    public function subscribedDaoras()
    {
        return $this->belongsToMany(Daora::class, 'daora_tournament');
    }

    public function matches()
    {
        return $this->hasMany(ChessMatch::class);
    }
}
