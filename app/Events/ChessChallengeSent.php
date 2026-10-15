<?php

namespace App\Events;

use App\Models\ChessMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChessChallengeSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;

    public function __construct(ChessMatch $match)
    {
        $this->match = $match;
    }

    public function broadcastOn()
    {
        // Broadcast to the opponent
        return new PrivateChannel('App.Models.User.' . $this->match->black_player_id);
    }
}
