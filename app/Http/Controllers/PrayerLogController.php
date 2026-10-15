<?php

namespace App\Http\Controllers;

use App\Models\PrayerLog;
use App\Models\PrayerProgram;
use App\Models\student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PrayerLogController extends Controller
{
    /**
     * Get Rankings for a program.
     * Returns ALL students in the daora (even with 0 points), plus user_id for client-side matching.
     */
    public function getRankings($program_id)
    {
        $program = PrayerProgram::findOrFail($program_id);

        // Get all students in this daora
        $allStudents = student::where('students.id', '>', 0)
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('users.daora_id', $program->daora_id)
            ->select('students.id as student_id', 'users.id as user_id', 'users.name')
            ->get();

        // Get points from logs
        $logs = PrayerLog::where('prayer_program_id', $program_id)->get();
        $studentsPoints = [];
        foreach ($logs as $log) {
            if (!isset($studentsPoints[$log->student_id])) {
                $studentsPoints[$log->student_id] = 0;
            }
            $studentsPoints[$log->student_id] += $log->points;
        }

        // Build rankings with all students
        $rankings = [];
        foreach ($allStudents as $stu) {
            $rankings[] = [
                'student_id' => $stu->student_id,
                'user_id' => $stu->user_id,
                'name' => $stu->name,
                'total_points' => $studentsPoints[$stu->student_id] ?? 0,
            ];
        }

        // Sort descending by points
        usort($rankings, fn($a, $b) => $b['total_points'] <=> $a['total_points']);

        return response()->json([
            'success' => true,
            'rankings' => $rankings,
        ]);
    }

    /**
     * Get My Logs (for students accessing their own logs)
     */
    public function getMyLogs($program_id)
    {
        $user = auth()->user();

        $student = student::where('user_id', $user->id)->first();
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student record not found'], 404);
        }

        $logs = PrayerLog::where('prayer_program_id', $program_id)
            ->where('student_id', $student->id)
            ->orderBy('date', 'desc')
            ->get();

        $total_points = $logs->sum('points');

        return response()->json([
            'success' => true,
            'total_points' => $total_points,
            'logs' => $logs,
        ]);
    }

    /**
     * Get a specific student's logs (for teachers/admins)
     */
    public function getStudentLogs($program_id, $student_id)
    {
        $user = auth()->user();
        if ($user->privilege <= 1) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $logs = PrayerLog::where('prayer_program_id', $program_id)
            ->where('student_id', $student_id)
            ->orderBy('date', 'desc')
            ->get();

        $total_points = $logs->sum('points');

        $studentInfo = student::where('students.id', $student_id)
            ->join('users', 'students.user_id', '=', 'users.id')
            ->select('students.id as student_id', 'users.name')
            ->first();

        return response()->json([
            'success' => true,
            'total_points' => $total_points,
            'student_name' => $studentInfo ? $studentInfo->name : '',
            'logs' => $logs,
        ]);
    }

    /**
     * Upsert Log (Create or Update).
     * Students can modify their own logs.
     * Teachers (privilege > 1) can modify any student's log by passing student_id.
     */
    public function upsertLog(Request $request, $program_id)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'student_id' => 'nullable|integer',
            'fajr' => 'nullable|in:jamaah,present,qada,none,empty',
            'fajr_sunnah' => 'nullable|in:present,qada,none,empty',
            'dhuhr' => 'nullable|in:jamaah,present,qada,none,empty',
            'dhuhr_sunnah' => 'nullable|in:present,qada,none,empty',
            'asr' => 'nullable|in:jamaah,present,qada,none,empty',
            'maghrib' => 'nullable|in:jamaah,present,qada,none,empty',
            'maghrib_sunnah' => 'nullable|in:present,qada,none,empty',
            'isha' => 'nullable|in:jamaah,present,qada,none,empty',
            'isha_sunnah' => 'nullable|in:present,qada,none,empty',
            'istighfar_count' => 'nullable|integer|min:0',
            'tahleel_count' => 'nullable|integer|min:0',
            'salat_ala_nabi_count' => 'nullable|integer|min:0',
            'parents_evaluation' => 'nullable|integer|min:0|max:10',
            'charity_amount' => 'nullable|integer|min:0',
            'quran_pages_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Determine target student
        if ($user->privilege > 1 && $request->has('student_id')) {
            // Teacher editing a student's log
            $targetStudentId = $request->student_id;
            $targetStudent = student::find($targetStudentId);
            if (!$targetStudent) {
                return response()->json(['success' => false, 'message' => 'الطالب غير موجود'], 404);
            }
        } else {
            // Student editing their own log
            $targetStudent = student::where('user_id', $user->id)->first();
            if (!$targetStudent) {
                return response()->json(['success' => false, 'message' => 'سجل الطالب غير موجود'], 404);
            }
            $targetStudentId = $targetStudent->id;
        }

        $targetDate = Carbon::parse($request->date)->startOfDay();
        $now = Carbon::now()->startOfDay();
        $diffDays = $now->diffInDays($targetDate, false);

        if ($diffDays > 0) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك إضافة سجل لتاريخ قادم.'], 400);
        }
        if ($diffDays < -7) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك تعديل سجل مضى عليه أكثر من 7 أيام.'], 400);
        }

        $program = PrayerProgram::findOrFail($program_id);

        if ($user->privilege <= 1) {
            $programEndDate = Carbon::parse($program->end_date)->endOfDay();
            if (Carbon::now()->isAfter($programEndDate)) {
                return response()->json(['success' => false, 'message' => 'لقد انتهت مهلة هذا البرنامج ولا يمكنك التعديل عليه.'], 403);
            }
        }

        $log = PrayerLog::updateOrCreate(
            [
                'prayer_program_id' => $program_id,
                'student_id' => $targetStudentId,
                'date' => $request->date,
            ],
            [
                'daora_id' => $program->daora_id,
                'fajr' => $request->fajr === 'empty' ? null : $request->fajr,
                'fajr_sunnah' => $request->fajr_sunnah === 'empty' ? null : $request->fajr_sunnah,
                'dhuhr' => $request->dhuhr === 'empty' ? null : $request->dhuhr,
                'dhuhr_sunnah' => $request->dhuhr_sunnah === 'empty' ? null : $request->dhuhr_sunnah,
                'asr' => $request->asr === 'empty' ? null : $request->asr,
                'maghrib' => $request->maghrib === 'empty' ? null : $request->maghrib,
                'maghrib_sunnah' => $request->maghrib_sunnah === 'empty' ? null : $request->maghrib_sunnah,
                'isha' => $request->isha === 'empty' ? null : $request->isha,
                'isha_sunnah' => $request->isha_sunnah === 'empty' ? null : $request->isha_sunnah,
                'istighfar_count' => $request->istighfar_count ?? 0,
                'tahleel_count' => $request->tahleel_count ?? 0,
                'salat_ala_nabi_count' => $request->salat_ala_nabi_count ?? 0,
                'parents_evaluation' => $request->parents_evaluation,
                'charity_amount' => $request->charity_amount ?? 0,
                'quran_pages_count' => $request->quran_pages_count ?? 0,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ السجل بنجاح',
            'log' => $log,
        ]);
    }
}
