<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\User\RegisterStudentRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use App\Http\Requests\Api\User\UserLoginRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller implements HasMiddleware
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', except: ['login', 'registerStudent', 'getSuggestions']),
        ];
    }

    public function toggleTeacherPrivilege(Request $request, User $user)
    {
        try {
            $authUser = Auth::user();
            $updatedUser = $this->userService->toggleTeacherPrivilege($user->id, $authUser);

            return response()->json([
                'message' => 'تم تغيير صلاحية الأستاذ بنجاح.',
                'user' => $updatedUser,
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function getSuggestions()
    {
        try {
            $data = $this->userService->getSuggestions();

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not fetch suggestions from the server.'], 500);
        }
    }

    public function registerStudent(RegisterStudentRequest $request)
    {
        try {
            $photoFile = $request->hasFile('photo') ? $request->file('photo') : null;
            $data = $this->userService->registerStudent($request->validated(), $photoFile);

            $user = $data['user'];
            $token = Auth::login($user);

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'age' => $user->age, // يحمل تاريخ الميلاد كما اتفقنا
                'phone_number' => $user->phone_number,
                'job' => $user->job ? $user->job->name : null,
                'address' => $user->area ? $user->area->name : null,
                'family_status' => $user->family_status,
                'privilege' => $user->privilege,
                'teacher_id' => $data['teacher_id'],
                'teacher_name' => $data['teacher_name'],
                'created_at' => $user->created_at,
                'token' => (string) $token,
                'daora_id' => $request->daora_id,

                // ✅ إضافة الحقول الجديدة هنا
                'family_members_count' => $user->family_members_count,
                'notes' => $user->notes,
                'skills' => $user->skills,
                'certificates' => $user->certificates,
                'enrollment_date' => $user->enrollment_date,
            ], 201);

        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['message' => 'هذا الاسم مستخدم بالفعل، يرجى اختيار اسم آخر.'], 409);
            }

            return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.', 'error' => $e->getMessage()], 500);
        }
    }

    public function login(UserLoginRequest $request)
    {
        try {
            $credentials = $request->validated();
            $token = Auth::attempt($credentials);

            if (! $token) {
                return response()->json([
                    'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
                ], 401);
            }

            $authUser = Auth::user();
            $data = $this->userService->getPersonalData(null, $authUser);
            $user = $data['user'];

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'age' => $user->age,
                'phone_number' => $user->phone_number,
                'privilege' => $user->privilege,
                'photo' => $user->photo,
                'photo_hash' => $user->photo_hash,
                'teacher_id' => $data['teacher_id'],
                'teache_name' => $data['teache_name'],
                'token' => (string) $token,
                'daora_id' => $user->daora_id,
                'job' => $user->job ? $user->job->name : null,
                'address' => $user->area ? $user->area->name : null,
                'family_status' => $user->family_status,

                // ✅ إضافة الحقول الجديدة هنا لتصل لتطبيق Flutter عند تسجيل الدخول
                'family_members_count' => $user->family_members_count,
                'notes' => $user->notes,
                'skills' => $user->skills,
                'certificates' => $user->certificates,
                'enrollment_date' => $user->enrollment_date,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ غير متوقع في الخادم.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function get_personal_data(Request $request)
    {
        try {
            $authUser = Auth::user();
            $data = $this->userService->getPersonalData($request->id, $authUser);
            $user = $data['user'];
            $student = $data['student'] ?? null;

            $response = [
                'id' => $user->id,
                'name' => $user->name,
                'age' => $user->age,
                'birth_date' => $user->birth_date,
                'phone_number' => $user->phone_number,
                'privilege' => $user->privilege,
                'photo' => $user->photo,
                'photo_hash' => $user->photo_hash,
                'teacher_id' => $data['teacher_id'],
                'teache_name' => $data['teache_name'],
                'daora_id' => $user->daora_id,
                'job' => $user->job ? $user->job->name : null,
                'address' => $user->area ? $user->area->name : null,
                'family_status' => $user->family_status,
                'family_members_count' => $user->family_members_count,
                'notes' => $user->notes,
                'skills' => $user->skills,
                'certificates' => $user->certificates,
                'enrollment_date' => $user->enrollment_date,
                'ended_quraan' => $user->ended_quraan_in_aukaf ?? [],
            ];

            // إضافة بيانات الطالب الخاصة
            if ($student) {
                $response['current_grade'] = $student->current_grade;
                $response['school_name'] = $student->school_name;
                $response['father_phone'] = $student->father_phone;
                $response['mother_phone'] = $student->mother_phone;
                $response['landline_phone'] = $student->landline_phone;
                $response['other_phone'] = $student->other_phone;
                $response['preferred_contact'] = $student->preferred_contact;
                $response['previous_mosques'] = $student->previous_mosques;
            }

            return response()->json($response, 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 404) {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم أثناء جلب البيانات.'], 500);
        }
    }

    public function update_password(Request $request)
    {
        try {
            $rules = [
                'old_password' => 'required',
                'password' => 'required',
            ];
            $validated = Validator::make($request->all(), $rules);

            if ($validated->fails()) {
                return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة', 'errors' => $validated->errors()], 403);
            }

            $authUser = Auth::user();
            $update = $this->userService->updatePassword($request->old_password, $request->password, $authUser);

            $credentials = ['name' => $authUser->name, 'password' => $request->password];
            $token = Auth::attempt($credentials);

            return response()->json([
                'token' => $token,
                'update' => $update,
            ], 200);

        } catch (\Exception $e) {
            if ($e->getCode() == 401) {
                return response()->json(['message' => $e->getMessage()], 401);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update_details(Request $request)
    {
        $validatedData = $request->validate([
            'phone_number' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'job' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'family_status' => 'nullable|string|max:255',
            'family_members_count' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'skills' => 'nullable|string|max:1000',
            'certificates' => 'nullable|string|max:1000',
            // ✅ إضافة تاريخ الانتساب ليمر بسلام
            'enrollment_date' => 'nullable|date',
        ]);

        try {
            $authUser = Auth::user();
            $this->userService->updateDetails($validatedData, $authUser);

            return response()->json(['message' => 'تم تحديث البيانات بنجاح'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ غير متوقع: '.$e->getMessage()], 500);
        }
    }

    public function update_photo(Request $request)
    {
        try {
            $rules = [
                'photo' => 'required|file|image|mimes:jpg,jpeg,png,webp,heic|max:10240',
            ];
            $validated = Validator::make($request->all(), $rules);

            if ($validated->fails()) {
                return response()->json(['status' => 'error', 'messages' => 'يوجد مشكلة ببالبيانات المدخلة', 'errors' => $validated->errors()], 403);
            }

            $authUser = Auth::user();
            $result = $this->userService->updatePhoto($request->file('photo'), $authUser);

            return response()->json([
                'photo_hash' => $result['photo_hash'],
                'photo' => $result['photo'],
                'updated' => $result['updated'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function update_student_photo(Request $request, $id)
    {
        try {
            $rules = [
                'photo' => 'required|file|image|mimes:jpg,jpeg,png,webp,heic|max:10240',
            ];
            $validated = Validator::make($request->all(), $rules);

            if ($validated->fails()) {
                return response()->json(['status' => 'error', 'messages' => 'يوجد مشكلة بالبيانات المدخلة', 'errors' => $validated->errors()], 403);
            }

            $authUser = Auth::user();
            $result = $this->userService->updateStudentPhoto($request->file('photo'), $id, $authUser);

            return response()->json([
                'photo_hash' => $result['photo_hash'],
                'photo' => $result['photo'],
                'updated' => $result['updated'],
            ]);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 404 || $code == 403) {
                return response()->json(['message' => $e->getMessage()], $code);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function add_admin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:users,name',
            'password' => 'required|string',
            'privilege' => 'required|integer|in:2,3',
            'daora_id' => 'nullable|exists:daoras,id',
            'job' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'family_status' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors(), 'errors' => $validator->errors()], 422);
        }

        try {
            $authUser = Auth::user();
            $this->userService->addAdmin($request->all(), $authUser);

            return response()->json(['message' => 'تمت إضافة الأستاذ بنجاح'], 201);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 403) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.', 'error' => $e->getMessage()], 500);
        }
    }

    public function delete_user(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $authUser = Auth::user();
            $userDeleted = $this->userService->deleteUser($request->user_id, $authUser);

            return response()->json([
                'message' => 'تم حذف المستخدم وكل بياناته بنجاح',
                'user_deleted' => $userDeleted,
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 403) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return response()->json([
                'error' => $e->getMessage(),
                'path' => $e->getTrace(), // strictly matching previous responses
            ], 500);
        }
    }

    public function show_all_teachers(Request $request)
    {
        try {
            $teachers = $this->userService->showAllTeachers($request->daora_id);

            return response()->json($teachers);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 400) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function take_student(Request $request)
    {
        try {
            $authUser = Auth::user();
            $update = $this->userService->takeStudent($request->user_id, $authUser);

            return response()->json(['update' => $update]);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 400) {
                return response()->json([$e->getMessage()]); // matching strictly older behavior which didn't throw proper json keys
            }

            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function leave_student(Request $request)
    {
        try {
            $authUser = Auth::user();
            $update = $this->userService->leaveStudent($request->user_id, $authUser);

            return response()->json(['update' => $update]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function add_wanting_students(Request $request)
    {
        try {
            $authUser = Auth::user();
            $data = $request->json()->all();
            $this->userService->addWantingStudents($data, $authUser);

            \App\Models\TeacherDailyTask::markTaskCompleted($authUser->id, 'attendance_taken');

            if ($authUser->privilege == 3 && ($data['glob'] ?? false)) {
                return response()->json(['message' => 'تم تسجيل الحضور والغياب (عام) بنجاح']);
            }

            return response()->json(['message' => 'تم تسجيل الحضور والغياب (للحلقة) بنجاح']);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 403) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
            if ($code == 400) {
                return response()->json(['error' => $e->getMessage()], 400);
            }

            return response()->json(['error' => 'حدث خطأ غير متوقع: '.$e->getMessage()], 500);
        }
    }
    public function add_absent_teachers(Request $request)
    {
        try {
            $authUser = Auth::user();
            if ($authUser->privilege < 3) {
                return response()->json(['error' => 'لا تملك صلاحية لتسجيل غياب الأساتذة'], 403);
            }

            $data = $request->json()->all();
            $absentTeacherIds = $data['teachers'] ?? [];

            if (! is_array($absentTeacherIds)) {
                return response()->json(['error' => 'البيانات المرسلة غير صحيحة، قائمة الأساتذة مطلوبة'], 400);
            }

            foreach ($absentTeacherIds as $teacherId) {
                \Illuminate\Support\Facades\DB::table('absence_records')->updateOrInsert(
                    [
                        'user_id' => $teacherId,
                        'absence_date' => now()->toDateString(),
                    ],
                    [
                        'teacher_id' => $authUser->id,
                        'reason' => 'غياب (مسجل بواسطة المدير)',
                        'updated_at' => now(),
                    ]
                );
            }

            return response()->json(['message' => 'تم تسجيل غياب الأساتذة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ غير متوقع: '.$e->getMessage()], 500);
        }
    }

    public function save_absence_reason(Request $request)
    {
        try {
            $authUser = Auth::user();
            if ($authUser->privilege <= 1) {
                return response()->json(['error' => 'لا تملك صلاحية'], 403);
            }
            $data = $request->json()->all();
            $studentUserId = $data['user_id'] ?? null;
            $reason = $data['reason'] ?? null;

            if (!$studentUserId) {
                return response()->json(['error' => 'معرف الطالب مطلوب'], 400);
            }

            \Illuminate\Support\Facades\DB::table('absence_records')->updateOrInsert(
                [
                    'user_id' => $studentUserId,
                    'absence_date' => now()->toDateString(),
                ],
                [
                    'reason' => $reason,
                    'teacher_id' => $authUser->id,
                    'updated_at' => now(),
                ]
            );

            return response()->json(['success' => true, 'message' => 'تم حفظ السبب بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    public function show_notifications()
    {
        try {
            $authUser = Auth::user();
            $data = $this->userService->showNotifications($authUser);

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['th' => $e->getMessage()]);
        }
    }

    public function read_notifications()
    {
        try {
            $authUser = Auth::user();
            $make_readed = $this->userService->readNotifications($authUser);

            return response()->json(['make_readed' => $make_readed]);
        } catch (\Exception $e) {
            return response()->json(['th' => $e->getMessage()]);
        }
    }

    public function get_score(Request $request)
    {
        try {
            $authUser = Auth::user();
            $student = $this->userService->getScore($request->id, $authUser);

            return response()->json($student);
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 400) {
                return response()->json([$e->getMessage()]);
            }

            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function get_user_by_id(Request $request)
    {
        try {
            $user = $this->userService->getUserById($request->user_id);

            return response()->json($user);
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 400 || $code == 404) {
                return response()->json($e->getMessage());
            }

            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function show_users_without_teacher(Request $request)
    {
        try {
            $students = $this->userService->showUsersWithoutTeacher($request->daora_id);

            return response()->json($students);
        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 401) {
                return response()->json(['message' => $e->getMessage()], 401);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateUser(UpdateUserRequest $request, $id)
    {
        try {
            $data = $this->userService->updateUser($request->validated(), $id);

            return response()->json([
                'status' => 'success',
                'messages' => 'تم تحديث البيانات بنجاح ✅',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;
            if ($code == 404) {
                return response()->json([
                    'status' => 'error',
                    'messages' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStudentTeachersHistory($studentUserId)
    {
        try {
            $authUser = Auth::user();
            $history = $this->userService->getStudentTeachersHistory($studentUserId, $authUser);

            return response()->json([
                'success' => true,
                'data' => $history,
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode() > 0 ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    public function update_user_behavior(Request $request, $user_id)
    {
        try {
            $authUser = Auth::user();

            // تحقق من أن الأستاذ أو المشرف هو من يقوم بالتقييم
            if ($authUser->privilege < 2) {
                return response()->json(['success' => false, 'message' => 'لا تملك صلاحية تقييم الطلاب'], 403);
            }

            $validatedData = $request->validate([
                'traits' => 'nullable|string',
                'habits' => 'nullable|string',
                'discipline' => 'nullable|string',
                'morals' => 'nullable|string',
                'skills' => 'nullable|string',
                'positive_actions' => 'nullable|string',
                'negative_actions' => 'nullable|string',
            ]);

            $this->userService->updateUserBehavior($user_id, $authUser->id, $validatedData);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ تقييم السلوك بنجاح',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ السلوك',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function get_user_behavior($user_id)
    {
        try {
            $behavior = $this->userService->getUserBehavior($user_id);

            return response()->json([
                'success' => true,
                'data' => $behavior, // سيرجع null إذا لم يكن للطالب تقييم سابق
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
