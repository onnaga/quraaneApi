<?php

namespace App\Services;

use App\Models\latest;
use App\Models\point;
use App\Models\report;
use App\Models\student;
use App\Models\User;
use App\Http\Controllers\ReportController;
use App\Notifications\delete_latest_quraan_notification;
use App\Notifications\latest_activities;
use App\Notifications\latest_hadith_with_homework;
use App\Notifications\latest_notes;
use App\Notifications\latest_quraan_with_homework;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LatestService
{
    protected $reportController;

    public function __construct(ReportController $reportController)
    {
        $this->reportController = $reportController;
    }

    public function addQuraan($data, $user_id, $authUser)
    {
        $student = student::where('user_id', $user_id)->first();
        if (!$student) {
            throw new \Exception('الطالب غير موجود', 404);
        }

        if (!$student->teacher_id) {
            throw new \Exception('المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاولة', 403);
        }

        if (!is_array($data) || count($data) < 2) {
            throw new \Exception('صيغة البيانات المرسلة غير صحيحة', 422);
        }

        $quran_data = $data[0];
        $homework_data = $data[1];

        $sortByType = function ($items) {
            $sorted = ['ghaiban' => [], 'nazaran' => []];
            foreach ($items as $item) {
                $type = $item->type ?? 'ghaiban';
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

        $this->reportController->add_latest_quraan($quran_data, $student->user_id, $student->teacher_id);

        $users = User::where('id', '!=', $authUser->id)->where('id', $student->user_id)->get();
        Notification::send($users, new latest_quraan_with_homework($data));

        return true;
    }

    public function deleteQuraan($item_to_delete, $user_id)
    {
        $student = student::where('user_id', $user_id)->first();
        if (!$student || !$student->latest_id) {
            throw new \Exception('الطالب أو سجل إنجازاته غير موجود', 404);
        }

        $latest = latest::find($student->latest_id);
        $quran_data = json_decode($latest->quran, true);

        if (!is_array($quran_data)) {
            throw new \Exception('بيانات القرآن غير صالحة في سجل الطالب', 422);
        }

        $item_found = false;

        foreach ($quran_data as $type => &$items) {
            if (!is_array($items)) continue;

            foreach ($items as $key => $item) {
                if (!is_array($item)) continue;

                if (
                    isset($item['num'], $item['from'], $item['to'], $item['type']) &&
                    $item['num'] == $item_to_delete->num &&
                    $item['from'] == $item_to_delete->from &&
                    $item['to'] == $item_to_delete->to &&
                    $item['type'] == $item_to_delete->type
                ) {
                    $result  = $this->if_latest_delete_from_report(
                        (object)$item,
                        $student->user_id,
                        $student->teacher_id,
                        $item_to_delete
                    );
                    if (isset($result['error']) && $result['error']) {
                        throw new \Exception($result['error'], 401);
                    }

                    unset($items[$key]);
                    $item_found = true;
                    break 2;
                }
            }
        }

        if (!$item_found) {
            throw new \Exception('الإنجاز المطلوب حذفه غير موجود', 404);
        }

        $quran_data = array_map('array_values', $quran_data);
        $latest->update(['quran' => json_encode($quran_data)]);

        $user_to_notify = User::find($student->user_id);
        if ($user_to_notify) {
            Notification::send($user_to_notify, new delete_latest_quraan_notification($item_to_delete));
        }

        return true;
    }

    public function if_latest_delete_from_report($quraan_item, $user_id, $teacher_id, $deleted_request_item)
    {
        $report = report::where('user_id', $user_id)->first();
        if (!$report || !$report->ended_quraan_this_course) {
            return ['error' => 'لا يوجد تقرير لهذا الإنجاز'];
        }

        $report_quraan = json_decode($report->ended_quraan_this_course);

        if (!is_object($report_quraan) || !isset($report_quraan->ghaiban) || !isset($report_quraan->nazaran)) {
            Log::warning("بنية تقرير القرآن غير صالحة للمستخدم: $user_id");
            return  ['error' => 'بنية تقرير القرآن غير صالحة للمستخدم'];
        }

        $item_to_delete = (object)$quraan_item;
        $type = $item_to_delete->type ?? 'ghaiban';
        $mark = $item_to_delete->mark ?? 0;
        $points_to_remove = $item_to_delete->point ?? 0;
        $item_was_successful = $mark >= 80;

        $deleted_from = $deleted_request_item->from ?? null;
        $deleted_to = $deleted_request_item->to ?? null;

        if (!in_array($type, ['ghaiban', 'nazaran'])) {
            $type = 'ghaiban';
        }

        $sora_index = null;
        $sora_data = null;

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
                $this->reportController->remove_latest_quraan(
                    $sora_data,
                    $item_was_successful,
                    $points_to_remove,
                    $user_id,
                    $mark,
                    $sora_index,
                    $type,
                    $report_quraan,
                    $report,
                    $quraan_item
                );
            } else {
                return ['error' => 'لا تستطيع حذف العنصر لأنه ليس آخر ما تم تسميعه من السورة'];
            }
        } else {
            return false;
        }

        return true;
    }


    public function addHadith($data, $user_id)
    {
        $student_id = student::where('user_id', $user_id)->first('id')->id;
        $student = student::find($student_id);
        $teacher_id = $student->teacher_id;

        if ($teacher_id == null) {
            throw new \Exception('المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاولة', 403);
        }

        if ($student->latest_id) {
            latest::where('id', $student->latest_id)->update([
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

        $this->reportController->add_latest_hadith($data[0], $user_id, $teacher_id);

        $users = User::where('id', '=', $student->user_id)->get();
        Notification::send($users, new latest_hadith_with_homework($data));

        return true;
    }

    public function deleteHadith($item_to_delete, $user_id)
    {
        $student = student::where('user_id', $user_id)->first();
        if (!$student || !$student->latest_id) {
            throw new \Exception('الطالب أو سجل إنجازاته غير موجود', 404);
        }

        $latest = latest::find($student->latest_id);
        $hadith_data = json_decode($latest->hadith, true);

        if (!is_array($hadith_data)) {
            throw new \Exception('بيانات الحديث غير صالحة', 422);
        }

        $item_found = false;
        foreach ($hadith_data as $key => $item) {
            if (!is_array($item)) continue;

            if (
                isset($item['num'], $item['mark'], $item['point']) &&
                $item['num'] == $item_to_delete->num &&
                $item['mark'] == $item_to_delete->mark &&
                $item['point'] == $item_to_delete->point 
            ) {

                $result = $this->if_latest_delete_from_report_hadith(
                    (object)$item,
                    $student->user_id,
                    $student->teacher_id
                );

                if (isset($result['error']) && $result['error']) {
                    throw new \Exception($result['error'], 401);
                }

                unset($hadith_data[$key]);
                $item_found = true;
                break; 
            }
        }
        
        if (!$item_found) {
            throw new \Exception('الإنجاز المطلوب حذفه غير موجود', 404);
        }

        $latest->update(['hadith' => json_encode(array_values($hadith_data))]);

        return true;
    }

    public function if_latest_delete_from_report_hadith($hadith_item, $user_id, $teacher_id)
    {
        $report = report::where('user_id', $user_id)->first();
        if (!$report || !$report->ended_hadith_this_course) {
            return ['error' => 'لا يوجد تقرير لهذا الإنجاز'];
        }

        $report_hadith = json_decode($report->ended_hadith_this_course);
        if (!is_array($report_hadith)) {
            Log::warning("بنية تقرير الحديث غير صالحة للمستخدم: $user_id");
            return ['error' => 'بنية تقرير الحديث غير صالحة للمستخدم'];
        }

        $item_to_delete = (object)$hadith_item;
        $mark = $item_to_delete->mark ?? 0;
        $points_to_remove = $item_to_delete->point ?? 0;
        $item_was_successful = $mark >= 80; 

        $item_index = null;
        $item_data = null;

        foreach ($report_hadith as $index => $item) {
            if ($item->num == $item_to_delete->num) {
                $item_index = $index;
                $item_data = $item;
                break;
            }
        }

        if ($item_data) {
            $this->reportController->remove_latest_hadith(
                $item_data,
                $item_was_successful,
                $points_to_remove,
                $user_id,
                $mark,
                $item_index,
                $report_hadith,
                $report,
                $hadith_item
            );
        } else {
            return false;
        }
        return true;
    }

    public function addActivity($data, $user_id)
    {
        $student_id = student::where('user_id', $user_id)->first('id')->id;
        $student = student::find($student_id);
        $teacher_id = $student->teacher_id;

        if ($teacher_id == null) {
            throw new \Exception('المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاول', 403);
        }

        if ($student->latest_id) {
            latest::where('id', $student->latest_id)->update([
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
        
        $this->reportController->add_latest_activity(json_decode($data), $user_id, $teacher_id);

        $users = User::where('id', '=', $student->user_id)->get();
        Notification::send($users, new latest_activities(json_decode($data)));

        return true;
    }

    public function deleteActivity($item_to_delete, $user_id)
    {
        $student = student::where('user_id', $user_id)->first();
        if (!$student || !$student->latest_id) {
            throw new \Exception('الطالب أو سجل إنجازاته غير موجود', 404);
        }

        $latest = latest::find($student->latest_id);
        $activity_data = json_decode($latest->activities, true); 

        if (is_string($activity_data)) {
            $activity_data = json_decode($activity_data, true);
        }

        if (!is_array($activity_data)) {
            throw new \Exception('بيانات الأنشطة غير صالحة', 422);
        }

        $item_found = false;

        foreach ($activity_data as $key => $item) {
            if (!is_array($item)) continue;

            if (
                isset($item['name'], $item['mark'], $item['point']) &&
                $item['name'] == $item_to_delete->name && 
                $item['mark'] == $item_to_delete->mark &&
                $item['point'] == $item_to_delete->point
            ) {
                $result = $this->if_latest_delete_from_report_activity(
                    (object)$item,
                    $student->user_id,
                    $student->teacher_id
                );

                if (isset($result['error']) && $result['error']) {
                    throw new \Exception($result['error'], 401);
                }

                unset($activity_data[$key]);
                $item_found = true;
                break;
            }
        }

        if (!$item_found) {
            throw new \Exception('الإنجاز المطلوب حذفه غير موجود', 404);
        }

        $latest->update(['activities' => json_encode(array_values($activity_data))]);
        return true;
    }

    public function if_latest_delete_from_report_activity($activity_item, $user_id, $teacher_id)
    {
        $report = report::where('user_id', $user_id)->first();
        
        if (!$report || !$report->activities_this_course) { 
            return ['error' => 'لا يوجد تقرير لهذا الإنجاز'];
        }

        $report_activities = json_decode($report->activities_this_course); 
        if (!is_array($report_activities)) { 
            return ['error' => 'بنية تقرير الأنشطة غير صالحة للمستخدم'];
        }

        $item_to_delete = (object)$activity_item;
        $mark = $item_to_delete->mark ?? 0;
        $points_to_remove = $item_to_delete->point ?? 0;
        $item_was_successful = $mark >= 80;

        $item_index = null;
        $item_data = null;

        foreach ($report_activities as $index => $item) {
            if ($item->name == $item_to_delete->name) { 
                $item_index = $index;
                $item_data = $item;
                break;
            }
        }

        if ($item_data) {
            $result = $this->reportController->remove_latest_activity( 
                $item_data, 
                $item_was_successful,
                $points_to_remove,
                $user_id,
                $mark,
                $item_index,
                $report_activities, 
                $report,
                $activity_item 
            );

            if (is_array($result) && isset($result['error'])) {
                return $result; 
            }
        } else {
            return false;
        }
        
        return true;
    }

    public function addNote($note, $lost_points, $user_id)
    {
        $student_id = student::where('user_id', $user_id)->first('id')->id;
        $student = student::find($student_id);
        $teacher_id = $student->teacher_id;

        if ($teacher_id == null) {
            throw new \Exception('المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاول', 403);
        }

        if ($student->latest_id) {
            latest::where('id', $student->latest_id)->update([
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

        $this->reportController->add_latest_note($note, $lost_points, $user_id, $teacher_id);

        if ($lost_points != 0) {
            $l_points = point::find($student->point_id)->l_points;
            point::where('id', $student->point_id)->update([
                'l_points' => $l_points + $lost_points
            ]);
        }

        $users = User::where('id', '=', $student->user_id)->get();
        Notification::send($users, new latest_notes([$note, $lost_points]));

        return true;
    }

    public function deleteNote($user_id)
    {
        $student = Student::where('user_id', $user_id)->first();
        if (!$student || !$student->latest_id) {
            throw new \Exception('الطالب أو سجل إنجازاته غير موجود', 404);
        }

        $latest = Latest::find($student->latest_id);
        if (!$latest) {
            throw new \Exception('سجل الإنجازات الأخير غير موجود', 404);
        }

        $latest_note_data = json_decode($latest->note, true);

        if (is_array($latest_note_data) && isset($latest_note_data['note'])) {
            $note_text_to_find = $latest_note_data['note'];
            $lost_points_to_find = $latest_note_data['LPoints'] ?? 0; 
            $note_item_to_delete = [
                'note' => $note_text_to_find,
                'lost_point' => $lost_points_to_find 
            ];

            $this->reportController->remove_latest_note($note_item_to_delete, $user_id);
        }

        $latest->update(['note' => null]);

        return true;
    }

    public function getLatestForStudent($user_id, $authUser)
    {
        if ($authUser->privilege > 1) {
            if (!$user_id) {
                throw new \Exception("the user id is required", 400);
            }
            $target_id = $user_id;
        } else {
            $target_id = $authUser->id;
        }

        $latest_id = student::where('user_id', $target_id)->value('latest_id');
        return latest::find($latest_id);
    }

    public function getRankMyGroup($authUser)
    {
        $teacher_id = null;

        if ($authUser->privilege > 1) { 
            $teacher_id = $authUser->id;
        } else {
            $student_info = student::where('user_id', $authUser->id)->first();
            $teacher_id = $student_info ? $student_info->teacher_id : null;
        }

        if (!$teacher_id) {
            return ['students' => [], 'halakas' => []];
        }

        $students = student::where('teacher_id', $teacher_id)
            ->get(['user_id', 'point_id', 'teacher_id', 'missing_days', 'last_attendance_status']); 

        foreach ($students as $student) {
            $student->user_name = User::find($student->user_id)->name;
            if ($student->point_id != null) {
                $points = point::find($student->point_id);
                $student->points = $points->q_points + $points->h_points + $points->a_points - $points->l_points;
            } else {
                $student->points = 0;
            }
        }

        $halakas = DB::table('halakas')
            ->join('users', 'halakas.teacher_id', '=', 'users.id')
            ->where('halakas.teacher_id', $teacher_id)
            ->select('halakas.id', 'halakas.name as halaka_name', 'halakas.teacher_id', 'users.name as teacher_name', 'halakas.students_count')
            ->get();

        return [
            'students' => $students,
            'halakas' => $halakas
        ];
    }

    public function getRankMasjed($daoraId)
    {
        $students = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('points', 'students.point_id', '=', 'points.id')
            ->whereNotNull('students.teacher_id')
            ->where('users.daora_id', $daoraId)
            ->select(
                'students.user_id',
                'students.teacher_id',
                'users.name as user_name',
                'students.missing_days',
                'students.last_attendance_status',
                DB::raw('COALESCE(points.q_points,0) + COALESCE(points.h_points,0) + COALESCE(points.a_points,0) - COALESCE(points.l_points,0) as points')
            )
            ->get();

        $halakas = DB::table('halakas')
            ->join('users', 'halakas.teacher_id', '=', 'users.id')
            ->where('halakas.daora_id', $daoraId)
            ->select('halakas.id', 'halakas.name as halaka_name', 'halakas.teacher_id', 'users.name as teacher_name', 'halakas.students_count')
            ->get();

        return [
            'students' => $students,
            'halakas' => $halakas
        ];
    }
}
