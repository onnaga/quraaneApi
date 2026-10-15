<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentUser extends Model
{
    protected $table = 'tournament_user';

    protected $fillable = [
        'tournament_id',
        'user_id',
        'points',
        'wins',
        'losses',
        'draws',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
