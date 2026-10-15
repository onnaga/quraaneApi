<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Daora;
use App\Models\Job;
use App\Models\point;
use App\Models\student;
use App\Models\User;
use App\Notifications\taken_by_teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserService
{
    private function getOrCreateJobAndArea(string $jobName, string $areaName): array
    {
        $job = Job::firstOrCreate(['name' => strtolower(trim($jobName))]);
        $job->increment('user_count');

        $area = Area::firstOrCreate(['name' => strtolower(trim($areaName))]);
        $area->increment('user_count');

        return [
            'job_id' => $job->id,
            'area_id' => $area->id,
        ];
    }

    public function toggleTeacherPrivilege($user_id, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('غير مصرح لك بالقيام بهذه العملية.', 403);
        }

        $user = User::find($user_id);
        if (! $user) {
            throw new \Exception('المستخدم غير موجود', 404);
        }

        if (! in_array($user->privilege, [2, 3])) {
            throw new \Exception('لا يمكن تغيير صلاحية هذا المستخدم.', 400);
        }

        if ($authUser->privilege == 3 && $user->privilege == 3) {
            if ($authUser->id >= $user->id) {
                throw new \Exception('لا يمكنك تعديل صلاحيات مشرف تم إضافته قبلك و بنفس صلاحياتك.', 403);
            }
        }

        $newPrivilege = ($user->privilege == 2) ? 3 : 2;
        $user->privilege = $newPrivilege;
        $user->save();

        return $user;
    }

    public function getSuggestions()
    {
        $jobs = Job::pluck('name');
        $areas = Area::pluck('name');

        return [
            'jobs' => $jobs,
            'areas' => $areas,
        ];
    }

    public function registerStudent($data, $photoFile = null)
    {
        $photoName = null;
        $photoHash = null;

        if ($photoFile) {
            $stored = app(\App\Services\ImageStorageService::class)->store($photoFile, 'uploadsUser');
            $photoName = $stored['photo'];
            $photoHash = $stored['photo_hash'];
        }

        $ids = $this->getOrCreateJobAndArea($data['job'], $data['address']);

        $user = User::create([
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['password']),
            'age' => $data['birth_date'] ?? null,
            'family_status' => $data['family_status'] ?? null,
            'family_members_count' => $data['family_members_count'] ?? null,
            'notes' => $data['notes'] ?? null,
            'skills' => $data['skills'] ?? null,
            'certificates' => $data['certificates'] ?? null,
            'enrollment_date' => $data['enrollment_date'] ?? null,
            'privilege' => 1,
            'photo' => $photoName,
            'photo_hash' => $photoHash,
            'daora_id' => $data['daora_id'] ?? null,
            'job_id' => $ids['job_id'],
            'area_id' => $ids['area_id'],
        ]);

        $user->load('job', 'area');

        $student = student::create([
            'user_id' => $user->id,
            'ended_quraan_in_aukaf' => $data['ended_quraan_in_aukaf'] ?? '[]',
            'current_grade' => $data['current_grade'] ?? null,
            'school_name' => $data['school_name'] ?? null,
            'father_phone' => $data['father_phone'] ?? null,
            'mother_phone' => $data['mother_phone'] ?? null,
            'landline_phone' => $data['landline_phone'] ?? null,
            'other_phone' => $data['other_phone'] ?? null,
            'preferred_contact' => $data['preferred_contact'] ?? null,
            'previous_mosques' => $data['previous_mosques'] ?? null,
            'missing_days' => 0,
        ]);

        if (isset($data['daora_id'])) {
            $daora = Daora::find($data['daora_id']);
            if ($daora) {
                $daora->increment('number_of_students');
            }
        }

        return [
            'user' => $user,
            'teacher_id' => $student->teacher_id,
            'teacher_name' => $student->teacher_id ? User::find($student->teacher_id)->name : null,
        ];
    }

    public function getPersonalData($idRequest, $authUser)
    {
        $userToFetch = null;

        if ($idRequest) {
            $userToFetch = User::find($idRequest);
            if (! $userToFetch) {
                throw new \Exception('المستخدم المطلوب غير موجود.', 404);
            }
        } else {
            $userToFetch = User::find($authUser->id);
        }

        $userToFetch->load('job', 'area');

        $teacher_id = null;
        $teacher_name = null;
        $studentData = null;

        if ($userToFetch->privilege == 1) {
            $student = student::where('user_id', $userToFetch->id)->first();
            if ($student) {
                $teacher_id = $student->teacher_id;
                if ($teacher_id) {
                    $teacher = User::find($teacher_id);
                    $teacher_name = $teacher ? $teacher->name : null;
                }
                $studentData = $student;
            }
        }

        return [
            'user' => $userToFetch,
            'teacher_id' => $teacher_id,
            'teache_name' => $teacher_name,
            'student' => $studentData,
        ];
    }

    public function updatePassword($old_password, $new_password, $authUser)
    {
        if (! Hash::check($old_password, $authUser->password)) {
            throw new \Exception('كلمة السر السابقة خاطئة', 401);
        }

        $update = User::where('id', $authUser->id)->update([
            'password' => Hash::make($new_password),
        ]);

        return $update;
    }

    public function updateDetails($data, $authUser)
    {
        DB::transaction(function () use ($data, $authUser) {
            $user = User::find($authUser->id);
            $userData = [];

            // 1. معالجة البيانات المباشرة
            if (array_key_exists('phone_number', $data)) {
                $userData['phone_number'] = $data['phone_number'];
            }
            if (array_key_exists('birth_date', $data)) {
                $userData['age'] = $data['birth_date']; // تذكر أن العمود في الداتا بيز اسمه age
            }
            if (array_key_exists('family_status', $data)) {
                $userData['family_status'] = $data['family_status'];
            }

            // 2. معالجة الوظيفة (Job) - فقط إذا تم إرسالها
            if (array_key_exists('job', $data) && ! empty($data['job'])) {
                $oldJobId = $user->job_id;
                $newJob = Job::firstOrCreate(['name' => strtolower(trim($data['job']))]);

                $userData['job_id'] = $newJob->id;

                // تحديث العدادات فقط إذا تغيرت الوظيفة
                if ($newJob->id !== $oldJobId) {
                    $newJob->increment('user_count');
                    if ($oldJobId && $oldJob = Job::find($oldJobId)) {
                        $oldJob->decrement('user_count');
                    }
                }
            }

            // 3. معالجة المنطقة/العنوان (Area) - فقط إذا تم إرسالها
            if (array_key_exists('address', $data) && ! empty($data['address'])) {
                $oldAreaId = $user->area_id;
                $newArea = Area::firstOrCreate(['name' => strtolower(trim($data['address']))]);

                $userData['area_id'] = $newArea->id;

                // تحديث العدادات فقط إذا تغيرت المنطقة
                if ($newArea->id !== $oldAreaId) {
                    $newArea->increment('user_count');
                    if ($oldAreaId && $oldArea = Area::find($oldAreaId)) {
                        $oldArea->decrement('user_count');
                    }
                }
            }

            // 4. معالجة الحقول الاختيارية الأخرى
            // ✅ تم إضافة enrollment_date إلى هذه المصفوفة
            $optionalFields = ['family_members_count', 'notes', 'skills', 'certificates', 'enrollment_date'];
            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $userData[$field] = $data[$field];
                }
            }

            // 5. تنفيذ التحديث في قاعدة البيانات فقط إذا كانت هناك بيانات لتحديثها
            if (! empty($userData)) {
                $user->update($userData);
            }
        });

        return true;
    }

    private function replaceUserPhoto($user, $file)
    {
        $images = app(\App\Services\ImageStorageService::class);
        $oldPhoto = $user->photo;
        
        $stored = $images->store($file, 'uploadsUser');
        
        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $stored) {
            return $user->update([
                'photo' => $stored['photo'],
                'photo_hash' => $stored['photo_hash'],
            ]);
        });
        
        if ($oldPhoto && $oldPhoto !== $stored['photo']) {
            $images->delete('uploadsUser', $oldPhoto);
        }
        
        return ['photo' => $stored['photo'], 'photo_hash' => $stored['photo_hash'], 'updated' => $updated];
    }

    public function updatePhoto($photoFile, $authUser)
    {
        return $this->replaceUserPhoto($authUser, $photoFile);
    }

    public function updateStudentPhoto($photoFile, $studentId, $authUser)
    {
        if ($authUser->privilege <= 1) {
            throw new \Exception('لا تملك صلاحية تعديل صور الطلاب', 403);
        }

        $studentUser = User::find($studentId);
        if (! $studentUser) {
            throw new \Exception('الطالب غير موجود', 404);
        }

        return $this->replaceUserPhoto($studentUser, $photoFile);
    }

    public function addAdmin($data, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('غير مصرح لك بإضافة أساتذة', 403);
        }

        DB::transaction(function () use ($data) {
            $ids = $this->getOrCreateJobAndArea($data['job'], $data['address']);

            $user = User::create([
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'privilege' => $data['privilege'],
                'daora_id' => $data['daora_id'] ?? null,
                'family_status' => $data['family_status'] ?? null,
                'job_id' => $ids['job_id'],
                'area_id' => $ids['area_id'],
            ]);

            if ($user->privilege == 2 || $user->privilege == 3) {
                \App\Models\Halaka::create([
                    'teacher_id' => $user->id,
                    'daora_id' => $user->daora_id,
                ]);
            }
        });

        return true;
    }

    public function deleteUser($user_id, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('عذرا , انت لا تمتلك صلاحية الحذف', 403);
        }

        $user = User::findOrFail($user_id);

        if ($user->privilege == 3) {
            $isAdminOfDaora = DB::table('daoras')
                ->where('admin_id', $user_id)
                ->exists();

            if ($isAdminOfDaora) {
                throw new \Exception('لا يمكن حذف هذا المستخدم لأنه مسؤول عن دورة', 403);
            }
        }

        if (! in_array($user->privilege, [2, 3])) {
            $latestIds = DB::table('students')
                ->where('user_id', $user_id)
                ->pluck('latest_id')
                ->filter()
                ->toArray();

            DB::table('students')->where('user_id', $user_id)->delete();

            if (! empty($latestIds)) {
                DB::table('latests')->whereIn('id', $latestIds)->delete();
            }

            DB::table('reports')->where('user_id', $user_id)->delete();
            DB::table('points')->where('user_id', $user_id)->delete();
            DB::table('user_tests')->where('user_id', $user_id)->delete();
        }

        DB::table('notifications')
            ->where('notifiable_type', 'App\Models\User')
            ->where('notifiable_id', $user_id)
            ->delete();

        return $user->delete();
    }

    public function showAllTeachers($daora_id)
    {
        if (! $daora_id) {
            throw new \Exception('daora_id is required', 400);
        }

        // ✅ 1. جلب رقم مدير الدورة من جدول الدورات
        $daora = \App\Models\Daora::find($daora_id);
        $admin_id = $daora ? $daora->admin_id : null;

        $admins = User::with(['job', 'area'])
            ->where('privilege', '>', 1)
            ->where('daora_id', $daora_id)
            ->get();

        // ✅ 2. تجهيز قائمة الأساتذة
        $teachersList = $admins->map(function ($admin) {
            $halaka = \App\Models\Halaka::where('teacher_id', $admin->id)->first();

            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'phone_number' => $admin->phone_number,
                'privilege' => $admin->privilege,
                'age' => $admin->age,
                'birth_date' => $admin->birth_date,
                'photo' => $admin->photo,
                'photo_hash' => $admin->photo_hash,
                'family_status' => $admin->family_status,
                'family_members_count' => $admin->family_members_count,
                'notes' => $admin->notes,
                'skills' => $admin->skills,
                'certificates' => $admin->certificates,
                'job_name' => $admin->job ? $admin->job->name : null,
                'area_name' => $admin->area ? $admin->area->name : null,
                'enrollment_date' => $admin->enrollment_date,
                'created_at' => $admin->created_at,
                'has_halaka' => $halaka ? true : false,
                'halaka_students_count' => $halaka ? $halaka->students_count : 0,
            ];
        });

        // ✅ 3. إرجاع المصفوفة الجديدة التي تحتوي على المدير والقائمة
        return [
            'admin_id' => $admin_id,
            'teachers' => $teachersList,
        ];
    }

