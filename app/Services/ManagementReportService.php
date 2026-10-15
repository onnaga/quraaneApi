<?php

namespace App\Services;

use App\Models\Halaka;
use App\Models\Report;
use App\Models\Student;
use App\Models\StudentGoal;
use App\Models\UserBehavior;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * خدمة حساب إحصائيات التقارير الإدارية.
 *
 * تُستخدم من ManagementReportController لتجميع الإحصائيات
 * لتقارير الحلقات (privilege 2) وتقارير المساجد (privilege 3).
 */
class ManagementReportService
{
    // ========================================================================
    //  Quran repetition types used across multiple calculations
    // ========================================================================

    private const QURAN_TYPES  = ['ghaiban', 'nazaran', 'sibr_juz'];
    private const HADITH_TYPES = ['ghaiban', 'sibr_juz'];

    // ========================================================================
    //  Public API
    // ========================================================================

    /**
     * إحصائيات أستاذ واحد (تقرير الحلقة).
     */
    public function getTeacherStats(int $teacherId): array
    {
        $teacher = \App\Models\User::findOrFail($teacherId);
        $halaka  = Halaka::where('teacher_id', $teacherId)->first();

        $studentUserIds = Student::where('teacher_id', $teacherId)
            ->pluck('user_id')
            ->toArray();

        return [
            'halaka_id'      => $halaka?->id,
            'halaka_name'    => $halaka?->name ?? 'بدون حلقة',
            'teacher_name'   => $teacher->name ?? 'غير محدد',
            'students_count' => count($studentUserIds),
        ] + $this->buildStudentStats($studentUserIds, $teacher->daora_id);
    }

    /**
     * إحصائيات المسجد / الدورة بالكامل (تقرير المسجد).
     */
    public function getMosqueStats(int $daoraId): array
    {
        $allTeachers = \App\Models\User::where('daora_id', $daoraId)
            ->where('privilege', '>', 1)
            ->with('daoraPeriods')
            ->get();

        if ($allTeachers->isEmpty()) {
            return $this->emptyMosqueStats();
        }

        $teacherIds = $allTeachers->pluck('id')->toArray();
        $halakas    = Halaka::where('daora_id', $daoraId)->with('teacher')->get();

        $allStudentUserIds = Student::whereIn('teacher_id', $teacherIds)
            ->pluck('user_id')
            ->toArray();

        return [
            'students_count' => count($allStudentUserIds),
            'halakas_count'  => $halakas->count(),
        ]
            + $this->buildStudentStats($allStudentUserIds, $daoraId)
            + $this->buildSupervisorStats($teacherIds)
            + $this->buildDailyTasksStats($teacherIds)
            + ['periods_stats' => $this->buildPeriodsStats($daoraId)]
            + ['teachers_list' => $this->buildTeachersList($allTeachers, $halakas)];
    }

    // ========================================================================
    //  إحصائيات الطلاب المشتركة
    // ========================================================================

