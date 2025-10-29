<?php

namespace App\Http\Controllers;

use App\Models\student;
use App\Models\test;
use App\Models\User;
use App\Models\user_test;
use App\Notifications\test_added;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class TestController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api');
    }

// public function test_note($test_id)
// {
//     $test = test::findOrFail($test_id);

//     $users = User::whereHas('user_tests', function ($q) use ($test) {
//         $q->where('daora_id', $test->daora_id);
//     })->where('id', '!=', 1)->get();

//     Notification::send($users, new test_added($test));

//     return response()->json(['aaa']);
// }
public function add_new_test(Request $request)
{
    $user = Auth::user();

    if ($user->privilege != 3) {
        return response()->json(
            ['message' => 'لا تملك الصلاحية لإضافة سبر'],
            403 // Forbidden
        );
    }

    try {
        $rules = [
            'End_time' => 'required|date',
            'notes'    => 'required|string',
            'aukaf'    => 'required|boolean',
            'daora_id' => 'required|integer|exists:daoras,id',
        ];
        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return response()->json([
                'status'   => 'error',
                'message' => 'يوجد خطأ بالبيانات المضافة', // رسالة أوضح
                'errors'   => $validated->errors()
            ], 422); // 422 Unprocessable Entity هو الرمز الأنسب لأخطاء التحقق
        }

        $exists = Test::where('End_time', $request->End_time)
            ->where('daora_id', $request->daora_id)
            ->first();

        if ($exists) {
            // ✅ استخدام رمز 409 Conflict للبيانات المكررة
            return response()->json([
                'message' => 'يوجد سبر بنفس التاريخ والوقت لهذه الدورة، يرجى تغييره'
            ], 409); // 409 Conflict
        }

        // ✅ أنشئ جديد
        $created_test = Test::create([
            'End_time' => $request->End_time,
            'notes'    => $request->notes,
            'aukaf'    => (bool)$request->aukaf,
            'daora_id' => $request->daora_id,
            'number'   => 0,
        ]);

        // ✅ جلب طلاب نفس الدورة فقط لإرسال الإشعار
        $users = User::where('daora_id', $created_test->daora_id)->get();

        // قد يفشل إرسال الإشعارات، لكن هذا لا يجب أن يمنع استجابة النجاح
        // يفضل وضعها في job في الخلفية لتجنب تأخير الاستجابة
        try {
            Notification::send($users, new test_added($created_test));
        } catch (\Exception $e) {
            print("$e");
            // يمكنك تسجيل الخطأ هنا، لكن استمر في إرجاع استجابة النجاح
            Log::error('Failed to send test notification: ' . $e->getMessage());
        }

        // إرجاع استجابة نجاح مع رمز 201 Created
        return response()->json(['created_test' => $created_test], 201);

    } catch (\Throwable $th) {
        // إرجاع خطأ 500 في حالة حدوث أي مشكلة غير متوقعة
        return response()->json(['message' => 'حدث خطأ غير متوقع في الخادم', 'error' => $th->getMessage()], 500);
    }
}

    public function show_tests(Request $request)
    {
        $request->validate([
            'daora_id' => 'required|integer|exists:daoras,id',
        ]);

        $tests = test::where('daora_id', $request->daora_id)
            ->orderBy('End_time', 'DESC')
            ->get();

        $user = Auth::user();

        // إذا مو بريفلج = 1 → ما نرجع امتحاناته الخاصة
        $registeredTests = [];
        if ($user->privilege == 1) {
            $registeredTests = DB::table('user_tests')
                ->where('user_id', $user->id)
                ->pluck('test_id')
                ->toArray();
        }

        return response()->json([
            'tests' => $tests,
            'registered_tests' => $registeredTests,
        ]);
    }


    public function accept_test($test_id)
    {
        if (Auth::user()->privilege == 1) {
            $test = test::find($test_id);
            if ($test) {
                if ($test->End_time > Carbon::now()) {
                    $user_id = Auth::user()->id;
                    $user_test = user_test::where([
                        ['test_id', '=', $test_id],
                        ['user_id', '=', $user_id]
                    ])->first();

                    if (!$user_test) {
                        $user_test = user_test::create([
                            'test_id'             => $test_id,
                            'user_id'             => $user_id,
                            'the_part_to_test_in' => 0,
                            'rating'              => 0,
                            'notes'               => 0,
                        ]);

                        // ✅ زيادة العدد
                        $test->increment('number');
                        return response()->json($user_test);
                    } else {
                        return response()->json('تم قبوله سابقا ');
                    }
                } else {
                    return response()->json('إن هذا السبر قد انتهى حاول التسجيل في سبر غير منتهي');
                }
            } else {
                return response()->json([
                    'error' => 'الرقم الخاص بالسبر خاطئ'
                ]);
            }
        } else {
            return response()->json([
                'error' => 'لا يمكن للأساتذة التسجيل على سبر أنشئ حساب طالب وحاول مجددا'
            ]);
        }
    }


    public function delete_accepted_test($test_id)
    {
        if (Auth::user()->privilege == 1) {
            $test = test::find($test_id);
            if ($test) {
                if ($test->End_time > Carbon::now()) {
                    $user_id = Auth::user()->id;
                    $deleted = user_test::where([
                        ['test_id', '=', $test_id],
                        ['user_id', '=', $user_id],
                        ['the_part_to_test_in', '=', 0]
                    ])->delete();

                    if ($deleted) {
                        // ✅ إنقاص العدد
                        $test->decrement('number');
                    }

                    return response()->json([
                        'deleted' => $deleted
                    ]);
                } else {
                    return response()->json('السبر قد انتهى بالفعل');
                }
            } else {
                return response()->json([
                    'error' => 'رقم السبر غير صحيح'
                ]);
            }
        } else {
            return response()->json([
                'error' => 'لا يمكن للأساتذة التسجيل على سبر'
            ]);
        }
    }


    public function delete_test($test_id)
    {
        if (Auth::user()->privilege == 3) {
            try {
                // أول شي نحذف كل العلاقات من جدول user_tests
                DB::table('user_tests')->where('test_id', $test_id)->delete();

                // بعدين نحذف السبر نفسه
                $delete = Test::where("id", $test_id)->delete();

                if ($delete) {
                    return response()->json([
                        'message' => 'تم حذف السبر وكل ما يتعلق به بنجاح'
                    ], 200);
                } else {
                    return response()->json([
                        'error' => 'لم يتم العثور على السبر'
                    ], 404);
                }
            } catch (\Throwable $th) {
                return response()->json([
                    'error' => 'حصل خطأ أثناء الحذف',
                    'details' => $th->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'error' => 'لا تمتلك تصريح حذف السبر'
            ], 403);
        }
    }

    public function show_test_accepters($test_id)
    {
        try {
            $tests = user_test::where('test_id', $test_id)->get();
            foreach ($tests as $test) {
                $test->user_name = User::find($test->user_id)->name;
                $test->test_date = test::find($test->test_id)->End_time;
            }

            return response()->json(
                $tests
            );
        } catch (\Throwable $th) {
            return response()->json([
                $th->getMessage(),
                'trace' => $th->getTrace()
            ]);
        }
    }


    public function update_test_accepter_data(Request $request, $test_id, $user_id)
    {

        if (Auth::user()->privilege > 1) {
            try {
                $rules = [
                    'the_part_to_test_in' => 'required',
                    'rating' => 'required',
                    'notes' => 'required',
                ];
                $validated = Validator::make($request->all(), $rules);

                if ($validated->fails()) {
                    return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة ', 'errors' => $validated->errors()], 403);
                }

                $user_test = user_test::where([['user_id', '=', $user_id], ['test_id', '=', $test_id]])->first();
                if ($user_test) {
                    $updated =
                        user_test::where([['user_id', '=', $user_id], ['test_id', '=', $test_id]])
                        ->update([
                            'the_part_to_test_in' => $request->the_part_to_test_in,
                            'rating' => $request->rating,
                            'notes' => $request->notes,
                        ]);


                    return response()->json(
                        ['updated' => $updated]
                    );
                } else {
                    return response()->json(
                        ['error' => 'هذا المستخدم لم يسجل في هذا السبر ']

                    );
                }
            } catch (\Throwable $th) {
                return response()->json(
                    $th->getMessage()
                );
            }
        } else {
            return response()->json([
                'message' => 'لا تملك صلاحية لتغيير نتائج الطالب في السبر '
            ]);
        }
    }



    public function show_success_students_in_test($test_id)
    {
        try {
            $success_users = user_test::where([['test_id', '=', $test_id], ['rating', '>=', 80]])->get();
            $fail_users = user_test::where([['test_id', '=', $test_id], ['rating', '<', 80]])->get();
            foreach ($success_users as $success_user) {
                $success_user->user_name = User::find($success_user->user_id)->name;
            }
            foreach ($fail_users as $fail_user) {
                $fail_user->user_name = User::find($fail_user->user_id)->name;
            }
            return response()->json(
                [
                    'success_users' => $success_users,
                    'fail_users' => $fail_users
                ]
            );
        } catch (\Throwable $th) {
            return response()->json(
                ['message' => $th->getMessage(), $th->getTrace()]

            );
        }
    }
public function make_aukaf_test_for_success_students(Request $request, $test_id)
{
    if (Auth::user()->privilege != 3) {
        return response()->json(
            ['message' => 'لا تملك صلاحية لتحويل الطلاب الناجحين الى سبر الاوقاف'],
            403
        );
    }

    try {
        $rules = [
            'End_time' => 'required|date',
            'notes'    => 'required|string',
        ];
        $validated = Validator::make($request->all(), $rules);

        if ($validated->fails()) {
            return response()->json([
                'status'   => 'error',
                'messages' => 'البيانات المدخلة غير صحيحة',
                'errors'   => $validated->errors()
            ], 403);
        }

        // ✅ جلب السبر الترشيحي
        $old_test = test::findOrFail($test_id);

        if ($old_test->aukaf) {
            return response()->json([
                'message' => 'لا يمكن تحويل سبر أوقاف إلى أوقاف مرة أخرى'
            ], 400);
        }

        $exists = test::where('End_time', $request->End_time)
            ->where('daora_id', $old_test->daora_id)
            ->first();

        if ($exists) {
            return response()->json([
                'message' => 'يوجد سبر بنفس التاريخ والوقت لهذه الدورة، يرجى تغييره'
            ], 401);
        }

        // ✅ جلب الطلاب الناجحين فقط
        $success_users = user_test::where('test_id', $test_id)
            ->where('rating', '>=', 80)
            ->get();

        // ✅ أنشئ سبر أوقاف جديد مرتبط بنفس الدورة وعدد الطلاب = عدد الناجحين
        $aukaf_test = test::create([
            'End_time'  => $request->End_time,
            'notes'     => $request->notes . " (من السبر رقم $test_id)",
            'aukaf'     => true,
            'daora_id'  => $old_test->daora_id,
            'number'    => $success_users->count(),
        ]);

        // ✅ نسخ الطلاب الناجحين
        foreach ($success_users as $success_user) {
            user_test::create([
                'user_id'             => $success_user->user_id,
                'test_id'             => $aukaf_test->id,
                'the_part_to_test_in' => $success_user->the_part_to_test_in,
                'rating'              => 0,
                'notes'               => "",
            ]);
        }
            
        // ✅ جلب طلاب نفس الدورة فقط لإرسال الإشعار
        $users = User::where('daora_id', $old_test->daora_id)->get();

        Notification::send($users, new test_added($aukaf_test));

        $aukaf_users = user_test::where('test_id', $aukaf_test->id)->get();

        return response()->json([
            'aukaf_test'  => $aukaf_test,
            'aukaf_users' => $aukaf_users
        ]);
    } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
    }
}

 public function update_aukaf_tests_after_the_test(Request $request, $test_id, $user_id)
    {
        try {
            // ✅ قوانين التحقق من صحة البيانات (تبقى كما هي)
            $validated = Validator::make($request->all(), [
                'rating' => 'required|numeric|min:0|max:100',
                'notes' => 'required|string',
                'the_part_to_test_in' => 'required|array',
            ]);

            if ($validated->fails()) {
                return response()->json(['errors' => $validated->errors()], 422); // 422 أفضل للـ validation
            }

            // ✅ الخطوة 2: جلب نموذج الاختبار نفسه للتحقق من نوعه
            $test = Test::find($test_id);
            if (!$test) {
                return response()->json(['messages' => 'تعريف الاختبار الرئيسي غير موجود'], 404);
            }

            // ✅ جلب سجل اختبار الطالب (يبقى كما هو)
            $aukaf_test = user_test::where([
                ['user_id', '=', $user_id],
                ['test_id', '=', $test_id]
            ])->first();

            if (!$aukaf_test) {
                return response()->json(['messages' => 'سجل اختبار الطالب هذا غير موجود'], 404);
            }

            // ✅ تحديث بيانات user_test (يبقى كما هو)
            $aukaf_test->update([
                'rating' => $request->rating,
                'notes' => $request->notes,
                'the_part_to_test_in' => json_encode(array_values($request->the_part_to_test_in)),
            ]);

            // ✅ الخطوة 3: التحقق من الشرطين معاً (العلامة ونوع الاختبار)
            if ($request->rating >= 80 && $test->aukaf) {
                $student = student::where('user_id', $user_id)->first();
                if ($student) {
                    $student_ended_quraan_in_aukaf = json_decode($student->ended_quraan_in_aukaf, true) ?? [];
                    $parts_in_aukaf_test = $request->the_part_to_test_in;
                    $merged_parts = array_unique(array_merge($student_ended_quraan_in_aukaf, $parts_in_aukaf_test));

                    $student->update([
                        'ended_quraan_in_aukaf' => json_encode(array_values($merged_parts)),
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'messages' => 'تم تعديل بيانات الاختبار بنجاح'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'messages' => 'حدث خطأ غير متوقع',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

