<?php

namespace App\Http\Controllers;

use App\Models\StudentGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentGoalController extends Controller
{
    public function add_student_goals(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'goals' => 'required|array',
            'goals.*.category' => 'required|in:quranic,educational,scientific,social,advocacy',
            'goals.*.goal_text' => 'required|string',
        ]);

        $user = Auth::user();
        if ($user->privilege < 2) {
            return response()->json(['message' => 'لا تملك الصلاحية لإضافة أهداف'], 403);
        }

        $userId = $request->input('user_id');
        $goals = $request->input('goals');

        foreach ($goals as $goalData) {
            StudentGoal::create([
                'user_id' => $userId,
                'teacher_id' => $user->id,
                'category' => $goalData['category'],
                'goal_text' => $goalData['goal_text'],
                'status' => 'pending',
            ]);
        }

        // Notify student here if requested, or handle via specific notification job.
        return response()->json(['message' => 'تم حفظ الأهداف بنجاح']);
    }

    public function get_student_goals(Request $request, $user_id)
    {
        $goals = StudentGoal::where('user_id', $user_id)
            ->with('teacher:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        $active_statuses = ['pending', 'in_progress'];
        $archived_statuses = ['achieved', 'not_achieved'];

        $active_goals = $goals->whereIn('status', $active_statuses)->values();
        $archived_goals = $goals->whereIn('status', $archived_statuses)->values();

        return response()->json([
            'active_goals' => $active_goals,
            'archived_goals' => $archived_goals,
        ]);
    }

    public function evaluate_goal(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,achieved,not_achieved',
            'comments' => 'nullable|string',
        ]);

        $user = Auth::user();
        if ($user->privilege < 2) {
            return response()->json(['message' => 'لا تملك الصلاحية لتقييم الأهداف'], 403);
        }

        $goal = StudentGoal::findOrFail($id);
        $goal->status = $request->input('status');

        if ($request->has('comments')) {
            $goal->comments = $request->input('comments');
        }

        $goal->save();

        return response()->json([
            'message' => 'تم تحديث التقييم بنجاح',
            'goal' => $goal->load('teacher:id,name'),
        ]);
    }

    public function delete_goal($id)
    {
        $user = Auth::user();
        if ($user->privilege < 2) {
            return response()->json(['message' => 'لا تملك الصلاحية لحذف الأهداف'], 403);
        }

        $goal = StudentGoal::findOrFail($id);
        $goal->delete();

        return response()->json(['message' => 'تم حذف الهدف بنجاح']);
    }
}