    /**
     * الإحصائيات المشتركة بين تقرير الحلقة وتقرير المسجد.
     */
    private function buildStudentStats(array $userIds, ?int $daoraId): array
    {
        if (empty($userIds)) {
            return $this->emptyStudentStats();
        }

        $absenceData = $this->calcAbsenceData($userIds);

        return [
            // الغياب
            'total_absence_days'              => $absenceData['total_absence_days'],
            'students_absent_count'           => $absenceData['students_absent_count'],
            'absent_students'                 => $this->getAbsentStudentsWithReasons($userIds),
            'unjustified_absences_count'      => $this->countUnjustifiedAbsences($userIds),
            // الواجبات
            'avg_homework_compliance'         => $this->calcHomeworkCompliance($userIds),
            'students_without_homework'       => $this->getStudentsWithoutHomework($userIds),
            // الأهداف
            'students_without_goals'          => $this->countWithoutGoals($userIds),
            'students_with_unachieved_goals'  => $this->countWithUnachievedGoals($userIds),
            'total_goals_set'                 => $this->countGoalsByStatus($userIds),
            'total_goals_achieved'            => $this->countGoalsByStatus($userIds, 'achieved'),
            'goals_in_progress'               => $this->countGoalsByStatus($userIds, 'in_progress'),
            // القرآن
            'quran_no_completion_count'       => $this->countNoCompletion($userIds, 'ended_quraan_this_course', self::QURAN_TYPES),
            'quran_failed_repetitions_count'  => $this->countFailedRepetitions($userIds, 'ended_quraan_this_course', self::QURAN_TYPES),
            'quran_success_repetitions_count' => $this->countSuccessRepetitions($userIds, 'ended_quraan_this_course', self::QURAN_TYPES),
            'quran_no_assignment_count'       => $this->countNoAssignment($userIds, 'q_homework'),
            'quran_achievement_summary'       => $this->getQuranAchievementSummary($userIds),
            // الحديث
            'hadith_no_completion_count'      => $this->countNoCompletion($userIds, 'ended_hadith_this_course', self::HADITH_TYPES),
            'hadith_failed_repetitions_count' => $this->countFailedRepetitions($userIds, 'ended_hadith_this_course', self::HADITH_TYPES),
            'hadith_success_repetitions_count'=> $this->countSuccessRepetitions($userIds, 'ended_hadith_this_course', self::HADITH_TYPES),
            'hadith_no_assignment_count'      => $this->countNoAssignment($userIds, 'h_homework'),
            // السبر
            'students_applied_for_test'       => $this->countTestParticipants($userIds, $daoraId, aukaf: false),
            'students_passed_aukaf'           => $this->countTestParticipants($userIds, $daoraId, aukaf: true, passedOnly: true),
            // السلوك
            'students_without_behavior'           => $this->countWithoutBehavior($userIds),
            'students_behavior_not_updated_1year' => $this->countBehaviorOlderThanYear($userIds),
        ];
    }

    // ========================================================================
    //  إحصائيات المشرفين (خاصة بتقرير المسجد)
    // ========================================================================

    private function buildSupervisorStats(array $teacherIds): array
    {
        return [
            'supervisors_without_goals'              => $this->countWithoutGoals($teacherIds),
            'supervisors_with_unachieved_goals'       => $this->countWithUnachievedGoals($teacherIds),
            'supervisors_total_goals'                 => $this->countGoalsByStatus($teacherIds),
            'supervisors_achieved_goals'              => $this->countGoalsByStatus($teacherIds, 'achieved'),
            'supervisors_goals_in_progress'           => $this->countGoalsByStatus($teacherIds, 'in_progress'),
            'supervisors_without_behavior'            => $this->countWithoutBehavior($teacherIds),
            'supervisors_behavior_not_updated_1year'  => $this->countBehaviorOlderThanYear($teacherIds),
        ];
    }

    // ========================================================================
    //  المهام اليومية (خاصة بتقرير المسجد)
    // ========================================================================

    private function buildDailyTasksStats(array $teacherIds): array
    {
        return [
            'daily_report_copy_count' => $this->countDailyTasksWhere($teacherIds, 'report_copied', true),
            'avg_delay_hours'         => $this->calcAvgDelayHoursForGroup($teacherIds),
        ];
    }

    // ========================================================================
    //  قائمة الأساتذة (خاصة بتقرير المسجد)
    // ========================================================================

