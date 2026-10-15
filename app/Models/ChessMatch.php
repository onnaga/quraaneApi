<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChessMatch extends Model
{
    protected $fillable = [
        'tournament_id',
        'white_player_id',
        'black_player_id',
        'winner_id',
        'status',
        'pgn',
        'white_time_left',
        'black_time_left',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function whitePlayer()
    {
        return $this->belongsTo(User::class, 'white_player_id');
    }

    public function blackPlayer()
    {
        return $this->belongsTo(User::class, 'black_player_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
