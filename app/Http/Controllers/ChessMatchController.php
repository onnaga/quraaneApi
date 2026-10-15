<?php

namespace App\Http\Controllers;

use App\Models\ChessMatch;
use App\Models\Tournament;
use App\Models\TournamentUser;
use App\Models\User;
use Illuminate\Http\Request;

class ChessMatchController extends Controller
{
    public function challenge(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'opponent_id' => 'required|exists:users,id',
        ]);

        $match = ChessMatch::create([
            'tournament_id' => $validated['tournament_id'],
            'white_player_id' => $user->id,
            'black_player_id' => $validated['opponent_id'],
            'status' => 'pending',
            'white_time_left' => 300,
            'black_time_left' => 300,
        ]);

        broadcast(new \App\Events\ChessChallengeSent($match))->toOthers();

        return response()->json(['success' => true, 'match' => $match]);
    }

    public function accept(Request $request, $id)
    {
        $user = auth()->user();
        $match = ChessMatch::findOrFail($id);

        if ($match->black_player_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not authorized to accept this match'], 403);
        }

        $match->update(['status' => 'playing']);

        broadcast(new \App\Events\ChessChallengeAccepted($match))->toOthers();

        return response()->json(['success' => true, 'match' => $match]);
    }

    public function decline(Request $request, $id)
    {
        $user = auth()->user();
        $match = ChessMatch::findOrFail($id);

        if ($match->black_player_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not authorized to decline this match'], 403);
        }

        $match->update(['status' => 'aborted']);

        broadcast(new \App\Events\ChessChallengeDeclined($match))->toOthers();

        return response()->json(['success' => true, 'match' => $match]);
    }

    public function move(Request $request, $id)
    {
        $user = auth()->user();
        $match = ChessMatch::findOrFail($id);

        if ($match->status !== 'playing') {
            return response()->json(['success' => false, 'message' => 'المباراة ليست قيد اللعب'], 400);
        }

        // Verify this user is actually a player in this match
        if ($user->id !== $match->white_player_id && $user->id !== $match->black_player_id) {
            return response()->json(['success' => false, 'message' => 'لست لاعباً في هذه المباراة'], 403);
        }

        $validated = $request->validate([
            'pgn' => 'required|string',
            'white_time_left' => 'required|integer|min:0',
            'black_time_left' => 'required|integer|min:0',
        ]);

        // --- SERVER-SIDE MOVE VALIDATION ---
        try {
            $newChess = new \PChess\Chess\Chess();
            $loadResult = $newChess->loadPgn($validated['pgn']);

            if (!$loadResult) {
                return response()->json(['success' => false, 'message' => 'PGN غير صالح'], 400);
            }

            // Verify the new PGN is exactly one move ahead of the stored PGN
            $oldChess = new \PChess\Chess\Chess();
            $oldMoveCount = 0;
            if (!empty($match->pgn)) {
                $oldChess->loadPgn($match->pgn);
                $oldMoveCount = count($oldChess->history());
            }

            $newMoveCount = count($newChess->history());

            if ($newMoveCount !== $oldMoveCount + 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'عدد الحركات غير متوافق. متوقع حركة واحدة جديدة فقط.'
                ], 400);
            }

            // Verify it's the correct player's turn
            // After loading old PGN, whose turn is it?
            $isWhiteTurn = ($oldMoveCount % 2 === 0); // even = white's turn, odd = black's turn
            if ($isWhiteTurn && $user->id !== $match->white_player_id) {
                return response()->json(['success' => false, 'message' => 'ليس دورك'], 403);
            }
            if (!$isWhiteTurn && $user->id !== $match->black_player_id) {
                return response()->json(['success' => false, 'message' => 'ليس دورك'], 403);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطأ في التحقق من الحركة: ' . $e->getMessage()], 400);
        }
        // --- END VALIDATION ---

        $match->update([
            'pgn' => $validated['pgn'],
            'white_time_left' => $validated['white_time_left'],
            'black_time_left' => $validated['black_time_left'],
        ]);

        // Check for game-ending conditions
        if ($newChess->inCheckmate()) {
            $winnerId = $isWhiteTurn ? $match->white_player_id : $match->black_player_id;
            $match->update(['status' => 'finished', 'winner_id' => $winnerId]);
            $loserId = ($winnerId == $match->white_player_id) ? $match->black_player_id : $match->white_player_id;
            $this->addPoints($match->tournament_id, $winnerId, 10, 'win');
            $this->addPoints($match->tournament_id, $loserId, -5, 'loss');
            broadcast(new \App\Events\ChessMatchEnded($match))->toOthers();
        } elseif ($newChess->inDraw() || $newChess->inStalemate()) {
            $match->update(['status' => 'finished', 'winner_id' => null]);
            $this->addPoints($match->tournament_id, $match->white_player_id, 3, 'draw');
            $this->addPoints($match->tournament_id, $match->black_player_id, 3, 'draw');
            broadcast(new \App\Events\ChessMatchEnded($match))->toOthers();
        } else {
            broadcast(new \App\Events\ChessMatchMoveMade($match))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function end(Request $request, $id)
    {
        $user = auth()->user();
        $match = ChessMatch::findOrFail($id);

        if ($match->status !== 'playing') {
            return response()->json(['success' => false, 'message' => 'المباراة انتهت مسبقاً'], 400);
        }

        // Verify user is a player
        if ($user->id !== $match->white_player_id && $user->id !== $match->black_player_id) {
            return response()->json(['success' => false, 'message' => 'لست لاعباً في هذه المباراة'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:resigned,timeout,aborted',
        ]);

        $winnerId = null;

        if ($validated['status'] === 'resigned') {
            // The user who sent this request is resigning, so the OTHER player wins
            $winnerId = ($user->id == $match->white_player_id)
                ? $match->black_player_id
                : $match->white_player_id;
            $this->addPoints($match->tournament_id, $winnerId, 10, 'win');
            $this->addPoints($match->tournament_id, $user->id, -5, 'loss');
        } elseif ($validated['status'] === 'timeout') {
            // Timeout: the user who sent this request is the one whose time ran out, so they lose
            $winnerId = ($user->id == $match->white_player_id)
                ? $match->black_player_id
                : $match->white_player_id;
            $this->addPoints($match->tournament_id, $winnerId, 10, 'win');
            $this->addPoints($match->tournament_id, $user->id, -5, 'loss');
        }
        // 'aborted' = no points change

        $match->update([
            'status' => 'finished',
            'winner_id' => $winnerId,
        ]);

        broadcast(new \App\Events\ChessMatchEnded($match))->toOthers();

        return response()->json(['success' => true]);
    }

    private function addPoints($tournament_id, $user_id, $points, $resultType = null)
    {
        $record = TournamentUser::firstOrCreate(
            ['tournament_id' => $tournament_id, 'user_id' => $user_id],
            ['points' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0]
        );

        $record->points = max(0, $record->points + $points);

        if ($resultType === 'win') {
            $record->wins += 1;
        } elseif ($resultType === 'loss') {
            $record->losses += 1;
        } elseif ($resultType === 'draw') {
            $record->draws += 1;
        }

        $record->save();
    }
}