    private function buildTeachersList($teachers, $halakas): array
    {
        $today = Carbon::now()->toDateString();

        return $teachers->map(function ($teacher) use ($halakas, $today) {
            $task = \App\Models\TeacherDailyTask::where('teacher_id', $teacher->id)
                ->where('task_date', $today)
                ->orderByDesc('created_at')
                ->first();

            $teacherHalaka = $halakas->firstWhere('teacher_id', $teacher->id);
            $absences = $this->getTeacherAbsences($teacher->id);

            // عدد الأيام الفريدة التي يوجد فيها سجل مهام (أيام العمل الفعلية)
            $totalActiveDays = DB::table('teacher_daily_tasks')
                ->where('teacher_id', $teacher->id)
                ->distinct('task_date')
                ->count('task_date');

            // عدد أيام التأخير (الأيام التي تأخر فيها ساعة أو أكثر)
            $lateDaysCount = DB::table('teacher_daily_tasks')
                ->where('teacher_id', $teacher->id)
                ->where(function ($q) {
                    $q->where('late_1_hour', true)
                      ->orWhere('late_2_hours', true);
                })
                ->distinct('task_date')
                ->count('task_date');

            $dailyReportCopyCount = $this->countDailyTasksWhere([$teacher->id], 'report_copied', true);
            $missedReportCopies = max(0, $totalActiveDays - $dailyReportCopyCount);

            $teacherPeriods = $teacher->daoraPeriods->map(function ($p) {
                return [
                    'id'          => $p->id,
                    'period_name' => $p->period_name,
                    'start_time'  => $p->start_time,
                    'end_time'    => $p->end_time,
                ];
            })->values()->toArray();

            return [
                'teacher_id'              => $teacher->id,
                'teacher_name'            => $teacher->name ?? 'غير محدد',
                'halaka_name'             => $teacherHalaka?->name ?? 'بدون حلقة',
                'students_count'          => $teacherHalaka?->students_count ?? 0,
                'periods'                 => $teacherPeriods,
                'missing_tasks'           => $this->getMissingTasks($task),
                'avg_delay_hours'         => $this->calcAvgDelayHours($teacher->id),
                'total_delay_hours'       => $this->sumDelayHours($teacher->id),
                'daily_report_copy_count' => $dailyReportCopyCount,
                'missed_report_copies'    => $missedReportCopies,
                'total_active_days'       => $totalActiveDays,
                'late_days_count'         => $lateDaysCount,
                'absence_days_count'      => $absences['absence_count'],
                'absences'                => $absences,
            ];
        })->toArray();
    }

    /**
     * إحصائيات الفترات المرنة للدورة اليوم.
     */
    private function buildPeriodsStats(int $daoraId): array
    {
        $today = Carbon::now()->toDateString();
        $periods = \App\Models\DaoraPeriod::where('daora_id', $daoraId)
            ->where('is_active', true)
            ->withCount('teachers')
            ->get();

        return $periods->map(function ($period) use ($today) {
            $tasks = DB::table('teacher_daily_tasks')
                ->where('daora_period_id', $period->id)
                ->where('task_date', $today)
                ->get();

            $copiedCount = $tasks->where('report_copied', true)->count();
            $lateCount   = $tasks->where('late_1_hour', true)->count();

            $avgDelay = DB::table('teacher_daily_tasks')
                ->where('daora_period_id', $period->id)
                ->whereNotNull('delay_hours')
                ->where('delay_hours', '>', 0)
                ->avg('delay_hours');

            return [
                'id'              => $period->id,
                'name'            => $period->period_name,
                'start_time'      => $period->start_time,
                'end_time'        => $period->end_time,
                'days_of_week'    => $period->days_of_week,
                'teachers_count'  => $period->teachers_count,
                'copied_count'    => $copiedCount,
                'pending_count'   => max(0, $period->teachers_count - $copiedCount),
                'late_count'      => $lateCount,
                'avg_delay_hours' => round($avgDelay ?? 0, 1),
            ];
        })->values()->toArray();
    }

    /**
     * المهام الناقصة لأستاذ اليوم.
     */
    private function getMissingTasks(?\App\Models\TeacherDailyTask $task): array
    {
        $missing = [];
        if (!$task || !$task->attendance_taken) {
            $missing[] = 'الغياب';
        }
        if (!$task || !$task->quran_tasks_taken) {
            $missing[] = 'الإنجازات';
        }
        if (!$task || !$task->report_copied) {
            $missing[] = 'نسخ التقرير';
        }

        return $missing;
    }

    // ========================================================================
    //  دوال حساب الغياب
    // ========================================================================

    private function calcAbsenceData(array $userIds): array
    {
        if (empty($userIds)) {
            return ['total_absence_days' => 0, 'students_absent_count' => 0];
        }

        return [
            'total_absence_days'    => Student::whereIn('user_id', $userIds)->sum('missing_days'),
            'students_absent_count' => Student::whereIn('user_id', $userIds)->where('missing_days', '>', 0)->count(),
        ];
    }

