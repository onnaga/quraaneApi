<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;   // <-- إضافة مهمة

use App\Models\latest;
use App\Models\point;
use App\Models\report;
use App\Models\student;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function add_latest_quraan($data, $user_id, $teacher_id)
    {
        $report = report::firstOrCreate(
            ['user_id' => $user_id],
            ['teacher_id' => $teacher_id]
        );

        // تهيئة بنية التقرير الجديدة إذا لم تكن موجودة أو كانت بالشكل القديم
        $report_quraan_data = $report->ended_quraan_this_course
            ? json_decode($report->ended_quraan_this_course)
            : null;


        if (is_object($report_quraan_data) && isset($report_quraan_data->ghaiban)) {
            $report_quraan = $report_quraan_data; // البيانات بالفعل بالشكل الجديد
        } else {
            // تهيئة البنية الجديدة الفارغة
            $report_quraan = (object) [
                'ghaiban' => [],
                'nazaran' => []
            ];
        }

        foreach ($data as $ended_sora) {
            // التأكد من وجود النوع، وافتراض 'ghaiban' إذا لم يرسل لضمان التوافقية
            $type = $ended_sora->type ?? 'ghaiban';

            // التأكد من أن النوع المرسل صحيح
            if (!in_array($type, ['ghaiban', 'nazaran'])) {
                continue; // تجاهل الإدخال إذا كان النوع غير معروف
            }

            $skip = false;

            // البحث في القائمة الصحيحة حسب النوع
            foreach ($report_quraan->{$type} as $sora) {
                if ($ended_sora->num == $sora->num) {
                    $skip = true;

                    if ($ended_sora->mark >= 80) {
                        $sora->success_repetitions = ($sora->success_repetitions ?? 0) + 1;
                        if ($ended_sora->to > $sora->to) {
                            $sora->to    = $ended_sora->to;
                            $sora->mark  = ($sora->mark + $ended_sora->mark) / 2;
                            $sora->point = $sora->point + $ended_sora->point;

                            $points = point::firstOrCreate(['user_id' => $user_id]);
                            $points->update([
                                'q_points' => $points->q_points + $ended_sora->point
                            ]);
                        }
                    } else {
                        $sora->failed_repetitions = ($sora->failed_repetitions ?? 0) + 1;
                    }
                }
            }

            if (!$skip) {
                $ended_sora->success_repetitions = $ended_sora->mark >= 80 ? 1 : 0;
                $ended_sora->failed_repetitions  = $ended_sora->mark < 80 ? 1 : 0;
                $ended_sora->type = $type; // التأكد من إضافة النوع إلى الكائن قبل الحفظ

                if ($ended_sora->mark >= 80) {
                    $points = point::firstOrCreate(['user_id' => $user_id]);
                    $points->update([
                        'q_points' => $points->q_points + $ended_sora->point
                    ]);
                    student::where('user_id', $user_id)->update([
                        'point_id' => $points->id
                    ]);
                }

                // الإضافة إلى القائمة الصحيحة حسب النوع
                $report_quraan->{$type}[] = $ended_sora;
            }
        }

        report::updateOrCreate(
            ['user_id' => $user_id, 'teacher_id' => $teacher_id],
            ['ended_quraan_this_course' => json_encode($report_quraan)]
        );

        return true; // يمكن إرجاع قيمة بسيطة
    }


    // في ReportController.php




    public function remove_latest_quraan($sora_data, $item_was_successful, $points_to_remove, $user_id, $mark, $sora_index, $type, $report_quraan, $report, $quraan_item)
    {
        try {

            // 3. إذا وجدت السورة، قم بعكس العمليات
            if ($sora_data) {


                // 3a. عكس عدد التكرارات
                if ($item_was_successful) {
                    $sora_data->success_repetitions = ($sora_data->success_repetitions ?? 1) - 1;
                } else {
                    $sora_data->failed_repetitions = ($sora_data->failed_repetitions ?? 1) - 1;
                }

                // 3b. عكس النقاط من جدول 'point' (هذا يحدث دائماً عند النجاح)
                if ($item_was_successful && $points_to_remove > 0) {
                    // تأكد من أن اسم الموديل صحيح (point أو Point)
                    $points = point::where('user_id', $user_id)->first();
                    if ($points) {
                        $new_points = $points->q_points - $points_to_remove;
                        $points->update([
                            'q_points' => $new_points < 0 ? 0 : $new_points
                        ]);
                    }
                }

                // 3c. عكس النقاط والعلامات من داخل السورة في الـ JSON
                if ($item_was_successful) {

                    // عكس النقاط
                    $sora_data->point = ($sora_data->point ?? 0) - $points_to_remove;
                    if ($sora_data->point < 0) $sora_data->point = 0;

                    // عكس المتوسط الحسابي للعلامة
                    if ($sora_data->success_repetitions > 0) {
                        $sora_data->mark = ($sora_data->mark * 2) - $mark;
                    } else {
                        // إذا كانت هذه آخر مراجعة ناجحة، أعد العلامة إلى 0
                        $sora_data->mark = 0;
                    }
                }

                // 4. التحقق مما إذا كان يجب حذف السورة بالكامل من التقرير
                if ($sora_data->success_repetitions <= 0 && $sora_data->failed_repetitions <= 0) {
                    // إذا لم يتبق أي تكرارات (ناجحة أو فاشلة)، احذف السورة
                    unset($report_quraan->{$type}[$sora_index]);
                    // إعادة فهرسة المصفوفة
                    $report_quraan->{$type} = array_values($report_quraan->{$type});
                } else {
                    // إذا كان لا يزال هناك تكرارات، قم بتحديث بيانات السورة
                    $report_quraan->{$type}[$sora_index] = $sora_data;
                }

                // 5. حفظ التقرير المحدث
                $report->update([
                    'ended_quraan_this_course' => json_encode($report_quraan)
                ]);
            }
        } catch (\Throwable $th) {
            // تسجيل الخطأ بدلاً من إيقاف العملية
            Log::error('خطأ في remove_latest_quraan: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'item' => $quraan_item,
                'trace' => $th->getTraceAsString()
            ]);
        }
    }


    // ==========================================================
    //  عكس إحصائيات الحديث
    // ==========================================================
    public function remove_latest_hadith(
        $hadith_data,
        $item_was_successful,
        $points_to_remove,
        $user_id,
        $mark,
        $item_index,
        $report_hadith_list,
        $report,
        $hadith_item
    ) {
        try {
            // 3a. عكس عدد التكرارات
            if ($item_was_successful) {
                $hadith_data->success_repetitions = ($hadith_data->success_repetitions ?? 1) - 1;
            } else {
                $hadith_data->failed_repetitions = ($hadith_data->failed_repetitions ?? 1) - 1;
            }

            // 3b. عكس النقاط من جدول 'point' 
            // ❗️ (افتراض: اسم الحقل 'h_points' لنقاط الحديث)
            if ($item_was_successful && $points_to_remove > 0) {
                $points = point::where('user_id', $user_id)->first();
                if ($points) {
                    $new_points = $points->h_points - $points_to_remove; // ❗️ h_points
                    $points->update([
                        'h_points' => $new_points < 0 ? 0 : $new_points // ❗️ h_points
                    ]);
                }
            }

            // 3c. عكس النقاط والعلامات من داخل الحديث في الـ JSON
            if ($item_was_successful) {
                // عكس النقاط
                $hadith_data->point = ($hadith_data->point ?? 0) - $points_to_remove;
                if ($hadith_data->point < 0) $hadith_data->point = 0;

                // عكس المتوسط الحسابي للعلامة (نفس منطق القرآن)
                if ($hadith_data->success_repetitions > 0) {
                    $hadith_data->mark = ($hadith_data->mark * 2) - $mark;
                } else {
                    $hadith_data->mark = 0;
                }
            }

            // 4. التحقق مما إذا كان يجب حذف الحديث بالكامل من التقرير
            if ($hadith_data->success_repetitions <= 0 && $hadith_data->failed_repetitions <= 0) {
                unset($report_hadith_list[$item_index]);
                // إعادة فهرسة المصفوفة
                $report_hadith_list = array_values($report_hadith_list);
            } else {
                // تحديث بيانات الحديث
                $report_hadith_list[$item_index] = $hadith_data;
            }

            // 5. حفظ التقرير المحدث

            $report->update([
                'ended_hadith_this_course' => json_encode($report_hadith_list)
            ]);
        } catch (\Throwable $th) {
            Log::error('خطأ في remove_latest_hadith: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'item' => $hadith_item,
                'trace' => $th->getTraceAsString()
            ]);
        }
    }

