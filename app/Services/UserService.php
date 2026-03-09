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
use Illuminate\Support\Facades\Notification;
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
            $photoName = uniqid('user_').'.'.$photoFile->getClientOriginalExtension();
            $manager = new ImageManager(new Driver);
            $img = $manager->read($photoFile);
            $img->scaleDown(600);
            $savePath = storage_path('app/public/uploadsUser');
            if (! file_exists($savePath)) {
                mkdir($savePath, 0777, true);
            }
            $img->save($savePath.'/'.$photoName);
            $photoHash = hash_file('sha256', $savePath.'/'.$photoName);
        }

        $ids = $this->getOrCreateJobAndArea($data['job'], $data['address']);

        $user = User::create([
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['password']),
            'age' => $data['age'],
            'family_status' => $data['family_status'] ?? null,
            'privilege' => 1,
            'photo' => $photoName,
            'photo_hash' => $photoHash,
            'daora_id' => $data['daora_id'],
            'job_id' => $ids['job_id'],
            'area_id' => $ids['area_id'],
        ]);

        $user->load('job', 'area');

        $student = student::create([
            'user_id' => $user->id,
            'ended_quraan_in_aukaf' => $data['ended_quraan_in_aukaf'],
            'missing_days' => 0,
        ]);

        $daora = Daora::find($data['daora_id']);
        if ($daora) {
            $daora->increment('number_of_students');
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

        if ($userToFetch->privilege == 1) {
            $student = student::where('user_id', $userToFetch->id)->first();
            if ($student) {
                $teacher_id = $student->teacher_id;
                if ($teacher_id) {
                    $teacher = User::find($teacher_id);
                    $teacher_name = $teacher ? $teacher->name : null;
                }
            }
        }

        return [
            'user' => $userToFetch,
            'teacher_id' => $teacher_id,
            'teache_name' => $teacher_name,
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

            $oldJobId = $user->job_id;
            $oldAreaId = $user->area_id;

            $newJob = Job::firstOrCreate(['name' => strtolower(trim($data['job']))]);
            $newArea = Area::firstOrCreate(['name' => strtolower(trim($data['address']))]);

            $user->update([
                'phone_number' => $data['phone_number'],
                'age' => $data['age'],
                'family_status' => $data['family_status'] ?? null,
                'job_id' => $newJob->id,
                'area_id' => $newArea->id,
            ]);

            if ($newJob->id !== $oldJobId) {
                $newJob->increment('user_count');
                if ($oldJobId && $oldJob = Job::find($oldJobId)) {
                    $oldJob->decrement('user_count');
                }
            }

            if ($newArea->id !== $oldAreaId) {
                $newArea->increment('user_count');
                if ($oldAreaId && $oldArea = Area::find($oldAreaId)) {
                    $oldArea->decrement('user_count');
                }
            }
        });

        return true;
    }

    public function updatePhoto($photoFile, $photo_hash, $authUser)
    {
        $new_photo = rand(0, 9999999).'.'.$photoFile->getClientOriginalExtension();
        $old_Photo = $authUser->photo;
        $save_path = storage_path('\uploadsUser');

        $manager = new ImageManager(new Driver);
        $image = $manager->read($photoFile);
        $image->save($save_path.'\\'.$new_photo);

        File::delete($save_path.'\\'.$old_Photo);

        $updated = User::where('id', $authUser->id)->update([
            'photo' => $new_photo,
            'photo_hash' => $photo_hash,
        ]);

        return $updated;
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

        $admins = User::with(['job', 'area'])
            ->where('privilege', '>', 1)
            ->where('daora_id', $daora_id)
            ->get();

        return $admins->map(function ($admin) {
            $adminData = $admin->toArray();
            $adminData['job_name'] = $admin->job ? $admin->job->name : null;
            $adminData['area_name'] = $admin->area ? $admin->area->name : null;
            unset($adminData['job'], $adminData['area']);

            // Get Halaka info if exists
            $halaka = \App\Models\Halaka::where('teacher_id', $admin->id)->first();
            if ($halaka) {
                $adminData['has_halaka'] = true;
                $adminData['halaka_students_count'] = $halaka->students_count;
            } else {
                $adminData['has_halaka'] = false;
                $adminData['halaka_students_count'] = 0;
            }

            return $adminData;
        });
    }

    public function takeStudent($user_id, $authUser)
    {
        if ($authUser->privilege <= 1) {
            throw new \Exception('لا تملك صلاحية استلام طلاب ', 403);
        }

        if (! $user_id) {
            throw new \Exception('user_id is required', 400);
        }

        $student_id = student::where('user_id', $user_id)->first('id')->id;

        if (student::find($student_id)->teacher_id == null) {
            $update = student::where('id', $student_id)->update([
                'teacher_id' => $authUser->id,
            ]);

            $halaka = \App\Models\Halaka::firstOrCreate(
                ['teacher_id' => $authUser->id],
                ['daora_id' => $authUser->daora_id]
            );
            $halaka->increment('students_count');

            student::where('id', $student_id)->update([
                'halaka_id' => $halaka->id,
            ]);

            $the_user_to_note = User::where('id', '=', student::find($student_id)->user_id)->get();
            $teacher = User::find($authUser->id);
            Notification::send($the_user_to_note, new taken_by_teacher($teacher));

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

        $studentRecord = student::where('user_id', $user_id)->first();
        if ($studentRecord && $studentRecord->teacher_id) {
            $teacher_id = $studentRecord->teacher_id;
            $update = student::where('id', $studentRecord->id)->update([
                'teacher_id' => null,
                'halaka_id' => null,
            ]);

            \App\Models\Halaka::where('teacher_id', $teacher_id)->decrement('students_count');

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
        if (!$halaka) {
            throw new \Exception('الحلقة غير موجودة', 404);
        }

        // Unassign all students from this halaka
        \App\Models\student::where('halaka_id', $halaka_id)->update([
            'teacher_id' => null,
            'halaka_id' => null,
        ]);

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
            ->whereNull('students.teacher_id')
            ->where('users.daora_id', $daora_id)
            ->select(
                'students.user_id',
                'students.ended_quraan_in_aukaf',
                'users.name',
                'users.phone_number',
                'users.age'
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

        $user->update([
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'age' => $data['age'],
        ]);

        if (isset($data['ended_quraan_in_aukaf'])) {
            $student = Student::where('user_id', $user->id)->first();
            if ($student) {
                $student->update([
                    'ended_quraan_in_aukaf' => $data['ended_quraan_in_aukaf'],
                ]);
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
            'age' => $user->age,
            'ended_quraan_in_aukaf' => $data['ended_quraan_in_aukaf'] ?? 'لم يتم التعديل على الاجزاء ',
        ];
    }
}