    private function getAbsentStudentsWithReasons(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return DB::table('absence_records')
            ->join('users', 'absence_records.user_id', '=', 'users.id')
            ->whereIn('absence_records.user_id', $userIds)
            ->select(
                'absence_records.user_id',
                'users.name as student_name',
                DB::raw("GROUP_CONCAT(COALESCE(absence_records.reason, '') SEPARATOR '_') as reasons"),
                DB::raw('COUNT(*) as absence_count'),
                DB::raw("SUM(CASE WHEN absence_records.reason IS NULL OR absence_records.reason = '' THEN 1 ELSE 0 END) as unjustified_count"),
            )
            ->groupBy('absence_records.user_id', 'users.name')
            ->get()
            ->map(fn ($row) => [
                'student_id'        => $row->user_id,
                'student_name'      => $row->student_name,
                'reasons'           => $row->reasons,
                'absence_count'     => $row->absence_count,
                'unjustified_count' => $row->unjustified_count,
                'justified'         => $row->unjustified_count == 0,
            ])
            ->toArray();
    }

    private function countUnjustifiedAbsences(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return DB::table('absence_records')
            ->whereIn('user_id', $userIds)
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', ''))
            ->distinct('user_id')
            ->count('user_id');
    }

    // ========================================================================
    //  دوال حساب الواجبات
    // ========================================================================

    private function calcHomeworkCompliance(array $userIds): float
    {
        if (empty($userIds)) {
            return 0;
        }

        $reports        = DB::table('reports')->whereIn('user_id', $userIds)->get();
        $totalAssigned  = 0;
        $totalCompleted = 0;

        foreach ($reports as $report) {
            $this->sumRepetitions($report->ended_quraan_this_course, self::QURAN_TYPES, $totalCompleted, $totalAssigned);
            $this->sumRepetitions($report->ended_hadith_this_course, self::HADITH_TYPES, $totalCompleted, $totalAssigned);
        }

        return $totalAssigned > 0 ? round(($totalCompleted / $totalAssigned) * 100, 1) : 0;
    }

    private function getStudentsWithoutHomework(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $students = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('latests', 'students.latest_id', '=', 'latests.id')
            ->whereIn('students.user_id', $userIds)
            ->select('students.user_id', 'users.name as student_name', 'latests.q_homework')
            ->get();

        $result = [];
        foreach ($students as $student) {
            if (!$this->hasHomeworkAssigned($student->q_homework)) {
                $result[] = [
                    'student_id'   => $student->user_id,
                    'student_name' => $student->student_name,
                    'reason'       => '',
                ];
            }
        }

        return $result;
    }

    /**
     * هل يوجد واجبات مُسندة لهذا الطالب؟
     */
    private function hasHomeworkAssigned(?string $qHomework): bool
    {
        if (!$qHomework) {
            return false;
        }

        $data = json_decode($qHomework, true);
        if (!is_array($data)) {
            return false;
        }

        return (count($data['ghaiban'] ?? []) + count($data['nazaran'] ?? []) + count($data['sibr_juz'] ?? [])) > 0;
    }

    // ========================================================================
    //  دوال حساب الأهداف (مشتركة بين الطلاب والمشرفين)
    // ========================================================================

    /**
     * عدد المستخدمين بدون أهداف.
     */
    private function countWithoutGoals(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $withGoals = StudentGoal::whereIn('user_id', $userIds)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        return count(array_diff($userIds, $withGoals));
    }

    /**
     * عدد المستخدمين الذين لديهم أهداف ولم يُنجزوا أياً منها.
     */
    private function countWithUnachievedGoals(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $withGoals = StudentGoal::whereIn('user_id', $userIds)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        $withAchieved = StudentGoal::whereIn('user_id', $userIds)
            ->where('status', 'achieved')
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        return count(array_diff($withGoals, $withAchieved));
    }

