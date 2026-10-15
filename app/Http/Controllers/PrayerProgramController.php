<?php

namespace App\Http\Controllers;

use App\Models\PrayerProgram;
use App\Models\User;
use App\Notifications\NewPrayerProgramNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;

class PrayerProgramController extends Controller
{
    public function index(Request $request)
    {
        $daoraId = $request->query('daora_id');
        
        if (!$daoraId) {
            return response()->json(['success' => false, 'message' => 'daora_id is required'], 400);
        }

        $programs = PrayerProgram::where('daora_id', $daoraId)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'programs' => $programs
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->privilege != 3 && $user->privilege != 4) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'daora_id' => 'required|exists:daoras,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Create the program
        $program = PrayerProgram::create([
            'daora_id' => $request->daora_id,
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);

        // Send notifications
        $users = User::where('daora_id', $request->daora_id)->get();
        Notification::send($users, new NewPrayerProgramNotification($program->name));

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء برنامج الصلاة بنجاح',
            'program' => $program
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->privilege != 3 && $user->privilege != 4) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $program = PrayerProgram::find($id);
        if (!$program) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Delete related logs
        $program->logs()->delete();
        
        // Delete program
        $program->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف البرنامج بنجاح']);
    }
}
