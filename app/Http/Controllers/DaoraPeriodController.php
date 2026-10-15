<?php

namespace App\Http\Controllers;

use App\Models\Daora;
use App\Models\DaoraPeriod;
use App\Models\Halaka;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DaoraPeriodController extends Controller
{
    private const PRIVILEGE_ADMIN = 4;

    /**
     * GET /daora-periods/{daora_id}
     * جلب جميع فترات الدورة مع الأساتذة المعينين.
     */
    public function index(int $daoraId): JsonResponse
    {
        $periods = DaoraPeriod::where('daora_id', $daoraId)
            ->with(['teachers:id,name,phone_number,photo'])
            ->withCount('teachers')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $periods,
        ]);
    }

    /**
     * GET /daora-periods/details/{id}
     * تفاصيل فترة واحدة.
     */
    public function show(int $id): JsonResponse
    {
        $period = DaoraPeriod::with(['teachers:id,name,phone_number,photo', 'daora:id,name'])
            ->withCount('teachers')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $period,
        ]);
    }

    /**
     * POST /daora-periods
     * إنشاء فترة جديدة للدورة.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'daora_id'      => 'required|exists:daoras,id',
            'period_name'   => 'nullable|string|max:255',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i',
            'days_of_week'  => 'required|array',
            'days_of_week.*'=> 'integer|between:1,7',
            'is_active'     => 'nullable|boolean',
            'teacher_ids'   => 'nullable|array',
            'teacher_ids.*' => 'exists:users,id',
        ]);

        $this->authorizeDaoraAdmin((int) $request->daora_id);

        $period = DaoraPeriod::create([
            'daora_id'     => $request->daora_id,
            'period_name'  => $request->period_name ?? 'فترة جديدة',
            'start_time'   => $request->start_time,
            'end_time'     => $request->end_time,
            'days_of_week' => $request->days_of_week,
            'is_active'    => $request->boolean('is_active', true),
        ]);

        if ($request->has('teacher_ids') && is_array($request->teacher_ids)) {
            $period->teachers()->sync($request->teacher_ids);
        }

        $period->load('teachers:id,name,phone_number,photo');
        $period->loadCount('teachers');

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الفترة بنجاح',
            'data'    => $period,
        ], 201);
    }

    /**
     * PUT /daora-periods/{id}
     * تعديل بيانات فترة.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $period = DaoraPeriod::findOrFail($id);
        $this->authorizeDaoraAdmin($period->daora_id);

        $request->validate([
            'period_name'   => 'nullable|string|max:255',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'days_of_week'  => 'nullable|array',
            'days_of_week.*'=> 'integer|between:1,7',
            'is_active'     => 'nullable|boolean',
        ]);

        $period->update($request->only([
            'period_name',
            'start_time',
            'end_time',
            'days_of_week',
            'is_active',
        ]));

        $period->load('teachers:id,name,phone_number,photo');
        $period->loadCount('teachers');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الفترة بنجاح',
            'data'    => $period,
        ]);
    }

    /**
     * DELETE /daora-periods/{id}
     * حذف فترة.
     */
    public function destroy(int $id): JsonResponse
    {
        $period = DaoraPeriod::findOrFail($id);
        $this->authorizeDaoraAdmin($period->daora_id);

        $period->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الفترة بنجاح',
        ]);
    }

    /**
     * POST /daora-periods/{id}/teachers
     * تعيين أساتذة لفترة محددة.
     */
    public function assignTeachers(Request $request, int $id): JsonResponse
    {
        $period = DaoraPeriod::findOrFail($id);
        $this->authorizeDaoraAdmin($period->daora_id);

        $request->validate([
            'teacher_ids'   => 'required|array',
            'teacher_ids.*' => 'exists:users,id',
        ]);

        $period->teachers()->syncWithoutDetaching($request->teacher_ids);

        $period->load('teachers:id,name,phone_number,photo');
        $period->loadCount('teachers');

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين الأساتذة بنجاح',
            'data'    => $period,
        ]);
    }

    /**
     * DELETE /daora-periods/{id}/teachers/{teacher_id}
     * إزالة أستاذ من فترة.
     */
    public function removeTeacher(int $id, int $teacherId): JsonResponse
    {
        $period = DaoraPeriod::findOrFail($id);
        $this->authorizeDaoraAdmin($period->daora_id);

        $period->teachers()->detach($teacherId);

        $period->load('teachers:id,name,phone_number,photo');
        $period->loadCount('teachers');

        return response()->json([
            'success' => true,
            'message' => 'تمت إزالة الأستاذ من الفترة بنجاح',
            'data'    => $period,
        ]);
    }

    /**
     * GET /daora-periods/{id}/available-teachers
     * جلب الأساتذة المتاحين في الدورة ولم يتم تعيينهم بعد لهذه الفترة.
     */
    public function availableTeachers(int $id): JsonResponse
    {
        $period = DaoraPeriod::findOrFail($id);
        $daoraId = $period->daora_id;

        // أساتذة الدورة من جدول users وحلقات الدورة
        $halakaTeacherIds = Halaka::where('daora_id', $daoraId)
            ->pluck('teacher_id')
            ->unique()
            ->toArray();

        // الأساتذة المعينون حالياً لهذه الفترة
        $assignedTeacherIds = $period->teachers()->pluck('users.id')->toArray();

        $availableTeachers = User::where(function ($q) use ($daoraId, $halakaTeacherIds) {
                $q->where('daora_id', $daoraId)
                  ->orWhereIn('id', $halakaTeacherIds);
            })
            ->where('privilege', '>=', 2)
            ->whereNotIn('id', $assignedTeacherIds)
            ->select('id', 'name', 'phone_number', 'photo')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $availableTeachers,
        ]);
    }

    /**
     * التحقق من صلاحية مدير الدورة أو المسؤول العام.
     */
    private function authorizeDaoraAdmin(int $daoraId): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'غير مصرح');
        }

        if ($user->privilege >= self::PRIVILEGE_ADMIN) {
            return;
        }

        $daora = Daora::find($daoraId);
        if (!$daora || $daora->admin_id !== $user->id) {
            abort(403, 'فقط مدير الدورة يمكنه إدارة الفترات');
        }
    }
}