    /**
     * عدد الأهداف حسب الحالة (أو الكل إذا لم تُحدد).
     */
    private function countGoalsByStatus(array $userIds, ?string $status = null): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $query = StudentGoal::whereIn('user_id', $userIds);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    // ========================================================================
    //  دوال حساب القرآن والحديث (موحّدة)
    // ========================================================================

    /**
     * عدد الطلاب الذين لديهم واجب/إنجاز لم ينجزوه (مرات الإعادة > 0 والنجاح = 0).
     *
     * @param string $column  عمود الإنجاز (ended_quraan_this_course أو ended_hadith_this_course)
     * @param array  $types   أنواع التسميع
     */
    private function countNoCompletion(array $userIds, string $column, array $types): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $reports = DB::table('reports')->whereIn('user_id', $userIds)->get();
        $count   = 0;

        foreach ($reports as $report) {
            $data = json_decode($report->$column, true);
            if (!is_array($data)) {
                continue;
            }

            $hasIncomplete = false;
            foreach ($types as $type) {
                foreach (($data[$type] ?? []) as $item) {
                    $s = (int) ($item['success_repetitions'] ?? 0);
                    $f = (int) ($item['failed_repetitions'] ?? 0);
                    if ($f > 0 && $s === 0) {
                        $hasIncomplete = true;
                        break 2;
                    }
                }
            }

            if ($hasIncomplete) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * إجمالي مرات الإعادة (الفشل) في القرآن أو الحديث لكل الطلاب المحددين.
     */
    private function countFailedRepetitions(array $userIds, string $column, array $types): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $reports = DB::table('reports')->whereIn('user_id', $userIds)->get();
        $total   = 0;

        foreach ($reports as $report) {
            $data = json_decode($report->$column, true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($types as $type) {
                foreach (($data[$type] ?? []) as $item) {
                    $total += (int) ($item['failed_repetitions'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * إجمالي مرات النجاح في القرآن أو الحديث لكل الطلاب المحددين.
     */
    private function countSuccessRepetitions(array $userIds, string $column, array $types): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $reports = DB::table('reports')->whereIn('user_id', $userIds)->get();
        $total   = 0;

        foreach ($reports as $report) {
            $data = json_decode($report->$column, true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($types as $type) {
                foreach (($data[$type] ?? []) as $item) {
                    $total += (int) ($item['success_repetitions'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * عدد الطلاب الذين لم يُسنَد لهم واجب (قرآن أو حديث).
     *
     * @param string $homeworkColumn  عمود الواجب في جدول latests
     */
    private function countNoAssignment(array $userIds, string $homeworkColumn): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return DB::table('students')
            ->whereIn('user_id', $userIds)
            ->where(fn ($q) => $q
                ->whereNull('latest_id')
                ->orWhereNotExists(fn ($sub) => $sub
                    ->select(DB::raw(1))
                    ->from('latests')
                    ->whereColumn('latests.id', 'students.latest_id')
                    ->whereNotNull($homeworkColumn)
                )
            )
            ->count();
    }

    /**
     * تجميع نتائج التكرار (ناجح / فاشل) من JSON المخزّن.
     *
     * يُستخدم لكل من حساب الالتزام بالواجبات وحساب عدم الإنجاز.
     */
    private function sumRepetitions(?string $jsonData, array $types, int &$success, int &$total): void
    {
        if (!$jsonData) {
            return;
        }

        $data = json_decode($jsonData, true);
        if (!is_array($data)) {
            return;
        }

        foreach ($types as $type) {
            foreach (($data[$type] ?? []) as $item) {
                $s = (int) ($item['success_repetitions'] ?? 0);
                $f = (int) ($item['failed_repetitions'] ?? 0);
                $success += $s;
                $total   += ($s + $f);
            }
        }
    }

    private function getQuranAchievementSummary(array $userIds): array
    {
        if (empty($userIds)) {
            return ['total_entries' => 0, 'students_with_progress' => 0, 'students_without_progress' => 0];
        }

        $reports              = Report::whereIn('user_id', $userIds)->get();
        $totalEntries         = 0;
        $studentsWithProgress = 0;

        foreach ($reports as $r) {
            $quraan = json_decode($r->ended_quraan_this_course, true);

            if (!$this->isQuranReportEmpty($quraan)) {
                $studentsWithProgress++;
                if (is_array($quraan)) {
                    $totalEntries += isset($quraan['ghaiban'])
                        ? count($quraan['ghaiban'] ?? []) + count($quraan['nazaran'] ?? []) + count($quraan['sibr_juz'] ?? [])
                        : count($quraan);
                }
            }
        }

        return [
            'total_entries'            => $totalEntries,
            'students_with_progress'   => $studentsWithProgress,
            'students_without_progress' => count($userIds) - $studentsWithProgress,
        ];
    }

    // ========================================================================
    //  دوال حساب السبر والاختبارات (موحّدة)
    // ========================================================================

    /**
     * عدد المشاركين في الاختبارات.
     *
     * @param bool $aukaf       هل هو سبر أوقاف أم ترشيحي
     * @param bool $passedOnly  فقط الناجحون (rating >= 80)
     */
    private function countTestParticipants(array $userIds, ?int $daoraId, bool $aukaf, bool $passedOnly = false): int
    {
        if (empty($userIds) || !$daoraId) {
            return 0;
        }

        $query = DB::table('user_tests')
            ->join('tests', 'user_tests.test_id', '=', 'tests.id')
            ->whereIn('user_tests.user_id', $userIds)
            ->where('tests.daora_id', $daoraId)
            ->where('tests.aukaf', $aukaf ? 1 : 0);

        if ($passedOnly) {
            $query->where('user_tests.rating', '>=', 80);
        }

        return $query->distinct('user_tests.user_id')->count('user_tests.user_id');
    }

    // ========================================================================
    //  دوال حساب السلوك (مشتركة بين الطلاب والمشرفين)
    // ========================================================================

    private function countWithoutBehavior(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $withBehavior = UserBehavior::whereIn('user_id', $userIds)
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        return count(array_diff($userIds, $withBehavior));
    }

    private function countBehaviorOlderThanYear(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return UserBehavior::whereIn('user_id', $userIds)
            ->where('updated_at', '<', Carbon::now()->subYear())
            ->distinct('user_id')
            ->count('user_id');
    }

    // ========================================================================
    //  دوال المهام اليومية
    // ========================================================================

    /**
     * عد الأيام الفريدة التي تحقق فيها شرط معين في المهام اليومية.
     *
     * كل يوم يُحسب مرة واحدة فقط بغض النظر عن عدد الفترات.
     * مثلاً: إذا نسخ الأستاذ التقرير في فترتين في نفس اليوم، يُحسب يوم واحد فقط.
     */
    private function countDailyTasksWhere(array $teacherIds, string $column, bool $value): int
    {
        if (empty($teacherIds)) {
            return 0;
        }

        return DB::table('teacher_daily_tasks')
            ->whereIn('teacher_id', $teacherIds)
            ->where($column, $value)
            ->distinct()
            ->count(DB::raw('CONCAT(teacher_id, task_date)'));
    }

    private function countAttendanceRecords(int $teacherId): int
    {
        return DB::table('absence_records')
            ->where('teacher_id', $teacherId)
            ->count();
    }

    /**
     * متوسط ساعات التأخير لأستاذ واحد.
     *
     * يُحسب بقسمة مجموع ساعات التأخير الفعلية (من حقل delay_hours)
     * على عدد الأيام التي تأخر فيها فعلاً.
     */
    private function calcAvgDelayHours(int $teacherId): float
    {
        $result = DB::table('teacher_daily_tasks')
            ->where('teacher_id', $teacherId)
            ->whereNotNull('delay_hours')
            ->where('delay_hours', '>', 0)
            ->selectRaw('SUM(delay_hours) as total_delay, COUNT(DISTINCT task_date) as late_days')
            ->first();

        if (!$result || $result->late_days == 0) {
            return 0;
        }

        return round($result->total_delay / $result->late_days, 1);
    }

    /**
     * متوسط ساعات التأخير لمجموعة أساتذة.
     */
    private function calcAvgDelayHoursForGroup(array $teacherIds): float
    {
        if (empty($teacherIds)) {
            return 0;
        }

        $result = DB::table('teacher_daily_tasks')
            ->whereIn('teacher_id', $teacherIds)
            ->whereNotNull('delay_hours')
            ->where('delay_hours', '>', 0)
            ->selectRaw('SUM(delay_hours) as total_delay, COUNT(DISTINCT CONCAT(teacher_id, task_date)) as late_days')
            ->first();

        if (!$result || $result->late_days == 0) {
            return 0;
        }

        return round($result->total_delay / $result->late_days, 1);
    }

    /**
     * مجموع ساعات التأخير الفعلية لأستاذ واحد.
     */
    private function sumDelayHours(int $teacherId): float
    {
        return (float) DB::table('teacher_daily_tasks')
            ->where('teacher_id', $teacherId)
            ->whereNotNull('delay_hours')
            ->sum('delay_hours');
    }

    /**
     * غيابات أستاذ واحد.
     */
    private function getTeacherAbsences(int $teacherId): array
    {
        $absences = DB::table('absence_records')
            ->where('user_id', $teacherId)
            ->select(
                DB::raw("GROUP_CONCAT(COALESCE(reason, '') SEPARATOR '_') as reasons"),
                DB::raw('COUNT(*) as absence_count'),
                DB::raw("SUM(CASE WHEN reason IS NULL OR reason = '' THEN 1 ELSE 0 END) as unjustified_count"),
            )
            ->first();

        return [
            'absence_count'     => (int) ($absences->absence_count ?? 0),
            'unjustified_count' => (int) ($absences->unjustified_count ?? 0),
            'reasons'           => $absences->reasons ?? '',
        ];
    }

    // ========================================================================
    //  دوال مساعدة
    // ========================================================================

    private function isQuranReportEmpty($quraan): bool
    {
        if (empty($quraan) || !is_array($quraan)) {
            return true;
        }

        if (isset($quraan['ghaiban'])) {
            return empty($quraan['ghaiban'])
                && empty($quraan['nazaran'] ?? [])
                && empty($quraan['sibr_juz'] ?? []);
        }

        return empty($quraan);
    }

    private function emptyStudentStats(): array
    {
        return [
            'total_absence_days'              => 0,
            'students_absent_count'           => 0,
            'absent_students'                 => [],
            'unjustified_absences_count'      => 0,
            'avg_homework_compliance'         => 0,
            'students_without_homework'       => [],
            'students_without_goals'          => 0,
            'students_with_unachieved_goals'  => 0,
            'total_goals_set'                 => 0,
            'total_goals_achieved'            => 0,
            'goals_in_progress'               => 0,
            'quran_no_completion_count'       => 0,
            'quran_failed_repetitions_count'  => 0,
            'quran_success_repetitions_count' => 0,
            'quran_no_assignment_count'       => 0,
            'quran_achievement_summary'       => ['total_entries' => 0, 'students_with_progress' => 0],
            'hadith_no_completion_count'      => 0,
            'hadith_failed_repetitions_count' => 0,
            'hadith_success_repetitions_count'=> 0,
            'hadith_no_assignment_count'      => 0,
            'students_applied_for_test'       => 0,
            'students_passed_aukaf'           => 0,
            'students_without_behavior'       => 0,
            'students_behavior_not_updated_1year' => 0,
        ];
    }

    private function emptyMosqueStats(): array
    {
        return [
            'students_count' => 0,
            'halakas_count'  => 0,
        ]
            + $this->emptyStudentStats()
            + [
                'daily_report_copy_count'                 => 0,
                'avg_delay_hours'                         => 0,
                'supervisors_without_goals'               => 0,
                'supervisors_with_unachieved_goals'       => 0,
                'supervisors_total_goals'                 => 0,
                'supervisors_achieved_goals'              => 0,
                'supervisors_goals_in_progress'           => 0,
                'supervisors_without_behavior'            => 0,
                'supervisors_behavior_not_updated_1year'  => 0,
                'teachers_list'                           => [],
                'periods_stats'                           => [],
                'attendance_record_count'                 => 0,
            ];
    }
}
