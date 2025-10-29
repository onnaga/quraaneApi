<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\point;
use App\Models\student;
use App\Models\Daora;
use App\Models\Job;
use App\Models\User;
use App\Notifications\taken_by_teacher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'registerStudent','getSuggestions']]);
    }

    /**
     * ✅ دالة مساعدة للتعامل مع العمل والمنطقة
     */
    private function getOrCreateJobAndArea(string $jobName, string $areaName): array
    {
        // التعامل مع العمل
        // firstOrCreate: ابحث عن العمل، إذا لم تجده، قم بإنشائه
        $job = Job::firstOrCreate(['name' => $jobName]);
        $job->increment('user_count'); // زيادة العداد

        // التعامل مع المنطقة
        $area = Area::firstOrCreate(['name' => $areaName]);
        $area->increment('user_count'); // زيادة العداد

        return [
            'job_id' => $job->id,
            'area_id' => $area->id,
        ];
    }




     public function toggleTeacherPrivilege(Request $request, User $user)
    {
        // 1. الحصول على المستخدم الذي أرسل الطلب (المشرف)
        $requestingUser = Auth::user();

        // 2. التحقق من أن المستخدم الذي يرسل الطلب هو مشرف (privilege = 3)
        if ($requestingUser->privilege != 3) {
            return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية.'], 403); // Forbidden
        }

        // 3. التحقق من أن صلاحية المستخدم المستهدف هي إما 2 أو 5
        if (!in_array($user->privilege, [2, 3])) {
            return response()->json(['message' => 'لا يمكن تغيير صلاحية هذا المستخدم.'], 400); // Bad Request
        }
        
        // 4. ✅ --- تطبيق القاعدة الخاصة: إذا كان كلاهما مشرفين (privilege = 3) ---
        // هذه القاعدة تنطبق إذا كان المستخدم المستهدف مشرفاً أيضاً
        if ($requestingUser->privilege == 3 && $user->privilege == 3) {
            // يجب أن يكون ID المشرف الذي يقوم بالتغيير أصغر من ID المشرف المستهدف
            if ($requestingUser->id >= $user->id) {
                return response()->json(['message' => 'لا يمكنك تعديل صلاحيات مشرف تم إضافته قبلك و بنفس صلاحياتك.'], 403);
            }
        }

        // 5. تحديد الصلاحية الجديدة

        $newPrivilege = ($user->privilege == 2) ? 3 : 2;
        
        $user->privilege = $newPrivilege;
        $user->save();

        // 6. إرجاع استجابة ناجحة مع بيانات المستخدم المحدثة
        return response()->json([
            'message' => 'تم تغيير صلاحية الأستاذ بنجاح.',
            'user' => $user // إرجاع بيانات المستخدم المحدثة مفيد للـ Frontend
        ], 200);
    }


        public function getSuggestions()
    {
        try {
            // pluck لجلب عمود واحد فقط من الجدول بكفاءة عالية
            $jobs = Job::pluck('name');
            $areas = Area::pluck('name');

            return response()->json([
                'jobs' => $jobs,
                'areas' => $areas
            ], 200);
        } catch (\Throwable $th) {
            // في حالة حدوث خطأ، أرجع رسالة خطأ واضحة
            return response()->json(['error' => 'Could not fetch suggestions from the server.'], 500);
        }
    }
