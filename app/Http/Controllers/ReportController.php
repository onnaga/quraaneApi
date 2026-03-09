<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    use ApiResponseTrait;

    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function add_latest_quraan($data, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestQuraan($data, $user_id, $teacher_id);
    }

    public function remove_latest_quraan($sora_data, $item_was_successful, $points_to_remove, $user_id, $mark, $sora_index, $type, $report_quraan, $report, $quraan_item)
    {
        return $this->reportService->removeLatestQuraan($sora_data, $item_was_successful, $points_to_remove, $user_id, $mark, $sora_index, $type, $report_quraan, $report, $quraan_item);
    }

    public function remove_latest_hadith($hadith_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_hadith_list, $report, $hadith_item) 
    {
        return $this->reportService->removeLatestHadith($hadith_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_hadith_list, $report, $hadith_item);
    }

    public function remove_latest_activity($activity_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_activity_list, $report, $activity_item) 
    {
        return $this->reportService->removeLatestActivity($activity_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_activity_list, $report, $activity_item);
    }

    public function remove_latest_note($note_item, $user_id)
    {
        return $this->reportService->removeLatestNote($note_item, $user_id);
    }

    public function add_latest_hadith($data, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestHadith($data, $user_id, $teacher_id);
    }

    public function add_latest_activity($data, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestActivity($data, $user_id, $teacher_id);
    }

    public function add_latest_note($oneNote, $lost_point, $user_id, $teacher_id)
    {
        return $this->reportService->addLatestNote($oneNote, $lost_point, $user_id, $teacher_id);
    }

    public function show_reports()
    {
        try {
            $requestingUser = Auth::user();
            $daora_id = $requestingUser->daora_id;

            if (!$daora_id) {
                return $this->errorSimpleMessage('المستخدم الحالي غير مسجل في أي دورة.', 404);
            }

            $reports = $this->reportService->getReportsByDaora($daora_id);
            
            // للحفاظ على شكل الصنف والواجهة
            return $this->successResponse($reports);

        } catch (\Throwable $th) {
            return $this->successResponse(['error' => 'حدث خطأ غير متوقع في الخادم'], 500);
        }
    }
}
