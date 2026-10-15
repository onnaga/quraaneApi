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

class ChessMatchEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;

    public function __construct(ChessMatch $match)
    {
        $this->match = $match;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chess-match.' . $this->match->id);
    }
}
