<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponseTrait;

    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function add_latest_quraan($data, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestQuraan($data, $user_id, $teacher_id);
    }

    public function remove_latest_quraan($sora_data, $item_was_successful, $points_to_remove, $user_id, $mark, $sora_index, $type, $report_quraan, $report, $quraan_item)
    {
        return $this->reportService->removeLatestQuraan($sora_data, $item_was_successful, $points_to_remove, $user_id, $mark, $sora_index, $type, $report_quraan, $report, $quraan_item);
    }

    public function remove_latest_hadith($hadith_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_hadith_list, $report, $hadith_item)
    {
        return $this->reportService->removeLatestHadith($hadith_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_hadith_list, $report, $hadith_item);
    }

    public function remove_latest_activity($activity_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_activity_list, $report, $activity_item)
    {
        return $this->reportService->removeLatestActivity($activity_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_activity_list, $report, $activity_item);
    }

    public function remove_latest_note($note_item, $user_id)
    {
        return $this->reportService->removeLatestNote($note_item, $user_id);
    }

    public function add_latest_hadith($data, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestHadith($data, $user_id, $teacher_id);
    }

    public function add_latest_activity($data, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestActivity($data, $user_id, $teacher_id);
    }

    public function add_latest_note($oneNote, $lost_point, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestNote($oneNote, $lost_point, $user_id, $teacher_id);
    }

    public function show_reports(\Illuminate\Http\Request $request)
    {
        try {
            $requestingUser = Auth::user();

            // ✅ إذا كان المستخدم بصلاحية 4 (مدير عام) وتم إرسال daora_id في الريكويست، نأخذه.
            // غير ذلك، نأخذ الـ daora_id الخاص بالمستخدم نفسه.
            if ($requestingUser->privilege == 4 && $request->filled('daora_id')) {
                $daora_id = $request->daora_id;
            } else {
                $daora_id = $requestingUser->daora_id;
            }

            if (! $daora_id) {
                return $this->errorSimpleMessage('المستخدم الحالي غير مسجل في أي دورة.', 404);
            }

            $reports = $this->reportService->getReportsByDaora($daora_id);

            // للحفاظ على شكل الصنف والواجهة
            return $this->successResponse($reports);

        } catch (\Throwable $th) {
            return $this->successResponse(['error' => 'حدث خطأ غير متوقع في الخادم'], 500);
        }
    }

    public function getComprehensiveReport(Request $request, $user_id)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            // ✅ التعديل هنا: إزالة Carbon\ واستخدام Carbon مباشرة
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // 1. معلومات الطالب الأساسية
            $studentInfo = DB::table('users')->where('id', $user_id)->first();
            if (! $studentInfo) {
                return response()->json(['success' => false, 'message' => 'الطالب غير موجود'], 404);
            }

            // 2. السلوك العام (نجلب أحدث تقييم)
            $behavior = DB::table('user_behaviors')->where('user_id', $user_id)->first();

            // 3. الأهداف المرجوة خلال الفترة
            $goals = DB::table('student_goals')
                ->where('user_id', $user_id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            // 4. سجل الأساتذة (جميع الأساتذة ليتطابق مع الواجهة)
            $studentRecord = DB::table('students')->where('user_id', $user_id)->first();
            $teachers = [];
            if ($studentRecord) {
                $teachers = DB::table('student_teacher')
                    ->join('users', 'student_teacher.teacher_id', '=', 'users.id')
                    ->where('student_teacher.student_id', $studentRecord->id)
                    ->select('users.name as teacher_name', 'student_teacher.started_at', 'student_teacher.ended_at')
                    ->orderBy('student_teacher.started_at', 'desc')
                    ->get();
            }

            // 5. السبورات (الاختبارات) خلال الفترة
            $tests = DB::table('user_tests')
                ->join('tests', 'user_tests.test_id', '=', 'tests.id')
                ->where('user_tests.user_id', $user_id)
                ->whereBetween('tests.End_time', [$startDate, $endDate])
                ->select('user_tests.the_part_to_test_in', 'user_tests.rating', 'tests.End_time')
                ->get();

            // 6. التقارير (الأنشطة والملاحظات) (يتم جلب سجل الطالب الحالي دون تقييد بالوقت لأن هذا السجل مجمع للدورة)
            $reports = DB::table('reports')
                ->where('user_id', $user_id)
                ->select('activities_this_course', 'notes', 'created_at')
                ->get();

            // فك تشفير الـ JSON لتسهيل التعامل معه في فلاتر
            $parsedReports = $reports->map(function ($report) {
                return [
                    // ✅ التعديل هنا أيضاً
                    'date' => Carbon::parse($report->created_at)->format('Y-m-d'),
                    'activities' => json_decode($report->activities_this_course, true),
                    'notes' => json_decode($report->notes, true),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'student_name' => $studentInfo->name,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'behavior' => $behavior,
                    'goals' => $goals,
                    'teachers' => $teachers,
                    'tests' => $tests,
                    'reports' => $parsedReports,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }
}