public function registerStudent(Request $request)
{
    try {
        $rules = [
            'name'                  => 'required|string|max:255|unique:users,name',
            'password'              => 'required|string',
            'phone_number'          => 'required|string|max:20',
            'age'                   => 'required|integer',
            'job'                   => 'required|string|max:255',
            'address'               => 'required|string|max:255', // 'address' هو اسم المنطقة
            'family_status'         => 'nullable|string|max:255',
            'ended_quraan_in_aukaf' => 'required',
            'photo'                 => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'daora_id'              => 'required|exists:daoras,id'
        ];

        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'البيانات المدخلة غير صحيحة , من الممكن أن يكون الاسم مكررا حاول كتابة الاسم الثلاثي ',
                'errors'  => $validated->errors()
            ], 422);
        }

        $photoName = null;
        $photoHash = null;

        if ($request->hasFile('photo')) {
            $image     = $request->file('photo');
            $photoName = uniqid('user_') . '.' . $image->getClientOriginalExtension();
            $manager = new ImageManager(new Driver());
            $img     = $manager->read($image);
            $img->scaleDown(600);
            $savePath = storage_path('app/public/uploadsUser');
            if (!file_exists($savePath)) {
                mkdir($savePath, 0777, true);
            }
            $img->save($savePath . '/' . $photoName);
            $photoHash = hash_file('sha256', $savePath . '/' . $photoName);
        }
        $ids = $this->getOrCreateJobAndArea($request->job, $request->address);

        $user = User::create([
            'name'          => $request->name,
            'phone_number'  => $request->phone_number,
            'password'      => Hash::make($request->password),
            'age'           => $request->age,
            'family_status' => $request->family_status,
            'privilege'     => 1,
            'photo'         => $photoName,
            'photo_hash'    => $photoHash,
            'daora_id'      => $request->daora_id,
            'job_id'        => $ids['job_id'],
            'area_id'       => $ids['area_id'],
        ]);

        // ✅ تحميل العلاقات لجلب الأسماء
        $user->load('job', 'area');

        $token = Auth::login($user);

        $student = Student::create([
            'user_id'               => $user->id,
            'ended_quraan_in_aukaf' => $request->ended_quraan_in_aukaf,
            'missing_days'          => 0
        ]);
        $teacher_id = $student->teacher_id;
        $teacher_name = $teacher_id ? User::find($teacher_id)->name : null;

        $daora = Daora::find($request->daora_id);
        if ($daora) {
            $daora->increment('number_of_students');
        }

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'age'          => $user->age,
            'phone_number' => $user->phone_number,
            // ✅ --- تعديل طريقة إرجاع البيانات ---
            'job'          => $user->job ? $user->job->name : null, // إرجاع اسم العمل
            'address'      => $user->area ? $user->area->name : null, // إرجاع اسم المنطقة
            'family_status' => $user->family_status, // إرجاع الحالة العائلية
            // --- نهاية التعديل ---
            'privilege'    => $user->privilege,
            'teacher_id'   => $teacher_id,
            'teacher_name' => $teacher_name, // ملاحظة: اسم المتغير هنا teacher_name وليس teache_name
            'created_at'   => $user->created_at,
            'token'        => (string)$token,
            'daora_id'     => $request->daora_id
        ], 201);
    } catch (\Throwable $th) {
        if ($th->getCode() == 23000) {
            return response()->json(['message' => 'هذا الاسم مستخدم بالفعل، يرجى اختيار اسم آخر.'], 409);
        }
        // لعرض الخطأ الفعلي أثناء التطوير
        // return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.', 'error' => $th->getMessage()], 500);
        return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.'], 500);
    }
}

public function login(Request $request)
{
    try {
        $rules = [
            'name'     => 'required|string',
            'password' => 'required|string'
        ];

        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'البيانات المدخلة غير صالحة.',
                'errors'  => $validated->errors()
            ], 422);
        }

        $credentials = $request->only('name', 'password');
        $token = Auth::attempt($credentials);

        if (!$token) {
            return response()->json([
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
            ], 401);
        }

        $id = Auth::user()->id;
        $user =User::where('id', $id)->first();
        // ✅ تحميل العلاقات لجلب الأسماء (Eager Loading)
        $user->load('job', 'area');

        $student = student::where('user_id', $user->id)->first();
        $teacher_id = $student ? $student->teacher_id : null;
        $teacher_name = $teacher_id ? User::find($teacher_id)->name : null;

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'age'          => $user->age,
            'phone_number' => $user->phone_number,
            'privilege'    => $user->privilege,
            'photo_hash'   => $user->photo_hash,
            'teacher_id'   => $teacher_id,
            'teache_name'  => $teacher_name, // انتبه للاسم هنا
            'token'        => (string)$token,
            'daora_id'     => $user->daora_id,
            // ✅ --- إضافة البيانات الجديدة هنا ---
            'job'          => $user->job ? $user->job->name : null,
            'address'      => $user->area ? $user->area->name : null,
            'family_status' => $user->family_status,
            // --- نهاية الإضافة ---
        ], 200);
    } catch (\Throwable $th) {
        return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.'], 500);
    }
}