public function takeStudent($user_id, $authUser)
    {
        if ($authUser->privilege <= 1) {
            throw new \Exception('لا تملك صلاحية استلام طلاب ', 403);
        }

        if (! $user_id) {
            throw new \Exception('user_id is required', 400);
        }

        $student_id = \App\Models\student::where('user_id', $user_id)->first('id')->id;

        if (\App\Models\student::find($student_id)->teacher_id == null) {
            $update = \App\Models\student::where('id', $student_id)->update([
                'teacher_id' => $authUser->id,
            ]);

            $halaka = \App\Models\Halaka::firstOrCreate(
                ['teacher_id' => $authUser->id],
                ['daora_id' => $authUser->daora_id]
            );
            $halaka->increment('students_count');

            \App\Models\student::where('id', $student_id)->update([
                'halaka_id' => $halaka->id,
            ]);

            // تسجيل تاريخ بدء استلام الطالب للأستاذ الحالي
            \Illuminate\Support\Facades\DB::table('student_teacher')->insert([
                'teacher_id' => $authUser->id,
                'student_id' => $student_id,
                'started_at' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 👇👇 التعديل الجديد: تحديث الأستاذ في جدول التقارير 👇👇
            \App\Models\report::where('user_id', $user_id)->update([
                'teacher_id' => $authUser->id,
            ]);
            // 👆👆 نهاية التعديل 👆👆

            $the_user_to_note = \App\Models\User::where('id', '=', \App\Models\student::find($student_id)->user_id)->get();
            $teacher = \App\Models\User::find($authUser->id);
            \Illuminate\Support\Facades\Notification::send($the_user_to_note, new taken_by_teacher($teacher));

            return $update;
        } else {
            throw new \Exception('يوجد استاذ لهذا الطالب بالفعل , يمكنك الطلب من الاستاذ أن يترك هذا الطالب ومن ثم تعيد المحاولة', 409);
        }
    }

public function leaveStudent($user_id, $authUser)
    {
        if ($authUser->privilege <= 1) {
            throw new \Exception('لا تملك صلاحية ترك مستخدمين', 403);
        }

        if (! $user_id) {
            throw new \Exception('user_id is required', 400);
        }

        $studentRecord = \App\Models\student::where('user_id', $user_id)->first();
        if ($studentRecord && $studentRecord->teacher_id) {
            $teacher_id = $studentRecord->teacher_id;

            $update = \App\Models\student::where('id', $studentRecord->id)->update([
                'teacher_id' => null,
                'halaka_id' => null,
            ]);

            \App\Models\Halaka::where('teacher_id', $teacher_id)->decrement('students_count');

            // تسجيل تاريخ انتهاء العلاقة السابقة (إنهاء التدريس)
            \Illuminate\Support\Facades\DB::table('student_teacher')
                ->where('student_id', $studentRecord->id)
                ->where('teacher_id', $teacher_id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => now()->toDateString(),
                    'updated_at' => now(),
                ]);

            // 👇👇 التعديل الجديد: تصفير الأستاذ في جدول التقارير 👇👇
            \App\Models\report::where('user_id', $user_id)->update([
                'teacher_id' => null,
            ]);
            // 👆👆 نهاية التعديل 👆👆

            return $update;
        }

        return false;
    }
public function deleteHalaka($halaka_id, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('لا تملك صلاحية حذف الحلقات', 403);
        }

        $halaka = \App\Models\Halaka::find($halaka_id);
        if (! $halaka) {
            throw new \Exception('الحلقة غير موجودة', 404);
        }

        $teacher_id = $halaka->teacher_id;
        
        // جلب الطلاب ومعرفات المستخدمين الخاصة بهم
        $studentsInHalaka = \App\Models\student::where('halaka_id', $halaka_id)->get(['id', 'user_id']);
        $studentIds = $studentsInHalaka->pluck('id')->toArray();
        $userIds = $studentsInHalaka->pluck('user_id')->toArray();

        if (!empty($studentIds)) {
            // تسجيل تاريخ الانتهاء لجميع طلاب الحلقة دفعة واحدة
            \Illuminate\Support\Facades\DB::table('student_teacher')
                ->whereIn('student_id', $studentIds)
                ->where('teacher_id', $teacher_id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => now()->toDateString(),
                    'updated_at' => now(),
                ]);

            // إزالة ارتباط الطلاب من هذه الحلقة
            \App\Models\student::whereIn('id', $studentIds)->update([
                'teacher_id' => null,
                'halaka_id' => null,
            ]);
        }

        // تصفير الأستاذ في جدول التقارير لجميع طلاب الحلقة
        if (!empty($userIds)) {
            \App\Models\report::whereIn('user_id', $userIds)->update([
                'teacher_id' => null,
            ]);
        }

        return $halaka->delete();
    }

    public function addWantingStudents($data, $authUser)
    {
        if ($authUser->privilege <= 1) {
            throw new \Exception('لا تملك صلاحية التفقد', 403);
        }

        $teacherId = $authUser->id;
        $absentStudentIds = $data['students'] ?? [];
        $glob = $data['glob'] ?? false;

        if (! is_array($absentStudentIds)) {
            throw new \Exception('البيانات المرسلة غير صحيحة، قائمة الطلاب مطلوبة', 400);
        }

        if ($authUser->privilege == 3 && $glob) {
            student::whereIn('user_id', $absentStudentIds)
                ->update([
                    'missing_days' => DB::raw('missing_days + 1'),
                    'last_attendance_status' => 'absent',
                ]);

            student::whereNotIn('user_id', $absentStudentIds)
                ->update([
                    'last_attendance_status' => 'present',
                ]);
        } else {
            student::where('teacher_id', $teacherId)
                ->whereIn('user_id', $absentStudentIds)
                ->update([
                    'missing_days' => DB::raw('missing_days + 1'),
                    'last_attendance_status' => 'absent',
                ]);

            student::where('teacher_id', $teacherId)
                ->whereNotIn('user_id', $absentStudentIds)
                ->update([
                    'last_attendance_status' => 'present',
                ]);
        }

        // تسجيل يوم الغياب في جدول absence_records
        foreach ($absentStudentIds as $studentUserId) {
            \Illuminate\Support\Facades\DB::table('absence_records')->updateOrInsert(
                [
                    'user_id' => $studentUserId,
                    'absence_date' => now()->toDateString(),
                ],
                [
                    'teacher_id' => $teacherId,
                    'updated_at' => now(),
                ]
            );
        }

        return true;
    }

    public function showNotifications($authUser)
    {
        $User_id = $authUser->id;

        $unreaded_notes = DB::table('notifications')->where([['notifiable_id', '=', $User_id], ['read_at', '=', null]])->get('data');
        $readed_notes = DB::table('notifications')->where([['notifiable_id', '=', $User_id], ['read_at', '!=', null]])->get('data');

        $decoded_unreaded_notes = [];
        $decoded_readed_notes = [];

        foreach ($unreaded_notes as $unreaded_note) {
            array_push($decoded_unreaded_notes, json_decode($unreaded_note->data));
        }
        foreach ($readed_notes as $readed_note) {
            array_push($decoded_readed_notes, json_decode($readed_note->data));
        }

        return [
            'unreaded_notes' => $decoded_unreaded_notes,
            'readed_notes' => $decoded_readed_notes,
        ];
    }

    public function readNotifications($authUser)
    {
        $User_id = $authUser->id;
        $make_readed = DB::table('notifications')->where([['notifiable_id', '=', $User_id], ['read_at', '=', null]])->update([
            'read_at' => now(),
        ]);

        return $make_readed;
    }

    public function getScore($idRequest, $authUser)
    {
        if ($authUser->privilege > 1) {
            if (! $idRequest) {
                throw new \Exception('the id is required', 400);
            }
            $student = student::where('user_id', $idRequest)->first(['point_id', 'ended_quraan_in_aukaf', 'missing_days']);
        } else {
            $student = student::where('user_id', $authUser->id)->first(['point_id', 'ended_quraan_in_aukaf', 'missing_days']);
        }

        if ($student) {
            $student->points = point::find($student->point_id);
        }

        return $student;
    }

    public function getUserById($user_id)
    {
        if (! $user_id) {
            throw new \Exception('user id is required', 400);
        }

        if (User::find($user_id) == null) {
            throw new \Exception('هذا المستحدم غير موجود', 404);
        }

        $user = DB::table('users')
            ->leftJoin('jobs', 'users.job_id', '=', 'jobs.id')
            ->leftJoin('areas', 'users.area_id', '=', 'areas.id')
            ->select(
                'users.id',
                'users.name',
                'users.phone_number',
                'users.age',
                'users.family_status',
                'jobs.name as job_name',
                'areas.name as area_name'
            )
            ->where('users.id', $user_id)
            ->first();

        return $user;
    }

    public function showUsersWithoutTeacher($daora_id)
    {
        if (! $daora_id) {
            throw new \Exception('daora_id is required', 401);
        }

        $students = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('jobs', 'users.job_id', '=', 'jobs.id') // جلب بيانات العمل
            ->whereNull('students.teacher_id')
            ->where('users.daora_id', $daora_id)
            ->select(
                'students.user_id',
                'students.ended_quraan_in_aukaf',
                'students.father_phone',
                'students.mother_phone',
                'users.name',
                'users.phone_number',
                'users.age',
                'users.family_members_count',
                'users.notes',
                'jobs.name as job_name'
            )
            ->get();

        return $students;
    }

    public function updateUser($data, $user_id)
    {
        $user = User::find($user_id);
        if (! $user) {
            throw new \Exception('المستخدم غير موجود', 404);
        }

        $userData = [
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
        ];

        // ✅ تحديث الحالة العائلية
        if (array_key_exists('family_status', $data)) {
            $userData['family_status'] = $data['family_status'];
        }

        // ✅ تحديث العمل (Job) مع الحفاظ على العدادات
        if (array_key_exists('job', $data) && ! empty($data['job'])) {
            $oldJobId = $user->job_id;
            $newJob = \App\Models\Job::firstOrCreate(['name' => strtolower(trim($data['job']))]);
            $userData['job_id'] = $newJob->id;

            if ($newJob->id !== $oldJobId) {
                $newJob->increment('user_count');
                if ($oldJobId && $oldJob = \App\Models\Job::find($oldJobId)) {
                    $oldJob->decrement('user_count');
                }
            }
        }

        // ✅ تحديث السكن (Area) مع الحفاظ على العدادات
        if (array_key_exists('address', $data) && ! empty($data['address'])) {
            $oldAreaId = $user->area_id;
            $newArea = \App\Models\Area::firstOrCreate(['name' => strtolower(trim($data['address']))]);
            $userData['area_id'] = $newArea->id;

            if ($newArea->id !== $oldAreaId) {
                $newArea->increment('user_count');
                if ($oldAreaId && $oldArea = \App\Models\Area::find($oldAreaId)) {
                    $oldArea->decrement('user_count');
                }
            }
        }

        // إضافة الحقول الأخرى
        if (isset($data['birth_date'])) {
            $userData['age'] = $data['birth_date'];
        }
        if (isset($data['family_members_count'])) {
            $userData['family_members_count'] = $data['family_members_count'];
        }
        if (array_key_exists('notes', $data)) {
            $userData['notes'] = $data['notes'];
        }
        if (array_key_exists('skills', $data)) {
            $userData['skills'] = $data['skills'];
        }
        if (array_key_exists('certificates', $data)) {
            $userData['certificates'] = $data['certificates'];
        }
        if (array_key_exists('enrollment_date', $data)) {
            $userData['enrollment_date'] = $data['enrollment_date'];
        }

        $user->update($userData);
        // تحديث بيانات الطالب (باقي الكود يبقى كما هو)
        $student = Student::where('user_id', $user->id)->first();
        if ($student) {
            $studentData = [];

            if (isset($data['ended_quraan_in_aukaf'])) {
                $studentData['ended_quraan_in_aukaf'] = $data['ended_quraan_in_aukaf'];
            }
            if (array_key_exists('current_grade', $data)) {
                $studentData['current_grade'] = $data['current_grade'];
            }
            if (array_key_exists('school_name', $data)) {
                $studentData['school_name'] = $data['school_name'];
            }
            if (array_key_exists('father_phone', $data)) {
                $studentData['father_phone'] = $data['father_phone'];
            }
            if (array_key_exists('mother_phone', $data)) {
                $studentData['mother_phone'] = $data['mother_phone'];
            }
            if (array_key_exists('landline_phone', $data)) {
                $studentData['landline_phone'] = $data['landline_phone'];
            }
            if (array_key_exists('other_phone', $data)) {
                $studentData['other_phone'] = $data['other_phone'];
            }
            if (array_key_exists('preferred_contact', $data)) {
                $studentData['preferred_contact'] = $data['preferred_contact'];
            }
            if (array_key_exists('previous_mosques', $data)) {
                $studentData['previous_mosques'] = $data['previous_mosques'];
            }

            if (! empty($studentData)) {
                $student->update($studentData);
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
            'age' => $user->age,
            'ended_quraan_in_aukaf' => $data['ended_quraan_in_aukaf'] ?? 'لم يتم التعديل على الاجزاء',
        ];
    }

    public function getStudentTeachersHistory($identifier, $authUser)
    {
        // 1. البحث عن الطالب عبر user_id أو عبر id كاحتياط
        $student = \Illuminate\Support\Facades\DB::table('students')
            ->where('user_id', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        // 🛑 إذا لم يجده، سنجعل رسالة الخطأ تطبع الرقم الذي وصلها من فلاتر!
        if (! $student) {
            throw new \Exception('الطالب غير موجود. الرقم الذي أرسله التطبيق هو: ('.$identifier.')', 404);
        }

        // 2. التحقق من الصلاحيات وطباعة الأرقام في حال الرفض لكشف الخلل
        if ($authUser->privilege < 2 && $authUser->id != $student->user_id) {
            throw new \Exception("عذراً! رقم حسابك هو ({$authUser->id}) وسجل الطالب يعود للحساب رقم ({$student->user_id}). لا تملك صلاحية.", 403);
        }

        // 3. جلب السجل بشكل طبيعي
        $history = \Illuminate\Support\Facades\DB::table('student_teacher')
            ->join('users as teachers', 'student_teacher.teacher_id', '=', 'teachers.id')
            ->where('student_teacher.student_id', $student->id)
            ->select(
                'teachers.name as teacher_name',
                'student_teacher.started_at',
                'student_teacher.ended_at'
            )
            ->orderBy('student_teacher.started_at', 'desc')
            ->get();

        return $history;
    }

    public function getUserBehavior($studentId)
    {
        return \Illuminate\Support\Facades\DB::table('user_behaviors')
            ->where('user_id', $studentId)
            ->first();
    }

    public function updateUserBehavior($studentId, $teacherId, $data)
    {
        // ستقوم هذه الدالة بتحديث كل الحقول المرسلة.
        // إذا قام الأستاذ بمسح النص، سيصل فارغاً (أو null) وسيتم مسحه من القاعدة.
        \Illuminate\Support\Facades\DB::table('user_behaviors')->updateOrInsert(
            ['user_id' => $studentId],
            [
                'teacher_id' => $teacherId,
                'traits' => $data['traits'] ?? null,
                'habits' => $data['habits'] ?? null,
                'discipline' => $data['discipline'] ?? null,
                'morals' => $data['morals'] ?? null,
                'skills' => $data['skills'] ?? null,
                'positive_actions' => $data['positive_actions'] ?? null,
                'negative_actions' => $data['negative_actions'] ?? null,
                'updated_at' => now(),
            ]
        );

        return true;
    }
}