// ==========================================================
    //  عكس إحصائيات النشاط (النسخة المصححة)
    // ==========================================================
    public function remove_latest_activity(
        $activity_data,
        $item_was_successful,
        $points_to_remove,
        $user_id,
        $mark,
        $item_index,
        $report_activity_list,
        $report,
        $activity_item
    ) {
        try {
            // 3a. عكس عدد التكرارات (الأسماء صحيحة)
            if ($item_was_successful) {
                $activity_data->number_of_repetitions = ($activity_data->number_of_repetitions ?? 1) - 1;
            } else {
                $activity_data->failures = ($activity_data->failures ?? 1) - 1;
            }

            // 3b. عكس النقاط من جدول 'point' (كان يعمل)
            if ($item_was_successful && $points_to_remove > 0) {
                $points = point::where('user_id', $user_id)->first();
                if ($points) {
                    $new_points = $points->a_points - $points_to_remove;
                    $points->update([
                        'a_points' => $new_points < 0 ? 0 : $new_points
                    ]);
                }
            }

            // 3c. عكس النقاط والعلامات (كان صحيحاً)
            if ($item_was_successful) {
                $activity_data->point = ($activity_data->point ?? 0) - $points_to_remove;
                if ($activity_data->point < 0) $activity_data->point = 0;

                if ($activity_data->number_of_repetitions > 0) {
                    $activity_data->mark = ($activity_data->mark * 2) - $mark;
                } else {
                    $activity_data->mark = 0;
                }
            }

            // 4. التحقق (كان صحيحاً)
            if ($activity_data->number_of_repetitions <= 0 && $activity_data->failures <= 0) {
                unset($report_activity_list[$item_index]);
                $report_activity_list = array_values($report_activity_list);
            } else {
                $report_activity_list[$item_index] = $activity_data;
            }

            // 5. حفظ التقرير المحدث
            $report->update([
                'activities_this_course' => json_encode($report_activity_list)
            ]);
            
            return null; // ✅ إرجاع null عند النجاح

        } catch (\Throwable $th) {
            // ✅ تصحيح: تسجيل الخطأ أولاً، ثم إرجاعه
            Log::error('خطأ في remove_latest_activity: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'item' => $activity_item,
                'trace' => $th->getTraceAsString()
            ]);
            return ["error" => $th->getMessage()]; // إرجاع الخطأ
        }
    }


    // ==========================================================
    //  عكس إحصائيات الملاحظة (النسخة المنقحة)
    // ==========================================================
    public function remove_latest_note($note_item, $user_id)
    {
        try {
            // 1. $note_item الآن ستكون صحيحة: ['note' => '88', 'lost_point' => '100']
            $item_to_delete = (object)$note_item;
            $points_to_restore = $item_to_delete->lost_point ?? 0;

            // 2. البحث عن تقرير المستخدم
            $report = Report::where('user_id', $user_id)->first();

            if (!$report || !$report->notes) {
                return;
            }

            // 3. فك ترميز قائمة الملاحظات
            $notes_list = json_decode($report->notes, true); // true للحصول على مصفوفة
            
            // ✅ التأكد من أن $notes_list مصفوفة
            if (!is_array($notes_list)) {
                Log::warning("بنية تقرير الملاحظات غير صالحة للمستخدم: $user_id");
                return;
            }

            $item_found_and_removed = false;

            // 4. البحث عن الملاحظة المطابقة وحذفها
            foreach ($notes_list as $index => $note) {
                
                // ✅ التأكد من أن $note مصفوفة قبل الوصول لمفاتيحها
                if (!is_array($note) || !isset($note['note'], $note['lost_point'])) {
                    continue; // تخطي العنصر الفاسد
                }

                // ✅ المقارنة ستنجح الآن
                // "88" == "88"
                // "100" == "100" (استخدام == للمقارنة المتساهلة أفضل هنا لأنواع البيانات)
                if ($note['note'] == $item_to_delete->note && $note['lost_point'] == $points_to_restore) {
                    unset($notes_list[$index]);
                    $item_found_and_removed = true;
                    break;
                }
            }

            // 5. إذا تم حذف عنصر، قم بتحديث التقرير والنقاط
            if ($item_found_and_removed) {
                // 5a. حفظ التقرير
                $report->update([
                    'notes' => json_encode(array_values($notes_list))
                ]);

                // 5b. إعادة النقاط المخصومة
                if ($points_to_restore > 0) {
                    $points = point::where('user_id', $user_id)->first();
                    if ($points) {
                        // نفترض أن حقل النقاط هو 'l_points'
                        $new_points = $points->l_points - $points_to_restore;
                        $points->update([
                            'l_points' => $new_points
                        ]);
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error('خطأ في remove_latest_note: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'item' => $note_item,
                'trace' => $th->getTraceAsString()
            ]);
        }
    }

    public function add_latest_hadith($data, $user_id, $teacher_id)
    {
        $report = report::firstOrCreate(['user_id' => $user_id], ['teacher_id' => $teacher_id]);

        $report_hadith = $report->ended_hadith_this_course
            ? json_decode($report->ended_hadith_this_course)
            : [];

        foreach ($data as $ended_hadith) {
            $skip = false;

            foreach ($report_hadith as $hadith) {
                if ($ended_hadith->num == $hadith->num) {
                    $skip = true;

                    if ($ended_hadith->mark >= 80) {
                        $hadith->success_repetitions = ($hadith->success_repetitions ?? 0) + 1;



                        $hadith->mark = ($hadith->mark + $ended_hadith->mark) / 2;
                        $hadith->point += $ended_hadith->point;

                        $points = point::firstOrCreate(['user_id' => $user_id]);
                        $points->update([
                            'h_points' => $points->h_points + $ended_hadith->point
                        ]);
                    } else {
                        // ❌ فقط زيادة الفشل بدون تعديل النطاق
                        $hadith->failed_repetitions = ($hadith->failed_repetitions ?? 0) + 1;
                    }
                }
            }

            if (!$skip) {
                $ended_hadith->success_repetitions = $ended_hadith->mark >= 80 ? 1 : 0;
                $ended_hadith->failed_repetitions  = $ended_hadith->mark < 80 ? 1 : 0;

                if ($ended_hadith->mark >= 80) {
                    $points = point::firstOrCreate(['user_id' => $user_id]);
                    $points->update([
                        'h_points' => $points->h_points + $ended_hadith->point
                    ]);

                    student::where('user_id', $user_id)->update([
                        'point_id' => $points->id
                    ]);
                }

                $report_hadith[] = $ended_hadith;
            }
        }

        report::updateOrCreate(
            ['user_id' => $user_id, 'teacher_id' => $teacher_id],
            ['ended_hadith_this_course' => json_encode($report_hadith)]
        );

        return true;
    }





    public function add_latest_activity($data, $user_id, $teacher_id)
    {
        $report = report::firstOrCreate(
            ['user_id' => $user_id],
            ['teacher_id' => $teacher_id]
        );

        // decode إلى array بدل object
        $report_activity = $report->activities_this_course
            ? json_decode($report->activities_this_course, true)
            : [];

        foreach ($data as $new_activity) {
            $new_activity = (array) $new_activity; // تأكدنا إنه array
            $found = false;

            foreach ($report_activity as &$activity) {
                if ($new_activity['name'] === $activity['name']) {
                    $found = true;

                    if ($new_activity['mark'] >= 80) {
                        // نجاح
                        $activity['number_of_repetitions'] =
                            ($activity['number_of_repetitions'] ?? 0) + 1;

                        // تحديث التقييم + النقاط
                        $activity['mark'] =
                            ($activity['mark'] + $new_activity['mark']) / 2;
                        $activity['point'] =
                            ($activity['point'] ?? 0) + $new_activity['point'];

                        // نقاط الطالب
                        $points = point::firstOrCreate(['user_id' => $user_id]);
                        $points->update([
                            'a_points' => $points->a_points + $new_activity['point']
                        ]);
                    } else {
                        // فشل
                        $activity['failures'] = ($activity['failures'] ?? 0) + 1;
                    }
                    break;
                }
            }

            if (!$found) {
                // نشاط جديد
                if ($new_activity['mark'] >= 80) {
                    $new_activity['number_of_repetitions'] = 1;
                    $new_activity['failures'] = 0;

                    $points = point::firstOrCreate(['user_id' => $user_id]);
                    $points->update([
                        'a_points' => $points->a_points + $new_activity['point']
                    ]);

                    student::where('user_id', $user_id)->update([
                        'point_id' => $points->id
                    ]);
                } else {
                    $new_activity['number_of_repetitions'] = 0;
                    $new_activity['failures'] = 1;
                    $new_activity['point'] = $new_activity['point'] ?? 0;
                }

                $report_activity[] = $new_activity;
            }
        }

        // تحديث التقرير
        $report->update([
            'activities_this_course' => json_encode($report_activity)
        ]);
        return true;
    }


    public function  add_latest_note($oneNote, $lost_point, $user_id, $teacher_id)
    {

        $report = report::firstOrCreate(['user_id' => $user_id], ['teacher_id' => $teacher_id]);
        $notes = [];
        if (json_decode($report->notes) != null)
            $notes = json_decode($report->notes);
        array_push($notes, ['note' => $oneNote, 'lost_point' => $lost_point]);

        report::where([['user_id', $user_id], ['teacher_id', $teacher_id]])->update([
            'notes' => json_encode($notes),
        ]);
        return true;
    }



    public function show_reports()
    {
        try {
            $requestingUser = Auth::user();
            $daora_id = $requestingUser->daora_id;

            if (!$daora_id) {
                return response()->json(['message' => 'المستخدم الحالي غير مسجل في أي دورة.'], 404);
            }

            // 🚀 استعلام واحد قوي لجلب كل شيء
            $reports = DB::table('reports')
                // --- الربط (Joining) ---

                // 1. اربط جدول 'reports' مع جدول 'users' لجلب اسم الطالب
                // نستخدم alias 'student_user' لتجنب تضارب الأسماء
                ->join('users as student_user', 'reports.user_id', '=', 'student_user.id')

                // 2. اربط جدول 'reports' مع 'users' مرة أخرى لجلب اسم المعلم
                // نستخدم alias 'teacher_user'
                ->join('users as teacher_user', 'reports.teacher_id', '=', 'teacher_user.id')

                // --- الفلترة (Filtering) ---

                // 3. فلترة النتائج لعرض تقارير طلاب نفس الدورة فقط
                ->where('student_user.daora_id', $daora_id)

                // --- تحديد الأعمدة (Selecting) ---

                // 4. حدد الأعمدة التي تريدها بالضبط لتجنب التضارب وتحسين الأداء
                ->select(
                    'reports.*', // جلب جميع أعمدة جدول التقارير
                    'student_user.name as user_name', // جلب اسم الطالب وتسميته user_name
                    'teacher_user.name as teacher_name' // جلب اسم المعلم وتسميته teacher_name
                )

                // --- التنفيذ ---
                ->get();

            // 💡 لا حاجة لحلقة foreach لجلب الأسماء بعد الآن!

            // 5. إذا كنت لا تزال بحاجة لفك تشفير حقول JSON، يمكنك إبقاء هذه الحلقة
            // (لأفضل أداء، يُفضل استخدام $casts في المودل كما ذكرنا سابقًا)
            foreach ($reports as $report) {
                $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
                $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course);
                $report->activities_this_course = json_decode($report->activities_this_course);
                $report->notes = json_decode($report->notes);
            }

            return response()->json($reports);
        } catch (\Throwable $th) {
            // Log::error($th); // جيد لتسجيل الأخطاء
            return response()->json(['error' => 'حدث خطأ غير متوقع في الخادم'], 500);
        }
    }

    // public function  show_user_reports(Request $request)
    // {
    //     if (Auth::user()->privilege > 1) {
    //         if (!$request->user_id)
    //             return response()->json('user_id required');
    //         $report = report::where('user_id', $request->user_id)->first(['ended_quraan_this_course', 'ended_hadith_this_course', 'activitis_this_course', 'notes']);

    //         $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course);
    //         $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
    //         $report->activitis_this_course = json_decode($report->activitis_this_course);
    //         $report->notes = json_decode($report->notes);
    //         return response()->json($report);
    //     } else {
    //         $report = report::where('user_id', Auth::user()->id)->first(['ended_quraan_this_course', 'ended_hadith_this_course', '	activitis_this_course', 'notes']);
    //         $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course);
    //         $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
    //         $report->activitis_this_course = json_decode($report->activitis_this_course);
    //         $report->notes = json_decode($report->notes);
    //         return response()->json($report);
    //     }
    // }
}
