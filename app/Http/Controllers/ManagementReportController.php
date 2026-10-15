<?php

namespace App\Http\Controllers;

use App\Models\Daora;
use App\Models\DaoraTime;
use App\Models\Halaka;
use App\Models\ManagementReport;
use App\Models\ReportRequest;
use App\Models\TeacherDailyTask;
use App\Models\User;
use App\Notifications\ReportRequestNotification;
use App\Notifications\ReportSharedNotification;
use App\Services\ManagementReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * وحدة تحكم التقارير الإدارية.
 *
 * تتعامل مع: الإحصائيات، حفظ/مشاركة التقارير، تصفح التقارير،
 * طلبات التقارير، أوقات الدورة، والمهام اليومية.
 */
class ManagementReportController extends Controller implements HasMiddleware
{
    private ManagementReportService $reportService;

    /** الحد الأدنى لكل صلاحية. */
    private const PRIVILEGE_TEACHER    = 2;
    private const PRIVILEGE_SUPERVISOR = 3;
    private const PRIVILEGE_ADMIN      = 4;

    /** العلاقات المُحمَّلة مسبقاً عند تصفح التقارير. */
    private const REPORT_RELATIONS = ['author:id,name', 'daora:id,name', 'halaka:id,name'];

    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }

    public function __construct()
    {
        $this->reportService = new ManagementReportService();
    }

    // =============================================
    //  جلب الإحصائيات
    // =============================================

    /**
     * GET /management-reports/teacher-stats/{teacher_id}
     */
    public function getTeacherStats(int $teacherId): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_TEACHER);

        try {
            return response()->json($this->reportService->getTeacherStats($teacherId));
        } catch (\Throwable $e) {
            Log::error('getTeacherStats error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('خطأ في جلب الإحصائيات: ' . $e->getMessage());
        }
    }

    /**
     * GET /management-reports/mosque-stats/{daora_id}
     */
    public function getMosqueStats(int $daoraId): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_SUPERVISOR);

        try {
            return response()->json($this->reportService->getMosqueStats($daoraId));
        } catch (\Throwable $e) {
            Log::error('getMosqueStats error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('خطأ في جلب الإحصائيات: ' . $e->getMessage());
        }
    }

    // =============================================
    //  حفظ / مشاركة التقارير
    // =============================================

    /**
     * POST /management-reports
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_TEACHER);

        $request->validate([
            'report_type' => 'required|in:halaka,mosque',
            'daora_id'    => 'required|exists:daoras,id',
            'halaka_id'   => 'nullable|exists:halakas,id',
            'status'      => 'required|in:draft,shared',
        ]);

        $user   = Auth::user();
        $report = ManagementReport::create([
            'report_type'                    => $request->report_type,
            'author_id'                      => $user->id,
            'daora_id'                       => $request->daora_id,
            'halaka_id'                      => $request->halaka_id,
            'statistics_snapshot'            => $request->statistics_snapshot,
            'field_notes'                    => $request->field_notes,
            'students_without_homework'      => $request->students_without_homework,
            'absent_students_justifications' => $request->absent_students_justifications,
            'media_file_notes'               => $request->media_file_notes,
            'admin_file_notes'               => $request->admin_file_notes,
            'events_file_notes'              => $request->events_file_notes,
            'logistics_file_notes'           => $request->logistics_file_notes,
            'teachers_notes'                 => $request->teachers_notes,
            'general_notes'                  => $request->general_notes,
            'notes_for_management'           => $request->notes_for_management,
            'status'                         => $request->status,
            'requested_by'                   => $request->requested_by,
        ]);

        if ($request->status === 'shared') {
            $this->markRequestSubmitted($user->id, $request->requested_by);
            $this->notifyReportShared($user, $request->report_type, $request->daora_id, $request->requested_by);
        }

        return response()->json([
            'message' => $request->status === 'shared' ? 'تم مشاركة التقرير بنجاح' : 'تم حفظ المسودة',
            'report'  => $report,
        ], 201);
    }

    // =============================================
    //  تصفح التقارير
    // =============================================

    /**
     * GET /management-reports/my-reports
     */
    public function myReports(): JsonResponse
    {
        $reports = ManagementReport::where('author_id', Auth::id())
            ->with(['daora:id,name', 'halaka:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($reports);
    }

    /**
     * GET /management-reports/by-teacher/{teacher_id}
     */
    public function byTeacher(int $teacherId): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_SUPERVISOR);

        return response()->json(
            $this->getSharedReports(['author_id' => $teacherId])
        );
    }

    /**
     * GET /management-reports/by-daora/{daora_id}
     */
    public function byDaora(int $daoraId): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_SUPERVISOR);

        return response()->json(
            $this->getSharedReports(['daora_id' => $daoraId])
        );
    }

    /**
     * GET /management-reports/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_TEACHER);

        $report = ManagementReport::with(array_merge(self::REPORT_RELATIONS, ['requester:id,name']))
            ->findOrFail($id);

        $user = Auth::user();
        if ($report->author_id !== $user->id && $report->status !== 'shared' && $user->privilege < self::PRIVILEGE_SUPERVISOR) {
            return response()->json(['message' => 'لا تملك الصلاحية لعرض هذا التقرير'], 403);
        }

        return response()->json($report);
    }

    // =============================================
    //  طلبات التقارير
    // =============================================

    /**
     * POST /report-requests
     */
    public function createRequest(Request $request): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_SUPERVISOR);

        $request->validate([
            'target_ids'   => 'required|array',
            'target_ids.*' => 'exists:users,id',
            'daora_id'     => 'required|exists:daoras,id',
            'request_type' => 'required|in:halaka_report,mosque_report',
        ]);

        $user            = Auth::user();
        $createdRequests = [];

        foreach ($request->target_ids as $targetId) {
            $targetUser = User::find($targetId);

            if (!$targetUser || $targetUser->privilege < self::PRIVILEGE_TEACHER) {
                continue;
            }

            $reportRequest     = ReportRequest::create([
                'requester_id' => $user->id,
                'target_id'    => $targetId,
                'daora_id'     => $request->daora_id,
                'request_type' => $request->request_type,
                'status'       => 'pending',
            ]);
            $createdRequests[] = $reportRequest;

            $this->sendReportRequestNotification($targetUser, $user->name, $request->request_type);
        }

        return response()->json([
            'message'  => 'تم إرسال طلبات التقارير بنجاح',
            'requests' => $createdRequests,
        ], 201);
    }

    /**
     * GET /report-requests/pending
     */
    public function pendingRequests(): JsonResponse
    {
        $requests = ReportRequest::where('target_id', Auth::id())
            ->where('status', 'pending')
            ->with(['requester:id,name', 'daora:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }

    // =============================================
    //  أوقات الدورة والمهام اليومية
    // =============================================

    /**
     * POST /daora-times
     */
    public function saveDaoraTimes(Request $request): JsonResponse
    {
        $this->authorize(self::PRIVILEGE_SUPERVISOR);

        $request->validate([
            'daora_id'     => 'required|exists:daoras,id',
            'days_of_week' => 'required|array',
            'start_time_1' => 'nullable|date_format:H:i',
            'end_time_1'   => 'nullable|date_format:H:i',
            'start_time_2' => 'nullable|date_format:H:i',
            'end_time_2'   => 'nullable|date_format:H:i',
            'start_time_3' => 'nullable|date_format:H:i',
            'end_time_3'   => 'nullable|date_format:H:i',
        ]);

        $user  = Auth::user();
        $daora = Daora::find($request->daora_id);

        if ($user->privilege < self::PRIVILEGE_ADMIN && (!$daora || $daora->admin_id !== $user->id)) {
            return response()->json(['message' => 'فقط مدير الدورة يمكنه تعديل أوقات الدورة'], 403);
        }

        $daoraTime = DaoraTime::updateOrCreate(
            ['daora_id' => $request->daora_id],
            $request->only(['days_of_week', 'start_time_1', 'end_time_1', 'start_time_2', 'end_time_2', 'start_time_3', 'end_time_3']),
        );

        return response()->json(['message' => 'تم حفظ أوقات الدورة بنجاح', 'daora_time' => $daoraTime]);
    }

    /**
     * GET /daora-times/{daora_id}
     */
    public function getDaoraTimes(int $daoraId): JsonResponse
    {
        return response()->json(DaoraTime::where('daora_id', $daoraId)->first());
    }

    /**
     * POST /teacher-daily-tasks/mark-copied
     *
     * يبحث عن آخر سجل لم يُنسخ فيه التقرير (اليوم أو الأيام السابقة).
     * يدعم تمرير daora_period_id لتحديد الفترة المطلوبة.
     * لا يُنشئ سجلات جديدة — السجلات تُنشأ فقط بواسطة daora:check-times.
     * يحسب ساعات التأخير الفعلية بعد فترة السماح (ساعتان بعد انتهاء الفترة).
     */
    public function markReportCopied(Request $request): JsonResponse
    {
        $user = Auth::user();
        $now  = Carbon::now();

        $query = TeacherDailyTask::where('teacher_id', $user->id)
            ->where('report_copied', false)
            ->whereNull('delay_hours'); // لا نأخذ المهام التي أُغلقت وثُبِّتت كفترات مفقودة

        if ($request->filled('daora_period_id')) {
            $query->where('daora_period_id', $request->daora_period_id);
            $task = $query->first();
        } else {
            // البحث عن مهمة اليوم الأحدث (الفترة الحالية أو الأقرب لوقت الآن)
            $today = $now->toDateString();
            $task = (clone $query)->where('task_date', $today)
                ->orderByDesc('period_end_time')
                ->first();

            // إذا لم تكن هناك مهمة لليوم، نأخذ أحدث مهمة معلقة مفتوحة
            if (!$task) {
                $task = $query->orderByDesc('task_date')
                    ->orderByDesc('period_end_time')
                    ->first();
            }
        }

        if (!$task) {
            return response()->json(['message' => 'لا توجد مهمة نسخ معلقة'], 404);
        }

        $updateData = [
            'report_copied' => true,
            'copied_at'     => $now,
        ];

        // حساب التأخير إذا تجاوز فترة السماح (ساعتان بعد نهاية الفترة)
        if ($task->period_end_time) {
            $endDateTime = Carbon::parse(
                $task->task_date->toDateString() . ' ' . $task->period_end_time
            );
            $graceEnd    = $endDateTime->copy()->addHours(2);

            if ($now->greaterThan($graceEnd)) {
                $updateData['delay_hours'] = round($now->diffInMinutes($graceEnd) / 60, 1);
            }
        }

        $task->update($updateData);

        return response()->json([
            'message' => 'تم تسجيل المهمة (نسخ التقرير) بنجاح',
            'task'    => $task,
        ]);
    }

    // =============================================
    //  Private Helpers
    // =============================================

    /**
     * التحقق من صلاحية المستخدم الحالي.
     */
    private function authorize(int $minPrivilege): void
    {
        $user = Auth::user();
        if ($user->privilege < $minPrivilege) {
            abort(403, 'لا تملك الصلاحية');
        }
    }

    /**
     * جلب التقارير المُشاركة بشروط معينة.
     */
    private function getSharedReports(array $conditions)
    {
        return ManagementReport::where($conditions)
            ->where('status', 'shared')
            ->with(self::REPORT_RELATIONS)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * تحديث حالة طلب التقرير عند المشاركة.
     */
    private function markRequestSubmitted(int $authorId, ?int $requestedBy): void
    {
        if (!$requestedBy) {
            return;
        }

        ReportRequest::where('target_id', $authorId)
            ->where('requester_id', $requestedBy)
            ->where('status', 'pending')
            ->update(['status' => 'submitted']);
    }

    /**
     * إرسال إشعارات عند مشاركة تقرير.
     */
    private function notifyReportShared($author, string $reportType, int $daoraId, ?int $requestedBy): void
    {
        $notification = new ReportSharedNotification($author->name, $reportType);

        if ($reportType === 'mosque') {
            $recipients = User::where('privilege', '>=', self::PRIVILEGE_ADMIN)->get();
        } elseif ($requestedBy) {
            $recipients = User::where('id', $requestedBy)->get();
        } else {
            $recipients = User::where('privilege', self::PRIVILEGE_SUPERVISOR)
                ->where('daora_id', $daoraId)
                ->get();
        }

        Notification::send($recipients, $notification);
    }

    /**
     * إرسال إشعار طلب تقرير لمستخدم.
     */
    private function sendReportRequestNotification(User $targetUser, string $requesterName, string $requestType): void
    {
        if (!$targetUser->fcm_token) {
            return;
        }

        try {
            $targetUser->notify(new ReportRequestNotification($requesterName, $requestType));
        } catch (\Exception $e) {
            Log::error('فشل إرسال إشعار طلب التقرير: ' . $e->getMessage());
        }
    }

    /**
     * استجابة خطأ موحدة.
     */
    private function errorResponse(string $message, int $status = 500): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
