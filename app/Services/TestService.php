<?php

namespace App\Services;

use App\Models\test;
use App\Models\User;
use App\Models\user_test;
use App\Models\student;
use App\Notifications\test_added;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class TestService
{
    public function addNewTest($data, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('لا تملك الصلاحية لإضافة سبر', 403);
        }

        $exists = test::where('End_time', $data['End_time'])
            ->where('daora_id', $data['daora_id'])
            ->first();

        if ($exists) {
            throw new \Exception('يوجد سبر بنفس التاريخ والوقت لهذه الدورة، يرجى تغييره', 409);
        }

        $created_test = test::create([
            'End_time' => $data['End_time'],
            'notes'    => $data['notes'],
            'aukaf'    => (bool)$data['aukaf'],
            'daora_id' => $data['daora_id'],
            'number'   => 0,
        ]);

        $users = User::where('daora_id', $created_test->daora_id)->get();

        try {
            Notification::send($users, new test_added($created_test));
        } catch (\Exception $e) {
            Log::error('Failed to send test notification: ' . $e->getMessage());
        }

        return $created_test;
    }

    public function getTests($daora_id, $authUser)
    {
        $tests = test::where('daora_id', $daora_id)
            ->orderBy('End_time', 'DESC')
            ->get();

        $registeredTests = [];
        if ($authUser->privilege == 1) {
            $registeredTests = DB::table('user_tests')
                ->where('user_id', $authUser->id)
                ->pluck('test_id')
                ->toArray();
        }

        return [
            'tests' => $tests,
            'registered_tests' => $registeredTests,
        ];
    }

    public function acceptTest($test_id, $authUser)
    {
        if ($authUser->privilege != 1) {
            throw new \Exception('لا يمكن للأساتذة التسجيل على سبر أنشئ حساب طالب وحاول مجددا', 403);
        }

        $test = test::find($test_id);
        if (!$test) {
            throw new \Exception('الرقم الخاص بالسبر خاطئ', 404);
        }

        if ($test->End_time <= Carbon::now()) {
            throw new \Exception('إن هذا السبر قد انتهى حاول التسجيل في سبر غير منتهي', 400);
        }

        $user_id = $authUser->id;
        $user_test = user_test::where([
            ['test_id', '=', $test_id],
            ['user_id', '=', $user_id]
        ])->first();

        if ($user_test) {
            throw new \Exception('تم قبوله سابقا ', 409);
        }

        $user_test = user_test::create([
            'test_id'             => $test_id,
            'user_id'             => $user_id,
            'the_part_to_test_in' => 0,
            'rating'              => 0,
            'notes'               => 0,
        ]);

        $test->increment('number');
        return $user_test;
    }

    public function deleteAcceptedTest($test_id, $authUser)
    {
        if ($authUser->privilege != 1) {
            throw new \Exception('لا يمكن للأساتذة التسجيل على سبر', 403);
        }

        $test = test::find($test_id);
        if (!$test) {
            throw new \Exception('رقم السبر غير صحيح', 404);
        }

        if ($test->End_time <= Carbon::now()) {
            throw new \Exception('السبر قد انتهى بالفعل', 400);
        }

        $user_id = $authUser->id;
        $deleted = user_test::where([
            ['test_id', '=', $test_id],
            ['user_id', '=', $user_id],
            ['the_part_to_test_in', '=', 0]
        ])->delete();

        if ($deleted) {
            $test->decrement('number');
        }

        return $deleted;
    }

    public function acceptTestForStudent($test_id, $student_id, $authUser)
    {
        if ($authUser->privilege < 2) {
            throw new \Exception('لا تملك الصلاحية لتسجيل طالب في السبر', 403);
        }

        $test = test::find($test_id);
        if (!$test) {
            throw new \Exception('الرقم الخاص بالسبر خاطئ', 404);
        }

        if ($test->End_time <= Carbon::now()) {
            throw new \Exception('إن هذا السبر قد انتهى', 400);
        }

        $studentUser = User::find($student_id);
        if (!$studentUser || $studentUser->privilege != 1) {
            throw new \Exception('المستخدم المحدد ليس طالباً', 400);
        }

        if ($authUser->privilege == 2) {
            $studentModel = student::where('user_id', $student_id)->first();
            if (!$studentModel || $studentModel->teacher_id != $authUser->id) {
                throw new \Exception('لا يمكنك إضافة طالب ليس في حلقتك', 403);
            }
        } elseif ($authUser->privilege == 3) {
            if ($studentUser->daora_id != $authUser->daora_id) {
                throw new \Exception('لا يمكنك إضافة طالب ليس في دورتك', 403);
            }
        }

        $user_test = user_test::where([
            ['test_id', '=', $test_id],
            ['user_id', '=', $student_id]
        ])->first();

        if ($user_test) {
            throw new \Exception('تم تسجيل الطالب مسبقاً', 409);
        }

        $user_test = user_test::create([
            'test_id'             => $test_id,
            'user_id'             => $student_id,
            'the_part_to_test_in' => 0,
            'rating'              => 0,
            'notes'               => 0,
        ]);

        $test->increment('number');
        return $user_test;
    }

    public function deleteAcceptedTestForStudent($test_id, $student_id, $authUser)
    {
        if ($authUser->privilege < 2) {
            throw new \Exception('لا تملك الصلاحية لحذف تسجيل طالب بالسابرو', 403);
        }

        $test = test::find($test_id);
        if (!$test) {
            throw new \Exception('رقم السبر غير صحيح', 404);
        }

        if ($test->End_time <= Carbon::now()) {
            throw new \Exception('السبر قد انتهى بالفعل', 400);
        }

        $studentUser = User::find($student_id);
        if (!$studentUser) {
            throw new \Exception('المستخدم المحدد غير موجود', 400);
        }

        if ($authUser->privilege == 2) {
            $studentModel = student::where('user_id', $student_id)->first();
            if (!$studentModel || $studentModel->teacher_id != $authUser->id) {
                throw new \Exception('لا يمكنك حذف طالب ليس في حلقتك', 403);
            }
        } elseif ($authUser->privilege == 3) {
            if ($studentUser->daora_id != $authUser->daora_id) {
                throw new \Exception('لا يمكنك حذف طالب ليس في دورتك', 403);
            }
        }

        $deleted = user_test::where([
            ['test_id', '=', $test_id],
            ['user_id', '=', $student_id],
            ['the_part_to_test_in', '=', 0] // 0 means not tested yet probably, just registered
        ])->delete();

        if ($deleted) {
            $test->decrement('number');
        }

        return $deleted;
    }

    public function deleteTest($test_id, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('لا تمتلك تصريح حذف السبر', 403);
        }

        DB::beginTransaction();
        try {
            DB::table('user_tests')->where('test_id', $test_id)->delete();
            $delete = test::where("id", $test_id)->delete();

            if (!$delete) {
                throw new \Exception('لم يتم العثور على السبر', 404);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function showTestAccepters($test_id)
    {
        $tests = user_test::where('test_id', $test_id)->get();
        foreach ($tests as $test_item) {
            $test_item->user_name = User::find($test_item->user_id)->name;
            $test_item->test_date = test::find($test_item->test_id)->End_time;
        }
        return $tests;
    }

    public function updateTestAccepterData($test_id, $user_id, $data, $authUser)
    {
        if ($authUser->privilege <= 1) {
            throw new \Exception('لا تملك صلاحية لتغيير نتائج الطالب في السبر ', 403);
        }

        $user_test = user_test::where([['user_id', '=', $user_id], ['test_id', '=', $test_id]])->first();
        if (!$user_test) {
            throw new \Exception('هذا المستخدم لم يسجل في هذا السبر ', 404);
        }

        $updated = user_test::where([['user_id', '=', $user_id], ['test_id', '=', $test_id]])
            ->update([
                'the_part_to_test_in' => $data['the_part_to_test_in'],
                'rating' => $data['rating'],
                'notes' => $data['notes'],
            ]);

        return $updated;
    }

    public function showSuccessStudentsInTest($test_id)
    {
        $success_users = user_test::where([['test_id', '=', $test_id], ['rating', '>=', 80]])->get();
        $fail_users = user_test::where([['test_id', '=', $test_id], ['rating', '<', 80]])->get();
        
        foreach ($success_users as $success_user) {
            $success_user->user_name = User::find($success_user->user_id)->name;
        }
        foreach ($fail_users as $fail_user) {
            $fail_user->user_name = User::find($fail_user->user_id)->name;
        }

        return [
            'success_users' => $success_users,
            'fail_users' => $fail_users
        ];
    }

    public function makeAukafTestForSuccessStudents($test_id, $data, $authUser)
    {
        if ($authUser->privilege != 3) {
            throw new \Exception('لا تملك صلاحية لتحويل الطلاب الناجحين الى سبر الاوقاف', 403);
        }

        $old_test = test::findOrFail($test_id);

        if ($old_test->aukaf) {
            throw new \Exception('لا يمكن تحويل سبر أوقاف إلى أوقاف مرة أخرى', 400);
        }

        $exists = test::where('End_time', $data['End_time'])
            ->where('daora_id', $old_test->daora_id)
            ->first();

        if ($exists) {
            throw new \Exception('يوجد سبر بنفس التاريخ والوقت لهذه الدورة، يرجى تغييره', 409); // Conflict changed to 409
        }

        $success_users = user_test::where('test_id', $test_id)
            ->where('rating', '>=', 80)
            ->get();

        $aukaf_test = test::create([
            'End_time'  => $data['End_time'],
            'notes'     => $data['notes'] . " (من السبر رقم $test_id)",
            'aukaf'     => true,
            'daora_id'  => $old_test->daora_id,
            'number'    => $success_users->count(),
        ]);

        foreach ($success_users as $success_user) {
            user_test::create([
                'user_id'             => $success_user->user_id,
                'test_id'             => $aukaf_test->id,
                'the_part_to_test_in' => $success_user->the_part_to_test_in,
                'rating'              => 0,
                'notes'               => "",
            ]);
        }

        $users = User::where('daora_id', $old_test->daora_id)->get();
        Notification::send($users, new test_added($aukaf_test));

        $aukaf_users = user_test::where('test_id', $aukaf_test->id)->get();

        return [
            'aukaf_test'  => $aukaf_test,
            'aukaf_users' => $aukaf_users
        ];
    }

    public function updateAukafTestsAfterTest($test_id, $user_id, $data)
    {
        $test = test::find($test_id);
        if (!$test) {
            throw new \Exception('تعريف الاختبار الرئيسي غير موجود', 404);
        }

        $aukaf_test = user_test::where([
            ['user_id', '=', $user_id],
            ['test_id', '=', $test_id]
        ])->first();

        if (!$aukaf_test) {
            throw new \Exception('سجل اختبار الطالب هذا غير موجود', 404);
        }

        $aukaf_test->update([
            'rating' => $data['rating'],
            'notes' => $data['notes'],
            'the_part_to_test_in' => json_encode(array_values($data['the_part_to_test_in'])),
        ]);

        if ($data['rating'] >= 80 && $test->aukaf) {
            $student = student::where('user_id', $user_id)->first();
            if ($student) {
                $student_ended_quraan_in_aukaf = json_decode($student->ended_quraan_in_aukaf, true) ?? [];
                $parts_in_aukaf_test = $data['the_part_to_test_in'];
                $merged_parts = array_unique(array_merge($student_ended_quraan_in_aukaf, $parts_in_aukaf_test));

                $student->update([
                    'ended_quraan_in_aukaf' => json_encode(array_values($merged_parts)),
                ]);
            }
        }

        return true;
    }
}
