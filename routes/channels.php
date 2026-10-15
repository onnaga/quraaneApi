<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('presence-online', function ($user) {
    if ($user) {
        return ['id' => $user->id, 'name' => $user->name, 'daora_id' => $user->daora_id];
    }
});

Broadcast::channel('chess-match.{matchId}', function ($user, $matchId) {
    return ['id' => $user->id, 'name' => $user->name];
});
