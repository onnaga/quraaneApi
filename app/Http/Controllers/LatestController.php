<?php

namespace App\Http\Controllers;

use App\Models\latest;
use App\Models\point;
use App\Models\report;
use App\Models\student;
use App\Models\User;
use App\Notifications\delete_latest_quraan_notification;
use Illuminate\Support\Facades\Auth;
use App\Notifications\latest_activities;
use App\Notifications\latest_hadith_with_homework;
use App\Notifications\latest_notes;
use App\Notifications\latest_quraan_with_homework;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LatestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function add_latest_quraan(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية اضافة التسميعات '], 403);
            }

            $student = student::where('user_id', $user_id)->first();
            if (!$student) {
                return response()->json(['message' => 'الطالب غير موجود'], 404);
            }

            if (!$student->teacher_id) {
                return response()->json(['message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاولة'], 403);
            }

            $data = json_decode($request->getContent());
            if (!is_array($data) || count($data) < 2) {
                return response()->json(['message' => 'صيغة البيانات المرسلة غير صحيحة'], 422);
            }

            // ✅ معالجة بيانات الواجبات بنفس طريقة معالجة التسميعات
            $quran_data = $data[0];
            $homework_data = $data[1];

            // دالة مساعدة لتصنيف البيانات إلى غيباً ونظراً
            $sortByType = function ($items) {
                $sorted = ['ghaiban' => [], 'nazaran' => []];
                foreach ($items as $item) {
                    $type = $item->type ?? 'ghaiban'; // افتراض غيباً إذا لم يرسل النوع
                    if ($type === 'nazaran') {
                        $sorted['nazaran'][] = $item;
                    } else {
                        $sorted['ghaiban'][] = $item;
                    }
                }
                return $sorted;
            };

            $sorted_quran = $sortByType($quran_data);
            $sorted_homework = $sortByType($homework_data);

            // حفظ في جدول latest
            if ($student->latest_id) {
                latest::where('id', $student->latest_id)->update([
                    'quran'      => json_encode($sorted_quran),
                    'q_homework' => json_encode($sorted_homework),
                ]);
            } else {
                $latest_created = latest::create([
                    'quran'      => json_encode($sorted_quran),
                    'q_homework' => json_encode($sorted_homework),
                ]);
                $student->update(['latest_id' => $latest_created->id]);
            }

            // تحديث التقرير (فقط للتسميعات، ليس للواجبات حسب الكود الأصلي)
            $report_controller = new ReportController;
            $report_controller->add_latest_quraan($quran_data, $student->user_id, $student->teacher_id);

            // إرسال إشعار
            $users = User::where('id', '!=', auth('api')->user()->id)->where('id', $student->user_id)->get();
            Notification::send($users, new latest_quraan_with_homework($data));

            return response()->json(['updated' => true], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage(), 'path'  => $th->getTrace()], 500);
        }
    }

    public function delete_latest_quraan(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية حذف التسميعات'], 403);
            }

            $student = student::where('user_id', $user_id)->first();
            if (!$student || !$student->latest_id) {
                return response()->json(['message' => 'الطالب أو سجل إنجازاته غير موجود'], 404);
            }

            // بيانات الإنجاز المُراد حذفه من الواجهة الأمامية
            $item_to_delete = json_decode($request->getContent());
            if (!$item_to_delete) {
                return response()->json(['message' => 'بيانات الحذف غير صحيحة'], 422);
            }

            $latest = latest::find($student->latest_id);
            $quran_data = json_decode($latest->quran, true);

            // تحقق من البنية
            if (!is_array($quran_data)) {
                return response()->json(['message' => 'بيانات القرآن غير صالحة في سجل الطالب'], 422);
            }

            $item_found = false;

            foreach ($quran_data as $type => &$items) {

                // تأكد أن $items مصفوفة
                if (!is_array($items)) continue;

                foreach ($items as $key => $item) {

                    // تأكد أن $item مصفوفة أيضاً
                    if (!is_array($item)) continue;

                    if (
                        isset($item['num'], $item['from'], $item['to'], $item['type']) &&
                        $item['num'] == $item_to_delete->num &&
                        $item['from'] == $item_to_delete->from &&
                        $item['to'] == $item_to_delete->to &&
                        $item['type'] == $item_to_delete->type
                    ) {

                        $Latest_controller = new LatestController;
                        $result  = $Latest_controller->if_latest_delete_from_report(
                            (object)$item,
                            $student->user_id,
                            $student->teacher_id
                        );
                        if (isset($result['error']) && $result['error']) {
                            return response()->json($result, 401);
                        }

                        unset($items[$key]);
                        $item_found = true;
                        break 2;
                    }
                }
            }


            if (!$item_found) {
                return response()->json(['message' => 'الإنجاز المطلوب حذفه غير موجود'], 404);
            }

            // إعادة ترتيب الفهارس وتحديث قاعدة البيانات
            $quran_data = array_map('array_values', $quran_data);
            $latest->update(['quran' => json_encode($quran_data)]);

            // إرسال إشعار بالحذف
            $user_to_notify = User::find($student->user_id);
            if ($user_to_notify) {
                Notification::send($user_to_notify, new delete_latest_quraan_notification($item_to_delete));
            }

            return response()->json(['deleted' => true, 'message' => 'تم حذف الإنجاز بنجاح'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error 44444' => $th->getMessage()], 500);
        }
    }



    public function if_latest_delete_from_report($quraan_item, $user_id, $teacher_id)
    {
        try {
            $report = report::where('user_id', $user_id)->first();

            // إذا لم يكن هناك تقرير أو بيانات قرآن، لا تفعل شيئاً
            if (!$report || !$report->ended_quraan_this_course) {
                return ['error' => 'لا يوجد تقرير لهذا الإنجاز'];
            }

            $report_quraan = json_decode($report->ended_quraan_this_course);

            // التأكد من أن بنية التقرير سليمة
            if (!is_object($report_quraan) || !isset($report_quraan->ghaiban) || !isset($report_quraan->nazaran)) {
                Log::warning("بنية تقرير القرآن غير صالحة للمستخدم: $user_id");
                return  ['error' => 'بنية تقرير القرآن غير صالحة للمستخدم'];
            }

            // 1. تحديد بيانات العنصر المحذوف
            $item_to_delete = (object)$quraan_item;
            $type = $item_to_delete->type ?? 'ghaiban';
            $mark = $item_to_delete->mark ?? 0;
            $points_to_remove = $item_to_delete->point ?? 0;
            $item_was_successful = $mark >= 80;

            // *** تعديل جديد: استخلاص from و to من العنصر المحذوف ***
            $deleted_from = $item_to_delete->from ?? null;
            $deleted_to = $item_to_delete->to ?? null;

            // التحقق من النوع
            if (!in_array($type, ['ghaiban', 'nazaran'])) {
                $type = 'ghaiban';
            }

            $sora_index = null;
            $sora_data = null;

            // 2. البحث عن السورة المطابقة في التقرير
            foreach ($report_quraan->{$type} as $index => $sora) {
                if ($sora->num == $item_to_delete->num) {
                    $sora_index = $index;
                    $sora_data = $sora;
                    break;
                }
            }
            if ($sora_data) {
                if (isset($sora_data->to) && $deleted_to !== null && $sora_data->to == $deleted_to) {
                    $sora_data->to = $deleted_from;
                    $report_controller = new ReportController;
                    $report_controller->remove_latest_quraan(
                        sora_data: $sora_data,
                        item_was_successful: $item_was_successful,
                        points_to_remove: $points_to_remove,
                        user_id: $user_id,
                        mark: $mark,
                        sora_index: $sora_index,
                        type: $type,
                        report_quraan: $report_quraan,
                        report: $report,
                        quraan_item: $quraan_item
                    );
                } else {
                    return ['error' => 'لا تستطيع حذف العنصر لأنه ليس آخر ما تم تسميعه من السورة'];
                }
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            return ['error 2222' => $th];
        }
    }


// ==========================================================
    //  حذف الحديث
    // ==========================================================
    public function delete_latest_hadith(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية الحذف'], 403);
            }

            $student = student::where('user_id', $user_id)->first();
            if (!$student || !$student->latest_id) {
                return response()->json(['message' => 'الطالب أو سجل إنجازاته غير موجود'], 404);
            }

            $item_to_delete = json_decode($request->getContent());
            if (!$item_to_delete) {
                return response()->json(['message' => 'بيانات الحذف غير صحيحة'], 422);
            }

            $latest = latest::find($student->latest_id);
            $hadith_data = json_decode($latest->hadith, true);

            if (!is_array($hadith_data)) {
                return response()->json(['message' => 'بيانات الحديث غير صالحة'], 422);
            }

            $item_found = false;
            
            // ✅ اللوب هنا أبسط (مصفوفة مباشرة)
            foreach ($hadith_data as $key => $item) {
                if (!is_array($item)) continue;

                // ✅ شرط المطابقة: لا يوجد 'from' أو 'to' أو 'type'
                if (
                    isset($item['num'], $item['mark'], $item['point']) &&
                    $item['num'] == $item_to_delete->num &&
                    $item['mark'] == $item_to_delete->mark &&
                    $item['point'] == $item_to_delete->point 
                    // (يمكن الاكتفاء بـ num و mark إذا كانت كافية للتمييز)
                ) {

                    // ✅ استدعاء دالة حذف التقرير للحديث
                    $result = $this->if_latest_delete_from_report_hadith(
                        (object)$item,
                        $student->user_id,
                        $student->teacher_id
                    );

                    if (isset($result['error']) && $result['error']) {
                        return response()->json($result, 401);
                    }

                    unset($hadith_data[$key]);
                    $item_found = true;
                    break; // كسر اللوب بعد العثور على العنصر
                }
            }
            
            if (!$item_found) {
                return response()->json(['message' => 'الإنجاز المطلوب حذفه غير موجود'], 404);
            }

            // ✅ إعادة ترتيب الفهارس وتحديث قاعدة البيانات
            $latest->update(['hadith' => json_encode(array_values($hadith_data))]);

            // TODO: إرسال إشعار (يمكنك إنشاء كلاس إشعار جديد للحديث)
            // $user_to_notify = User::find($student->user_id);
            // if ($user_to_notify) {
            //     Notification::send($user_to_notify, new delete_latest_hadith_notification($item_to_delete));
            // }

            return response()->json(['deleted' => true, 'message' => 'تم حذف إنجاز الحديث بنجاح'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function if_latest_delete_from_report_hadith($hadith_item, $user_id, $teacher_id)
    {
        try {
            $report = report::where('user_id', $user_id)->first();
            
            // ✅ افتراض اسم الحقل في التقرير
            if (!$report || !$report->ended_hadith_this_course) {
                return ['error' => 'لا يوجد تقرير لهذا الإنجاز'];
            }

            $report_hadith = json_decode($report->ended_hadith_this_course);

            // ✅ التأكد من أن البنية مصفوفة
            if (!is_array($report_hadith)) {
                Log::warning("بنية تقرير الحديث غير صالحة للمستخدم: $user_id");
                return ['error' => 'بنية تقرير الحديث غير صالحة للمستخدم'];
            }

            // 1. تحديد بيانات العنصر المحذوف
            $item_to_delete = (object)$hadith_item;
            $mark = $item_to_delete->mark ?? 0;
            $points_to_remove = $item_to_delete->point ?? 0;
            $item_was_successful = $mark >= 80; // (أو حسب معيار النجاح لديكم)

            $item_index = null;
            $item_data = null;

            // 2. البحث عن الحديث المطابق في التقرير
            foreach ($report_hadith as $index => $item) {
                if ($item->num == $item_to_delete->num) {
                    $item_index = $index;
                    $item_data = $item;
                    break;
                }
            }

            // 3. إذا وجد، قم بعكس العمليات
            if ($item_data) {
                // ✅ لا نحتاج للتحقق من 'from' و 'to'
                $report_controller = new ReportController;
                // ✅ استدعاء دالة جديدة في ReportController
                $report_controller->remove_latest_hadith(
                    hadith_data: $item_data,
                    item_was_successful: $item_was_successful,
                    points_to_remove: $points_to_remove,
                    user_id: $user_id,
                    mark: $mark,
                    item_index: $item_index,
                    report_hadith_list: $report_hadith, // إرسال المصفوفة كاملة
                    report: $report,
                    hadith_item: $hadith_item
                );
            } else {
                return false; // لا يوجد تقرير، لا مشكلة
            }
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }
// ==========================================================
    //  حذف النشاط (نسخة مطابقة للحديث مع تغيير الأسماء)
    // ==========================================================
    public function delete_latest_activity(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية الحذف'], 403);
            }

            $student = student::where('user_id', $user_id)->first();
            if (!$student || !$student->latest_id) {
                return response()->json(['message' => 'الطالب أو سجل إنجازاته غير موجود'], 404);
            }

            $item_to_delete = json_decode($request->getContent());
            if (!$item_to_delete) {
                return response()->json(['message' => 'بيانات الحذف غير صحيحة'], 422);
            }

            $latest = latest::find($student->latest_id);
            $activity_data = json_decode($latest->activities, true); // ✅ activities

            // إذا تم الترميز مرتين
            if (is_string($activity_data)) {
                $activity_data = json_decode($activity_data, true);
            }

            if (!is_array($activity_data)) {
                return response()->json([
                    'message' => 'بيانات الأنشطة غير صالحة',
                    'debug' => $latest->activities // للمساعدة في الفحص
                ], 422);
            }
            $item_found = false;

            foreach ($activity_data as $key => $item) {
                if (!is_array($item)) continue;

                if (
                    isset($item['name'], $item['mark'], $item['point']) &&
                    $item['name'] == $item_to_delete->name && // ✅ name
                    $item['mark'] == $item_to_delete->mark &&
                    $item['point'] == $item_to_delete->point
                ) {

                    $result = $this->if_latest_delete_from_report_activity(
                        (object)$item,
                        $student->user_id,
                        $student->teacher_id
                    );

                    // ✅ تصحيح: التحقق من وجود الخطأ وإرجاعه
                    if (isset($result['error']) && $result['error']) {
                        return response()->json($result, 401);
                    }

                    unset($activity_data[$key]);
                    $item_found = true;
                    break;
                }
            }

            if (!$item_found) {
                return response()->json(['message' => 'الإنجاز المطلوب حذفه غير موجود'], 404);
            }

            $latest->update(['activities' => json_encode(array_values($activity_data))]);

            return response()->json(['deleted' => true, 'message' => 'تم حذف النشاط بنجاح'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function if_latest_delete_from_report_activity($activity_item, $user_id, $teacher_id)
    {
        try {
            $report = report::where('user_id', $user_id)->first();
            
            if (!$report || !$report->activities_this_course) { 
                return ['error' => 'لا يوجد تقرير لهذا الإنجاز'];
            }

            $report_activities = json_decode($report->activities_this_course); 

            if (!is_array($report_activities)) { 
                Log::warning("بنية تقرير الأنشطة غير صالحة للمستخدم: $user_id");
                return ['error' => 'بنية تقرير الأنشطة غير صالحة للمستخدم'];
            }

            $item_to_delete = (object)$activity_item;
            $mark = $item_to_delete->mark ?? 0;
            $points_to_remove = $item_to_delete->point ?? 0;
            $item_was_successful = $mark >= 80;

            $item_index = null;
            $item_data = null;

            foreach ($report_activities as $index => $item) {
                if ($item->name == $item_to_delete->name) { // ✅ name
                    $item_index = $index;
                    $item_data = $item;
                    break;
                }
            }

            if ($item_data) {
                $report_controller = new ReportController;
                
                $result = $report_controller->remove_latest_activity( 
                    activity_data: $item_data, 
                    item_was_successful: $item_was_successful,
                    points_to_remove: $points_to_remove,
                    user_id: $user_id,
                    mark: $mark,
                    item_index: $item_index,
                    report_activity_list: $report_activities, 
                    report: $report,
                    activity_item: $activity_item 
                );

                // ✅ تصحيح: التحقق من $result (الذي قد يكون null عند النجاح)
                if (is_array($result) && isset($result['error'])) {
                    return $result; // إرجاع مصفوفة الخطأ
                }

            } else {
                return false;
            }
            
            return true; // ✅ الإشارة إلى النجاح
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    // ==========================================================
    //  حذف آخر ملاحظة (النسخة المصححة)
    // ==========================================================
    public function delete_latest_note(Request $request, $user_id)
    {
        try {
            // 1. التحقق من صلاحيات المستخدم
            if (auth('api')->user()->privilege <= 1) {
                return response()->json(['message' => 'لا تملك صلاحية الحذف'], 403);
            }

            // 2. البحث عن الطالب وسجل إنجازاته الأخير
            $student = Student::where('user_id', $user_id)->first();
            if (!$student || !$student->latest_id) {
                return response()->json(['message' => 'الطالب أو سجل إنجازاته غير موجود'], 404);
            }

            $latest = Latest::find($student->latest_id);
            if (!$latest) {
                return response()->json(['message' => 'سجل الإنجازات الأخير غير موجود'], 404);
            }

            // 3. ✅ فك ترميز الملاحظة من 'latest'
            // $latest->note هو نص JSON: '{"note":"88","LPoints":"100"}'
            $latest_note_data = json_decode($latest->note, true);

            // 4. التحقق من وجود ملاحظة صالحة ليتم حذفها
            if (is_array($latest_note_data) && isset($latest_note_data['note'])) {
                
                // 4a. ✅ قراءة البيانات الصحيحة من 'latest'
                $note_text_to_find = $latest_note_data['note'];
                // ✅ الانتباه: اسم الحقل 'LPoints' في latest
                $lost_points_to_find = $latest_note_data['LPoints'] ?? 0; 

                // 4b. ✅ إنشاء كائن الملاحظة الصحيح لتمريره
                // هذا الكائن يجب أن يطابق بنية *التقرير* (report)
                $note_item_to_delete = [
                    'note' => $note_text_to_find,
                    // ✅ الانتباه: اسم الحقل 'lost_point' في report
                    'lost_point' => $lost_points_to_find 
                ];

                // 4c. استدعاء التابع لعكس الملاحظة
                $report_controller = new ReportController();
                $report_controller->remove_latest_note($note_item_to_delete, $user_id);
            
            }
            // إذا لم تكن $latest_note_data مصفوفة، فهذا يعني أن الحقل فارغ (null)
            // وسننتقل مباشرة إلى الخطوة 5 لتأكيد الحذف (لا مشكلة)

            // 5. ✅ تحديث سجل 'latest' لإزالة الملاحظة
            // (فقط عمود 'note'، لأن 'LPoints' غير موجود)
            $latest->update([
                'note' => null, 
            ]);
            
            return response()->json(['deleted' => true, 'message' => 'تم حذف الملاحظة بنجاح'], 200);

        } catch (\Throwable $th) {
            Log::error('خطأ في delete_latest_note: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json(['error' => 'حدث خطأ غير متوقع أثناء الحذف'], 500);
        }
    }
    public function  add_latest_hadith(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege > 1) {
                $student_id = student::where('user_id', $user_id)->first('id')->id;
                $student = student::find($student_id);
                $teacher_id = $student->teacher_id;
                if ($teacher_id != null) {
                    $data = json_decode($request->getContent());
                    $user_id = $student->user_id;
                    $updated = false;
                    $latest_created = false;
                    if ($student->latest_id) {
                        $updated = latest::where('id', $student->latest_id)->update([
                            'hadith' => json_encode($data[0]),
                            'h_homework' => json_encode($data[1])
                        ]);
                    } else {
                        $latest_created = latest::create([
                            'hadith' => json_encode($data[0])
                        ]);
                        student::where('id', $student_id)->update([
                            'latest_id' => $latest_created->id
                        ]);
                    }
                    $report_controller = new ReportController;
                    $report   = $report_controller->add_latest_hadith($data[0], $user_id, $teacher_id);
                    ////////////////// make note ////////////////////
                    $users = User::where('id', '=', $student->user_id)->get();
                    Notification::send(
                        $users,
                        new latest_hadith_with_homework($data)
                    );

                    return response()->json([
                        'updated' => true
                    ], 200);

                    // return response()->json([
                    //         'latest_created' => $latest_created,
                    //         'updated' => $updated,
                    //         'student'=>$student,
                    //         'report'=>$report
                    //     ],200);

                } else {
                    return response()->json([
                        'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاولة',
                    ], 403);
                }
            } else {
                return response()->json([
                    'message' => 'لا تملك صلاحية اضافة تسميعات',
                ], 403);
            }
        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage(), 'paht' => $th->getTrace()], 500);
        }
    }


    public function  add_latest_activity(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege > 1) {
                $student_id = student::where('user_id', $user_id)->first('id')->id;
                $student = student::find($student_id);
                $teacher_id = $student->teacher_id;
                if ($teacher_id != null) {
                    //add latest activity
                    $data =   $request->getContent();

                    $user_id = $student->user_id;
                    $updated = false;
                    $latest_created = false;
                    if ($student->latest_id) {
                        $updated = latest::where('id', $student->latest_id)->update([
                            'activities' => $data
                        ]);
                    } else {
                        $latest_created = latest::create([
                            'activities' => $data
                        ]);
                        student::where('id', $student_id)->update([
                            'latest_id' => $latest_created->id
                        ]);
                    }
                    $report_controller = new ReportController;
                    $report   = $report_controller->add_latest_activity(json_decode($data), $user_id, $teacher_id);
                    ////////////////// make note ////////////////////
                    $users = User::where('id', '=', $student->user_id)->get();
                    Notification::send(
                        $users,
                        new latest_activities(json_decode($data))
                    );
                    return response()->json([
                        'latest_created' => $latest_created,
                        'updated' => $updated,
                        'student' => $student,
                        'report' => $report
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاول',
                    ], 403);
                }
            } else {
                return response()->json([
                    'message' => 'لا تمتلك صلاحية اضافة نشاطات للطلاب',
                ], 403);
            }
        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function  add_latest_note(Request $request, $user_id)
    {
        try {
            if (auth('api')->user()->privilege > 1) {
                $student_id = student::where('user_id', $user_id)->first('id')->id;
                $student = student::find($student_id);
                $teacher_id = $student->teacher_id;
                if ($teacher_id != null) {
                    //add latest note
                    $note = $request->note;
                    $lost_points = $request->lost_point;
                    $user_id = $student->user_id;
                    $updated = false;
                    $latest_created = false;
                    if ($student->latest_id) {
                        $updated = latest::where('id', $student->latest_id)->update([
                            'note' => json_encode(['note' => $note, 'LPoints' => $lost_points])
                        ]);
                    } else {
                        $latest_created = latest::create([
                            'note' => $note
                        ]);
                        student::where('id', $student_id)->update([
                            'latest_id' => $latest_created->id
                        ]);
                    }
                    $report_controller = new ReportController;
                    $report   = $report_controller->add_latest_note($note, $request->lost_point, $user_id, $teacher_id);

                    if ($request->lost_point != 0) {
                        $l_points = point::find($student->point_id)->l_points;
                        point::where('id', $student->point_id)->update([
                            'l_points' => $l_points + $request->lost_point
                        ]);
                    }

                    ////////////////// make note ////////////////////
                    $users = User::where('id', '=', $student->user_id)->get();
                    Notification::send(
                        $users,
                        new latest_notes([$request->note, $request->lost_point])
                    );


                    return response()->json([
                        'creted' => true
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاول',
                    ], 403);
                }
            } else {
                return response()->json([
                    'message' => 'لا تمتلك صلاحية لإضافة آخر الملاحظات',
                ], 403);
            }
        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function  get_latest_for_student(Request $request)
    {
        try {
            if (Auth::user()->privilege > 1) {
                if (!$request->user_id)
                    return response()->json(["the user id is required"]);

                $latest_id = student::where('user_id', $request->user_id)->first('latest_id');
                $latest = latest::find($latest_id);
                return response()->json($latest);
            } else {
                $latest_id = student::where('user_id', Auth::user()->id)->first('latest_id');
                $latest = latest::find($latest_id);
                return response()->json($latest);
            }
        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage(), 'path' => $th->getTrace()], 500);
        }
    }


    public function get_rank_my_group(Request $request)
    {
        try {
            $user = Auth::user();
            $teacher_id = null;

            if ($user->privilege > 1) { // إذا كان المستخدم أستاذ
                $teacher_id = $user->id;
            } else { // إذا كان المستخدم طالب
                $student_info = student::where('user_id', $user->id)->first();
                $teacher_id = $student_info ? $student_info->teacher_id : null;
            }

            if (!$teacher_id) {
                return response()->json([]); // أو رسالة خطأ مناسبة
            }

            // جلب الطلاب مع الحقول الجديدة
            $students = student::where('teacher_id', $teacher_id)
                ->get(['user_id', 'point_id', 'teacher_id', 'missing_days', 'last_attendance_status']); // ✅ أضفنا الحقول الجديدة

            foreach ($students as $student) {
                $student->user_name = User::find($student->user_id)->name;
                if ($student->point_id != null) {
                    $points = point::find($student->point_id);
                    $student->points = $points->q_points + $points->h_points + $points->a_points - $points->l_points;
                } else {
                    $student->points = 0;
                }
            }

            return response()->json($students);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage(), 'path' => $th->getTrace()], 500);
        }
    }
    public function get_rank_masjed(Request $request)
    {
        try {
            $request->validate([
                'daora_id' => 'required|integer|exists:daoras,id',
            ]);

            $daoraId = $request->input('daora_id');

            $students = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('points', 'students.point_id', '=', 'points.id')
                ->whereNotNull('students.teacher_id')
                ->where('users.daora_id', $daoraId)
                ->select(
                    'students.user_id',
                    'students.teacher_id',
                    'users.name as user_name',
                    'students.missing_days',           // ✅ أضف هذا السطر
                    'students.last_attendance_status', // ✅ أضف هذا السطر
                    DB::raw('COALESCE(points.q_points,0) + COALESCE(points.h_points,0) + COALESCE(points.a_points,0) - COALESCE(points.l_points,0) as points')
                )
                ->get();

            return response()->json($students);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
                'path' => $th->getTrace()
            ], 500);
        }
    }
}
