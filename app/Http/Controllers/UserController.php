<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Http\Requests\Api\User\RegisterStudentRequest;
use App\Http\Requests\Api\User\UserLoginRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

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
                'age' => $user->age,
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
            ], 201);

        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['message' => 'هذا الاسم مستخدم بالفعل، يرجى اختيار اسم آخر.'], 409);
            }
            return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.', 'error' => $e->getMessage()], 500); 
            // the error property is left intentionally for debugging as previously present in fallback logic
        }
    }

    public function login(UserLoginRequest $request)
    {
        try {
            $credentials = $request->validated();
            $token = Auth::attempt($credentials);

            if (!$token) {
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
                'photo_hash' => $user->photo_hash,
                'teacher_id' => $data['teacher_id'],
                'teache_name' => $data['teache_name'], 
                'token' => (string) $token,
                'daora_id' => $user->daora_id,
                'job' => $user->job ? $user->job->name : null,
                'address' => $user->area ? $user->area->name : null,
                'family_status' => $user->family_status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ غير متوقع في الخادم.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(), // kept exactly as their original try/catch returned 
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

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'age' => $user->age,
                'phone_number' => $user->phone_number,
                'privilege' => $user->privilege,
                'photo_hash' => $user->photo_hash,
                'teacher_id' => $data['teacher_id'],
                'teache_name' => $data['teache_name'], 
                'daora_id' => $user->daora_id,
                'job' => $user->job ? $user->job->name : null,
                'address' => $user->area ? $user->area->name : null,
                'family_status' => $user->family_status,
            ], 200);

        } catch (\Exception $e) {
             $code = $e->getCode() > 0 ? $e->getCode() : 500;
             if ($code == 404) return response()->json(['message' => $e->getMessage()], 404);
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
            'phone_number' => 'required|string|max:255',
            'age' => 'required|integer|min:1',
            'job' => 'required|string|min:1|max:255',
            'address' => 'required|string|min:1|max:255',
            'family_status' => 'nullable|string|max:255',
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
                'photo' => 'required',
                'photo_hash' => 'required',
            ];
            $validated = Validator::make($request->all(), $rules);

            if ($validated->fails()) {
                return response()->json(['status' => 'error', 'messages' => 'يوجد مشكلة ببالبيانات المدخلة', 'errors' => $validated->errors()], 403);
            }

            $authUser = Auth::user();
            $updated = $this->userService->updatePhoto($request->file('photo'), $request->photo_hash, $authUser);

            return response()->json([
                'photo_hash' => $request->photo_hash,
                'updated' => $updated,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ]);
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
}
