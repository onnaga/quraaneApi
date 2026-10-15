<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Daora;
use App\Models\User;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->privilege >= 2) {
            // Teachers/Admins: see all public tournaments, plus private ones from their Daora
            $tournaments = Tournament::with(['admin', 'daora'])
                ->where('is_public', true)
                ->orWhere('daora_id', $user->daora_id)
                ->withCount('subscribedDaoras')
                ->get();
        } else {
            // Students: see private tournaments of their Daora, plus public ones their Daora is subscribed to
            $tournaments = Tournament::with(['admin', 'daora'])
                ->where('daora_id', $user->daora_id)
                ->orWhereHas('subscribedDaoras', function ($query) use ($user) {
                    $query->where('daora_id', $user->daora_id);
                })
                ->withCount('subscribedDaoras')
                ->get();
        }

        $tournaments->map(function ($tournament) use ($user) {
            if ($tournament->daora_id == $user->daora_id) {
                $tournament->is_subscribed_by_me = true;
            } else {
                $tournament->is_subscribed_by_me = $tournament->subscribedDaoras()->where('daoras.id', $user->daora_id)->exists();
            }
            return $tournament;
        });

        return response()->json(['success' => true, 'tournaments' => $tournaments]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->privilege < 2) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_public' => 'boolean',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'allow_teachers' => 'boolean',
        ]);

        $tournament = Tournament::create([
            'name' => $validated['name'],
            'admin_id' => $user->id,
            'daora_id' => $user->daora_id,
            'is_public' => $validated['is_public'] ?? false,
            'allow_teachers' => $validated['allow_teachers'] ?? false,
            'difficulty' => $validated['difficulty'] ?? 'medium',
        ]);

        // Auto-subscribe the creator's daora if it's public
        if ($tournament->is_public) {
            $tournament->subscribedDaoras()->attach($user->daora_id);
        }

        return response()->json(['success' => true, 'tournament' => $tournament]);
    }

    public function subscribe(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->privilege < 2) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tournament = Tournament::findOrFail($id);

        if (!$tournament->is_public) {
            return response()->json(['success' => false, 'message' => 'Cannot subscribe to private tournament'], 400);
        }

        $tournament->subscribedDaoras()->syncWithoutDetaching([$user->daora_id]);

        return response()->json(['success' => true, 'message' => 'Subscribed successfully']);
    }

    public function show($id)
    {
        $tournament = Tournament::with(['admin', 'daora', 'subscribedDaoras'])->findOrFail($id);

        // Get all daoras involved
        $daoraIds = [$tournament->daora_id];
        if ($tournament->is_public) {
            $daoraIds = array_merge($daoraIds, $tournament->subscribedDaoras->pluck('id')->toArray());
        }

        // Get students (and optionally teachers) in these daoras
        $students = User::whereIn('daora_id', $daoraIds)
            ->when(!$tournament->allow_teachers, function ($query) {
                $query->where('privilege', 1);
            }, function ($query) {
                $query->where('privilege', '>=', 1);
            })
            ->with('daora') // To show their daora name
            ->select('id', 'name', 'age', 'photo', 'photo_hash', 'daora_id', 'privilege') // pusher presence handles online status
            ->get();

        // Attach tournament-specific points to each student
        $tournamentPoints = \App\Models\TournamentUser::where('tournament_id', $id)
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $students->each(function ($student) use ($tournamentPoints) {
            $tp = $tournamentPoints->get($student->id);
            $student->tournament_points = $tp ? $tp->points : 0;
            $student->wins = $tp ? $tp->wins : 0;
            $student->losses = $tp ? $tp->losses : 0;
            $student->draws = $tp ? $tp->draws : 0;
        });

        // Sort by tournament points descending
        $students = $students->sortByDesc('tournament_points')->values();

        return response()->json([
            'success' => true,
            'tournament' => $tournament,
            'students' => $students
        ]);
    }
    public function reportOutcome(Request $request, $id)
    {
        $user = auth()->user();
        $outcome = $request->input('outcome'); // 'win', 'loss', or 'draw'
        
        $tournamentUser = \App\Models\TournamentUser::firstOrCreate(
            ['tournament_id' => $id, 'user_id' => $user->id],
            ['points' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0]
        );

        if ($outcome === 'win') {
            $tournamentUser->increment('points', 3);
            $tournamentUser->increment('wins', 1);
            
            $pointsRecord = \App\Models\Point::firstOrCreate(['user_id' => $user->id]);
            $pointsRecord->increment('a_points', 3);
            return response()->json(['success' => true, 'message' => 'Points added for win']);
        } elseif ($outcome === 'draw') {
            $tournamentUser->increment('points', 1);
            $tournamentUser->increment('draws', 1);
            
            $pointsRecord = \App\Models\Point::firstOrCreate(['user_id' => $user->id]);
            $pointsRecord->increment('a_points', 1);
            return response()->json(['success' => true, 'message' => 'Points added for draw']);
        } elseif ($outcome === 'loss') {
            $tournamentUser->increment('losses', 1);
            return response()->json(['success' => true, 'message' => 'Loss recorded']);
        }

        return response()->json(['success' => false, 'message' => 'Invalid outcome']);
    }
}
