<?php

namespace App\Http\Controllers;

use App\Services\LatestService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LatestController extends Controller
{
    protected $latestService;

    public function __construct(LatestService $latestService)
    {
        $this->middleware('auth:api');
        $this->latestService = $latestService;
    }

    public function add_latest_quraan(Request $request, $user_id)
    {
        try {
            $authUser = auth('api')->user();
            if ($authUser->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية اضافة التسميعات '], 403);
            }

            $data = json_decode($request->getContent());
            $this->latestService->addQuraan($data, $user_id, $authUser);

            \App\Models\TeacherDailyTask::markTaskCompleted($authUser->id, 'quran_tasks_taken');

            return response()->json(['updated' => true], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => $e->getMessage(), 'path' => $e->getTrace()], 500);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function delete_latest_quraan(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية حذف التسميعات'], 403);
            }

            $item_to_delete = json_decode($request->getContent());
            if (! $item_to_delete) {
                return response()->json(['message' => 'بيانات الحذف غير صحيحة'], 422);
            }

            $this->latestService->deleteQuraan($item_to_delete, $user_id);

            return response()->json(['deleted' => true, 'message' => 'تم حذف الإنجاز بنجاح'], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error 44444' => $e->getMessage()], 500);
            }
            if ($code == 401) {
                return response()->json(['error' => $e->getMessage()], 401);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function if_latest_delete_from_report($quraan_item, $user_id, $teacher_id)
    {
        // تم نقلها بالكامل إلى الـ Service، لكن مبقاة هنا إذا كان هناك أحد يستدعيها مباشرة
        return $this->latestService->if_latest_delete_from_report($quraan_item, $user_id, $teacher_id, (object) $quraan_item);
    }

    public function delete_latest_hadith(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية الحذف'], 403);
            }

            $item_to_delete = json_decode($request->getContent());
            if (! $item_to_delete) {
                return response()->json(['message' => 'بيانات الحذف غير صحيحة'], 422);
            }

            $this->latestService->deleteHadith($item_to_delete, $user_id);

            return response()->json(['deleted' => true, 'message' => 'تم حذف إنجاز الحديث بنجاح'], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            if ($code == 401) {
                return response()->json(['error' => $e->getMessage()], 401);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function if_latest_delete_from_report_hadith($hadith_item, $user_id, $teacher_id)
    {
        return $this->latestService->if_latest_delete_from_report_hadith($hadith_item, $user_id, $teacher_id);
    }

    public function delete_latest_activity(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية الحذف'], 403);
            }

            $item_to_delete = json_decode($request->getContent());
            if (! $item_to_delete) {
                return response()->json(['message' => 'بيانات الحذف غير صحيحة'], 422);
            }

            $this->latestService->deleteActivity($item_to_delete, $user_id);

            return response()->json(['deleted' => true, 'message' => 'تم حذف النشاط بنجاح'], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            if ($code == 401) {
                return response()->json(['error' => $e->getMessage()], 401);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function if_latest_delete_from_report_activity($activity_item, $user_id, $teacher_id)
    {
        return $this->latestService->if_latest_delete_from_report_activity($activity_item, $user_id, $teacher_id);
    }

    public function delete_latest_note(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية الحذف'], 403);
            }

            $this->latestService->deleteNote($user_id);

            return response()->json(['deleted' => true, 'message' => 'تم حذف الملاحظة بنجاح'], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => 'حدث خطأ غير متوقع أثناء الحذف'], 500);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function add_latest_hadith(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege > 1) {
                $data = json_decode($request->getContent());
                $this->latestService->addHadith($data, $user_id);

                \App\Models\TeacherDailyTask::markTaskCompleted(auth('api')->id(), 'quran_tasks_taken');

                return response()->json([
                    'updated' => true,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'لا تملك صلاحية اضافة تسميعات',
                ], 403);
            }
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => $e->getMessage(), 'paht' => $e->getTrace()], 500);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function add_latest_activity(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege > 1) {
                $content = json_decode($request->getContent(), true);
                
                if (isset($content['activities'])) {
                    $activities_data = $content['activities'];
                    $date = $content['date'] ?? date('Y-m-d');
                } else if (is_array($content) && count($content) == 2 && is_string($content[1])) {
                    $activities_data = $content[0];
                    $date = $content[1];
                } else {
                    $activities_data = $content;
                    $date = date('Y-m-d');
                }

                $this->latestService->addActivity($activities_data, $date, $user_id);

                return response()->json([
                    'latest_created' => true,
                    'updated' => true,
                    'student' => null,
                    'report' => true,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'لا تمتلك صلاحية اضافة نشاطات للطلاب',
                ], 403);
            }
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function add_latest_note(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege > 1) {
                $date = $request->date ?? date('Y-m-d');
                $this->latestService->addNote($request->note, $request->lost_point, $date, $user_id);

                return response()->json([
                    'creted' => true,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'لا تمتلك صلاحية لإضافة آخر الملاحظات',
                ], 403);
            }
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 500) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function get_latest_for_student(Request $request)
    {
        try {
            $authUser = Auth::user();
            $latest = $this->latestService->getLatestForStudent($request->user_id, $authUser);

            return response()->json($latest);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 400) {
                return response()->json([$e->getMessage()]);
            }

            return response()->json(['error' => $e->getMessage(), 'path' => $e->getTrace()], 500);
        }
    }

    public function get_rank_my_group(Request $request)
    {
        try {
            $authUser = Auth::user();
            $students = $this->latestService->getRankMyGroup($authUser);

            return response()->json($students);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'path' => $e->getTrace()], 500);
        }
    }

    public function get_rank_masjed(Request $request)
    {
        try {
            $request->validate([
                'daora_id' => 'required|integer|exists:daoras,id',
            ]);

            $daoraId = $request->input('daora_id');
            $students = $this->latestService->getRankMasjed($daoraId);

            return response()->json($students);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'path' => $e->getTrace(),
            ], 500);
        }
    }

    public function update_halaka_name(Request $request)
    {
        try {
            $request->validate([
                'halaka_id' => 'required|integer|exists:halakas,id',
                'name' => 'nullable|string|max:255',
            ]);

            $authUser = Auth::user();
            if ($authUser->privilege < 2) {
                return response()->json(['message' => 'لا تملك الصلاحية لتعديل اسم الحلقة'], 403);
            }

            $halaka = \App\Models\Halaka::findOrFail($request->halaka_id);

            // Only supervisor (3) or the specific teacher (2) can edit
            if ($authUser->privilege == 2 && $halaka->teacher_id != $authUser->id) {
                return response()->json(['message' => 'لا يمكنك تعديل حلقة أستاذ آخر'], 403);
            }

            $halaka->name = $request->name;
            $halaka->save();

            return response()->json(['message' => 'تم تعديل اسم الحلقة بنجاح', 'halaka' => $halaka], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
public function change_halaka_teacher(Request $request)
    {
        try {
            $request->validate([
                'halaka_id' => 'required|integer|exists:halakas,id',
                'new_teacher_id' => 'required|integer|exists:users,id',
            ]);

            $authUser = auth('api')->user();
            if (! $authUser) {
                return response()->json(['message' => 'غير مصرح'], 401);
            }
            if ($authUser->privilege < 3) {
                return response()->json(['message' => 'لا تملك الصلاحية لتغيير أستاذ الحلقة'], 403);
            }

            $oldHalaka = \App\Models\Halaka::findOrFail($request->halaka_id);
            $newTeacher = \App\Models\User::findOrFail($request->new_teacher_id);

            if ($newTeacher->privilege < 2) {
                return response()->json(['message' => 'المستخدم المحدد ليس أستاذاً'], 400);
            }

            $oldTeacherId = $oldHalaka->teacher_id;
            
            // جلب الـ id والـ user_id معاً لتحسين الأداء
            $studentsInHalaka = \App\Models\student::where('halaka_id', $oldHalaka->id)->get(['id', 'user_id']);
            $studentIds = $studentsInHalaka->pluck('id')->toArray();
            $userIds = $studentsInHalaka->pluck('user_id')->toArray();

            // معالجة جدول العلاقة لجميع الطلاب
            if (! empty($studentIds)) {
                // 1. وضع تاريخ الانتهاء مع الأستاذ القديم
                \Illuminate\Support\Facades\DB::table('student_teacher')
                    ->whereIn('student_id', $studentIds)
                    ->where('teacher_id', $oldTeacherId)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);

                // 2. تسجيل تاريخ البدء مع الأستاذ الجديد 
                $newRecords = array_map(function ($id) use ($newTeacher) {
                    return [
                        'teacher_id' => $newTeacher->id,
                        'student_id' => $id,
                        'started_at' => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $studentIds);

                \Illuminate\Support\Facades\DB::table('student_teacher')->insert($newRecords);
                
                // التعديل الأهم: تحديث جدول التقارير لجميع الطلاب دفعة واحدة
                if (!empty($userIds)) {
                    \App\Models\report::whereIn('user_id', $userIds)->update([
                        'teacher_id' => $newTeacher->id,
                    ]);
                }
            }

            // فحص ما إذا كان الأستاذ الجديد يملك حلقة مسبقاً
            $existingHalakaForNewTeacher = \App\Models\Halaka::where('teacher_id', $newTeacher->id)->first();

            if ($existingHalakaForNewTeacher && $existingHalakaForNewTeacher->id != $oldHalaka->id) {
                // سيناريو الدمج
                \App\Models\student::where('halaka_id', $oldHalaka->id)
                    ->update([
                        'teacher_id' => $newTeacher->id,
                        'halaka_id' => $existingHalakaForNewTeacher->id,
                    ]);

                $existingHalakaForNewTeacher->students_count += $oldHalaka->students_count;
                $existingHalakaForNewTeacher->save();

                $oldHalaka->delete();

                return response()->json([
                    'message' => 'تم دمج الحلقة مع حلقة الأستاذ الحالية بنجاح',
                    'halaka' => $existingHalakaForNewTeacher,
                ], 200);

            } else {
                // سيناريو النقل العادي
                $oldHalaka->teacher_id = $newTeacher->id;
                $oldHalaka->save();

                \App\Models\student::where('halaka_id', $oldHalaka->id)
                    ->update(['teacher_id' => $newTeacher->id]);

                return response()->json([
                    'message' => 'تم تغيير أستاذ الحلقة بنجاح',
                    'halaka' => $oldHalaka,
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function delete_halaka(Request $request, $id)
    {
        try {
            $authUser = auth('api')->user();
            if (! $authUser) {
                return response()->json(['message' => 'غير مصرح'], 401);
            }
            $deleted = app(\App\Services\UserService::class)->deleteHalaka($id, $authUser);

            return response()->json([
                'message' => 'تم حذف الحلقة وإلغاء تعيين الطلاب بنجاح',
                'deleted' => $deleted,
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;

            return response()->json([
                'error' => $e->getMessage(),
            ], $code);
        }
    }
}