public function get_personal_data(Request $request)
{
    try {
        $userToFetch = null;

        if ($request->has('id')) {
            $userToFetch = User::find($request->id);
            if (!$userToFetch) {
                return response()->json(['message' => 'المستخدم المطلوب غير موجود.'], 404);
            }
        } else {
            $id = Auth::user()->id;
            $userToFetch = User::find($id);
        }

        // ✅ تحميل العلاقات لجلب الأسماء (Eager Loading)
        $userToFetch->load('job', 'area');

        $teacher_id = null;
        $teacher_name = null;

        if ($userToFetch->privilege == 1) { // إذا كان المستخدم طالباً
            $student = student::where('user_id', $userToFetch->id)->first();
            if ($student) {
                $teacher_id = $student->teacher_id;
                if ($teacher_id) {
                    $teacher = User::find($teacher_id);
                    $teacher_name = $teacher ? $teacher->name : null;
                }
            }
        }

        return response()->json([
            'id'           => $userToFetch->id,
            'name'         => $userToFetch->name,
            'age'          => $userToFetch->age,
            'phone_number' => $userToFetch->phone_number,
            'privilege'    => $userToFetch->privilege,
            'photo_hash'   => $userToFetch->photo_hash,
            'teacher_id'   => $teacher_id,
            'teache_name'  => $teacher_name, // انتبه للاسم هنا
            'daora_id'     => $userToFetch->daora_id,
            // ✅ --- إضافة البيانات الجديدة هنا ---
            'job'          => $userToFetch->job ? $userToFetch->job->name : null,
            'address'      => $userToFetch->area ? $userToFetch->area->name : null,
            'family_status' => $userToFetch->family_status,
            // --- نهاية الإضافة ---
        ], 200);
    } catch (\Throwable $th) {
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
            //Create a validator, unlike $this->validate(), this does not automatically redirect on failure, leaving the final control to you :)
            $validated = Validator::make($request->all(), $rules);

            //Check if the validation failed, return your custom formatted code here.
            if ($validated->fails()) {
                return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة', 'errors' => $validated->errors()], 403);
            }
            $user = Auth::user();
            $credentials = ['name' => $user->name, 'password' => $request->old_password];
            //there we check if the password is right , if not the token woyldnt generated
            $token = Auth::attempt($credentials);
            if ($token) {
                $credentials = ['name' => $user->name, 'password' => $request->password];
                $update = User::where('id', $user->id)->update([
                    "password" => Hash::make($request->password)
                ]);
                $token = Auth::attempt($credentials);

                return response()->json([
                    'token' => $token,
                    'update' => $update
                ], 200);
            } else {
                return response()->json([
                    'message' => 'كلمة السر السابقة خاطئة',
                ], 401);
            }
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
public function update_details(Request $request)
    {
        // 2. التحقق من صحة المدخلات أولاً
        $validatedData = $request->validate([
            'phone_number' => 'required|string|max:255',
            'age'          => 'required|integer|min:1',
            'job'          => 'required|string|min:1|max:255',
            'address'      => 'required|string|min:1|max:255',
            'family_status'=> 'nullable|string|max:255',
        ]);

        try {
            // 3. بدء معاملة (Transaction) لضمان سلامة البيانات
            $result = DB::transaction(function () use ($validatedData) {
                $user = Auth::user();

                // 4. حفظ IDs القديمة قبل أي تغيير
                $oldJobId = $user->job_id;
                $oldAreaId = $user->area_id;

                // 5. جلب أو إنشاء العمل والمنطقة الجديدة (مع تطبيع النص لتجنب التكرار)
                $newJob = Job::firstOrCreate(['name' => strtolower($validatedData['job'])]);
                $newArea = Area::firstOrCreate(['name' => strtolower($validatedData['address'])]);

                // 6. تحديث بيانات المستخدم بالـ IDs الجديدة
                $user->update([
                    'phone_number'  => $validatedData['phone_number'],
                    'age'           => $validatedData['age'],
                    'family_status' => $validatedData['family_status'],
                    'job_id'        => $newJob->id,
                    'area_id'       => $newArea->id,
                ]);

                // 7. تحديث العدّادات فقط إذا تغير الـ ID
                if ($newJob->id !== $oldJobId) {
                    $newJob->increment('user_count');
                    // ابحث عن العمل القديم وقم بإنقاص عداده
                    if ($oldJobId && $oldJob = Job::find($oldJobId)) {
                        $oldJob->decrement('user_count');
                    }
                }

                if ($newArea->id !== $oldAreaId) {
                    $newArea->increment('user_count');
                    // ابحث عن المنطقة القديمة وقم بإنقاص عدادها
                    if ($oldAreaId && $oldArea = Area::find($oldAreaId)) {
                        $oldArea->decrement('user_count');
                    }
                }
                
                return true; // إشارة إلى نجاح المعاملة
            });
            
            return response()->json(['message' => 'تم تحديث البيانات بنجاح'], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $th) {
            // في حال فشلت المعاملة، سيتم إرجاع خطأ
            return response()->json(['error' => 'حدث خطأ غير متوقع: ' . $th->getMessage()], 500);
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

            $image = $request->file('photo');
            $photo_hash = $request->photo_hash;
            $new_photo = rand(0, 9999999) . '.' . $image->getClientOriginalExtension();
            $old_Photo = Auth::user()->photo;
            $save_path = storage_path('\uploadsUser');



            $manager = new ImageManager(new Driver);

            $image = $manager->read($request->file('photo'));
            $image->save($save_path . '\\' . $new_photo);
            $delete_the_photo_from_storage = File::delete($save_path . '\\' . $old_Photo);
            $updated = User::where('id', Auth::user()->id)->update([
                'photo' => $new_photo,
                'photo_hash' => $photo_hash
            ]);
            return response()->json([
                'photo_hash' => $photo_hash,
                'updated' => $updated
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ]);
        }
    }
public function add_admin(Request $request)
{
    // 1. التحقق من صلاحيات المستخدم أولاً
    if (Auth::user()->privilege != 3) {
        return response()->json(['message' => 'غير مصرح لك بإضافة أساتذة'], 403);
    }

    // 2. التحقق من صحة المدخلات
    $validator = Validator::make($request->all(), [
        'name'          => 'required|string|max:255|unique:users,name',
        'password'      => 'required|string',
        'privilege'     => 'required|integer|in:2,3', // التأكد أن الصلاحية هي 2 أو 3
        'daora_id'      => 'nullable|exists:daoras,id',
        'job'           => 'required|string|max:255',
        'address'       => 'required|string|max:255',
        'family_status' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => $validator->errors(), 'errors' => $validator->errors()], 422);
    }

    try {
        // 3. بدء معاملة (Transaction) لضمان تنفيذ كل العمليات معاً أو إلغائها معاً
        DB::transaction(function () use ($request) {
            
            // 4. جلب أو إنشاء العمل والمنطقة الجديدة (مع تطبيع النص لتجنب التكرار)
            // strtolower لتحويل النص إلى أحرف صغيرة، و trim لإزالة المسافات الزائدة
            $newJob = Job::firstOrCreate(['name' => strtolower(trim($request->job))]);
            $newArea = Area::firstOrCreate(['name' => strtolower(trim($request->address))]);
            
            // 5. زيادة عدّاد المستخدمين للعمل والمنطقة
            $newJob->increment('user_count');
            $newArea->increment('user_count');

            // 6. إنشاء المستخدم الجديد في قاعدة البيانات
            User::create([
                'name'          => $request->name,
                'password'      => Hash::make($request->password),
                'privilege'     => $request->privilege,
                'daora_id'      => $request->daora_id,
                'family_status' => $request->family_status,
                'job_id'        => $newJob->id, // استخدام الـ ID
                'area_id'       => $newArea->id, // استخدام الـ ID
            ]);
        });

        // 7. إرجاع رسالة نجاح
        return response()->json(['message' => 'تمت إضافة الأستاذ بنجاح'], 201);

    } catch (\Throwable $th) {
        // 8. التعامل مع الأخطاء غير المتوقعة (مثل خطأ في قاعدة البيانات)
        // سيتم إلغاء كل العمليات داخل الـ transaction تلقائياً
        return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم.', 'error' => $th->getMessage()], 500);
    }
}

    public function delete_user(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user_id = $request->user_id;

        try {
            if (Auth::user()->privilege != 3) {
                return response()->json([
                    'message' => 'عذرا , انت لا تمتلك صلاحية الحذف',
                ], 403);
            }

            $user = User::findOrFail($user_id);

            // ✅ التحقق إذا كان المستخدم أستاذ مدير وهو مسؤول عن دورة
            if ($user->privilege == 3) {
                $isAdminOfDaora = DB::table('daoras')
                    ->where('admin_id', $user_id)
                    ->exists();

                if ($isAdminOfDaora) {
                    return response()->json([
                        'message' => 'لا يمكن حذف هذا المستخدم لأنه مسؤول عن دورة',
                    ], 403);
                }
            }

            // ✅ إذا كان أستاذ (privilege 2 أو 3) → لا نحذف بيانات الطلاب / الإنجازات / التقارير / النقاط / الاختبارات
            if (!in_array($user->privilege, [2, 3])) {
                // حفظ latest_id قبل حذف الطلاب
                $latestIds = DB::table('students')
                    ->where('user_id', $user_id)
                    ->pluck('latest_id')
                    ->filter()
                    ->toArray();

                // حذف الطلاب
                DB::table('students')->where('user_id', $user_id)->delete();

                // حذف آخر الإنجازات
                if (!empty($latestIds)) {
                    DB::table('latests')->whereIn('id', $latestIds)->delete();
                }

                // حذف التقارير
                DB::table('reports')->where('user_id', $user_id)->delete();

                // حذف النقاط
                DB::table('points')->where('user_id', $user_id)->delete();

                // حذف الاختبارات
                DB::table('user_tests')->where('user_id', $user_id)->delete();
            }

            // ✅ حذف الإشعارات (مشتركة للجميع)
            DB::table('notifications')
                ->where('notifiable_type', 'App\\Models\\User')
                ->where('notifiable_id', $user_id)
                ->delete();

            // ✅ حذف المستخدم نفسه
            $userDeleted = $user->delete();

            return response()->json([
                'message' => 'تم حذف المستخدم وكل بياناته بنجاح',
                'user_deleted' => $userDeleted,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
                'path' => $th->getTrace()
            ], 500);
        }
    }

public function show_all_teachers(Request $request)
{
    if (!$request->daora_id) {
        return response()->json([
            'message' => 'daora_id is required'
        ], 400); // 400 Bad Request is more appropriate here
    }

    // ✅ الخطوة 1: استخدام Eager Loading لجلب بيانات العمل والمنطقة المرتبطة
    $admins = User::with(['job', 'area']) 
        ->where('privilege', '>', 1)
        ->where('daora_id', $request->daora_id)
        ->get();
        

    // ✅ الخطوة 2 (اختياري لكن موصى به): تنسيق البيانات لتسهيل التعامل معها في فلاتر
    $formattedAdmins = $admins->map(function ($admin) {
        $adminData = $admin->toArray();
        // نضيف اسم العمل والمنطقة كمفاتيح جديدة
        $adminData['job_name'] = $admin->job ? $admin->job->name : null;
        $adminData['area_name'] = $admin->area ? $admin->area->name : null;
        
        // نزيل الكائنات المتداخلة غير الضرورية
        unset($adminData['job'], $adminData['area']);
        
        return $adminData;
    });

    return response()->json($formattedAdmins);
}

    public function take_student(Request $request)
    {

        try {
            $user = Auth::user();
            if ($user->privilege > 1) {
                if (!$request->user_id) {
                    return response()->json([
                        'user_id is required'
                    ]);
                }
                $student_id = student::where('user_id', $request->user_id)->first('id')->id;

                if (student::find($student_id)->teacher_id == null) {
                    $update = student::where('id', $student_id)->update([
                        'teacher_id' => $user->id
                    ]);
                    $the_user_to_note = User::where('id', '=', student::find($student_id)->user_id)->get();
                    $teacher = User::find($user->id);
                    Notification::send(
                        $the_user_to_note,
                        new taken_by_teacher($teacher)
                    );
                    return response()->json([
                        'update' => $update
                    ]);
                } else {
                    return response()->json([
                        'error' => 'يوجد استاذ لهذا الطالب بالفعل , يمكنك الطلب من الاستاذ أن يترك هذا الطالب ومن ثم تعيد المحاولة'
                    ]);
                }
            } else {
                return response()->json([
                    'error' => 'لا تملك صلاحية استلام طلاب '
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ]);
        }
    }

    public function leave_student(Request $request)
    {

        try {
            $user = Auth::user();
            if ($user->privilege > 1) {
                if (!$request->user_id) {
                    return response()->json([
                        'user_id is required'
                    ]);
                }
                $student_id = student::where('user_id', $request->user_id)->first('id')->id;
                $update = student::where('id', $student_id)->update([
                    'teacher_id' => null,
                ]);
                return response()->json([
                    'update' => $update
                ]);
            } else {
                return response()->json([
                    'error' => 'لا تملك صلاحية ترك مستخدمين'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ]);
        }
    }
public function add_wanting_students(Request $request)
{
    if (Auth::user()->privilege <= 1) {
        return response()->json(['message' => 'لا تملك صلاحية التفقد'], 403);
    }

    try {
        $teacherId = Auth::id();

        // --- التعديل هنا ---
        // اقرأ البيانات من جسم الطلب كـ JSON
        $data = $request->json()->all(); 
        
        // احصل على قائمة الطلاب والمتغير glob
        // مع وضع قيم افتراضية آمنة
        $absentStudentIds = $data['students'] ?? []; 
        $glob = $data['glob'] ?? false;
        // --- نهاية التعديل ---


        // التحقق من أن المدخلات هي مصفوفة
        if (!is_array($absentStudentIds)) {
            // يمكنك تحسين رسالة الخطأ
            return response()->json(['error' => 'البيانات المرسلة غير صحيحة، قائمة الطلاب مطلوبة'], 400);
        }

        // الآن المنطق الخاص بك سيعمل كما هو
        if (Auth::user()->privilege == 3 && $glob) {
            
            // الخطوة 1: تسجيل الطلاب الغائبين (الكل)
            student::whereIn('user_id', $absentStudentIds)
                ->update([
                    'missing_days' => DB::raw('missing_days + 1'),
                    'last_attendance_status' => 'absent'
                ]);

            // الخطوة 2: تسجيل الطلاب الحاضرين (الكل)
            student::whereNotIn('user_id', $absentStudentIds)
                ->update([
                    'last_attendance_status' => 'present'
                ]);

            return response()->json(['message' => 'تم تسجيل الحضور والغياب (عام) بنجاح']);

        } else {
            
            // الخطوة 1: تسجيل الطلاب الغائبين (للحلقة فقط)
            student::where('teacher_id', $teacherId)
                ->whereIn('user_id', $absentStudentIds)
                ->update([
                    'missing_days' => DB::raw('missing_days + 1'),
                    'last_attendance_status' => 'absent'
                ]);

            // الخطوة 2: تسجيل الطلاب الحاضرين (للحلقة فقط)
            student::where('teacher_id', $teacherId)
                ->whereNotIn('user_id', $absentStudentIds)
                ->update([
                    'last_attendance_status' => 'present'
                ]);

            return response()->json(['message' => 'تم تسجيل الحضور والغياب (للحلقة) بنجاح']);
        }
    } catch (\Throwable $th) {
        return response()->json(['error' => 'حدث خطأ غير متوقع: ' . $th->getMessage()], 500);
    }
}
    public function show_notifications()
    {

        try {
            $User_id = Auth::user()->id;
            $unreaded_notes = DB::table('notifications')->where([['notifiable_id', '=', $User_id], ['read_at', '=', null]])->get("data");
            $readed_notes = DB::table('notifications')->where([['notifiable_id', '=', $User_id], ['read_at', '!=', null]])->get("data");
            $decoded_unreaded_notes = [];
            $decoded_readed_notes = [];
            foreach ($unreaded_notes as $unreaded_note) {
                array_push($decoded_unreaded_notes, json_decode($unreaded_note->data));
            }
            foreach ($readed_notes as $readed_note) {
                array_push($decoded_readed_notes, json_decode($readed_note->data));
            }



            return response()->json(['unreaded_notes' => $decoded_unreaded_notes, 'readed_notes' => $decoded_readed_notes]);
        } catch (\Throwable $th) {
            return response()->json(['th' => $th->getMessage()]);
        }
    }


    public function read_notifications()
    {

        try {
            $User_id = Auth::user()->id;
            $make_readed = DB::table('notifications')->where([['notifiable_id', '=', $User_id], ['read_at', '=', null]])->update([
                'read_at' => now()
            ]);
            return response()->json(['make_readed' => $make_readed]);
        } catch (\Throwable $th) {
            return response()->json(['th' => $th->getMessage()]);
        }
    }




    public function get_score(Request $request)
    {
        try {
            if (Auth::user()->privilege > 1) {
                if (!$request->id) {
                    return response()->json(["the id is required"]);
                }


                $student = student::where('user_id', $request->id)->first(['point_id', 'ended_quraan_in_aukaf', 'missing_days']);

                $student->points = point::find($student->point_id);
            } else {

                $student = student::where('user_id', Auth::user()->id)->first(['point_id', 'ended_quraan_in_aukaf', 'missing_days']);

                $student->points = point::find($student->point_id);
            }

            return response()->json($student);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ]);
        }
    }



    public function get_user_by_id(Request $request)
    {
        if (!$request->user_id)
            return response()->json('user id is required');
        try {

            if (User::find($request->user_id) == null)
                return response()->json("هذا المستحدم غير موجود");
           $user =  DB::table('users')
            ->leftJoin('jobs', 'users.job_id', '=', 'jobs.id')
            ->select(
                'users.id',
                'users.name',
                'users.phone_number',
                'users.age',
                'users.family_status', // ❗️ جلب الحالة العائلية
                'jobs.name as job_name'   // ❗️ جلب اسم العمل وتسميته job_name
                // يمكنك إضافة أي حقول أخرى تحتاجها هنا
            )
            ->where('users.id',$request->user_id)
            ->first(); // استخدم first() لأننا نتوقع مستخدمًا واحدًا
            return response()->json($user);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ]);
        }
    }


    public function show_users_without_teacher(Request $request)
    {
        try {
            if (!$request->daora_id) {
                return response()->json([
                    'message' => 'daora_id is required'
                ], 401);
            }

            // 🔎 هات فقط الطلاب اللي ما عندهم معلّم وضمن نفس الدورة
            $students = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereNull('students.teacher_id')
                ->where('users.daora_id', $request->daora_id)
                ->select(
                    'students.user_id',
                    'students.ended_quraan_in_aukaf',
                    'users.name',
                    'users.phone_number',
                    'users.age'
                )
                ->get();

            return response()->json($students);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json([
                    'status'   => 'error',
                    'messages' => 'المستخدم غير موجود'
                ], 404);
            }

            // القوانين (بدون إلزامية ended_quraan_in_aukaf)
            $rules = [
                'name'         => 'required|string|max:255|unique:users,name,' . $user->id,
                'phone_number' => 'required|string|max:20',
                'age'          => 'required|integer|min:1',
                'ended_quraan_in_aukaf' => 'nullable|string'
            ];

            $validated = Validator::make($request->all(), $rules);

            if ($validated->fails()) {
                return response()->json([
                    'status'   => 'error',
                    'messages' => 'البيانات غير صالحة',
                    'errors'   => $validated->errors()
                ], 403);
            }

            // تحديث جدول users
            $user->update([
                'name'         => $request->name,
                'phone_number' => $request->phone_number,
                'age'          => $request->age,
            ]);

            // تحديث جدول students فقط إذا وصلت ended_quraan_in_aukaf
            if ($request->filled('ended_quraan_in_aukaf')) {
                $student = Student::where('user_id', $user->id)->first();
                if ($student) {
                    $student->update([
                        'ended_quraan_in_aukaf' => $request->ended_quraan_in_aukaf
                    ]);
                }
            }

            return response()->json([
                'status'   => 'success',
                'messages' => 'تم تحديث البيانات بنجاح ✅',
                'data'     => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'phone_number' => $user->phone_number,
                    'age'          => $user->age,
                    'ended_quraan_in_aukaf' => $request->ended_quraan_in_aukaf ?? "لم يتم التعديل على الاجزاء "
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
