<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ArchiveController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    public function archiveDaora(Request $request)
    {
        $daoraId = $request->input('daora_id');
        if (! $daoraId) {
            return response()->json(['success' => false, 'message' => 'Missing daora_id'], 400);
        }

        $daora = DB::table('daoras')->where('id', $daoraId)->first();
        if (! $daora) {
            return response()->json(['success' => false, 'message' => 'Daora not found'], 404);
        }

        $now = Carbon::now()->toDateString();

        DB::beginTransaction();
        try {
            // == Archive Students ==
            $students = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('jobs', 'users.job_id', '=', 'jobs.id')
                ->leftJoin('areas', 'users.area_id', '=', 'areas.id')
                ->where('users.daora_id', $daoraId)
                ->select(
                    'users.id as user_id',
                    'users.name as student_name',
                    'users.family_status',
                    'jobs.name as job',
                    'areas.name as region',
                    'students.ended_quraan_in_aukaf',
                    'students.latest_id',
                    'students.missing_days'
                )
                ->get();

            foreach ($students as $student) {
                // Fetch behaviors
                $behavior = DB::table('user_behaviors')->where('user_id', $student->user_id)->first();
                // Fetch goals
                $goals = DB::table('student_goals')->where('user_id', $student->user_id)->get();
                $goal_cat = $goals->pluck('category')->implode(' | ');
                $goal_txt = $goals->pluck('goal_text')->implode(' | ');
                $goal_stat = $goals->pluck('status')->implode(' | ');
                $goal_com = $goals->pluck('comments')->implode(' | ');

                // Fetch Teachers
                $teachers1 = DB::table('student_teacher')
                    ->join('users', 'student_teacher.teacher_id', '=', 'users.id')
                    ->where('student_teacher.student_id', $student->user_id)
                    ->pluck('users.name')->toArray();

                $teachers2 = DB::table('reports')
                    ->join('users', 'reports.teacher_id', '=', 'users.id')
                    ->where('reports.user_id', $student->user_id)
                    ->pluck('users.name')->toArray();

                $teachersNames = implode(' - ', array_unique(array_merge($teachers1, $teachers2)));

                $report = DB::table('reports')
                    ->where('user_id', $student->user_id)
                    ->orderByDesc('created_at')
                    ->first();

                // Points
                $pointsRow = DB::table('points')->where('user_id', $student->user_id)->first();
                $totalPoints = $pointsRow ? ($pointsRow->q_points + $pointsRow->h_points + $pointsRow->a_points + $pointsRow->l_points) : 0;

                // Test Awqaf
                $test = DB::table('user_tests')
                    ->join('tests', 'user_tests.test_id', '=', 'tests.id')
                    ->where('user_tests.user_id', $student->user_id)
                    ->orderByDesc('user_tests.id')
                    ->first();

                // ended_quraan_in_aukaf mapping array to string
                $qAukaf = '';
                if ($student->ended_quraan_in_aukaf) {
                    $decoded = json_decode($student->ended_quraan_in_aukaf, true);
                    $qAukaf = is_array($decoded) ? implode(', ', $decoded) : $decoded;
                }

                DB::table('archived_students')->insert([
                    'user_id' => $student->user_id,
                    'student_name' => $student->student_name,
                    'daora_id' => $daoraId,
                    'daora_name' => $daora->name ?? null,
                    'archived_at' => $now,
                    'family_status' => $student->family_status,
                    'job' => $student->job,
                    'region' => $student->region,
                    'teachers_names' => $teachersNames,

                    'traits' => $behavior?->traits ?? '',
                    'habits' => $behavior?->habits ?? '',
                    'discipline' => $behavior?->discipline ?? '',
                    'morals' => $behavior?->morals ?? '',
                    'skills' => $behavior?->skills ?? '',
                    'positive_actions' => $behavior?->positive_actions ?? '',
                    'negative_actions' => $behavior?->negative_actions ?? '',

                    'goal_category' => $goal_cat,
                    'goal_text' => $goal_txt,
                    'goal_status' => $goal_stat,
                    'goal_comments' => $goal_com,

                    'ended_quraan_in_aukaf' => $qAukaf,
                    'quraan_rating' => $test?->rating ?? '',
                    'quraan_notes' => $test?->notes ?? '',

                    'ended_quraan_this_course' => $report?->ended_quraan_this_course ?? $report?->ended_quraan ?? '', // fallback mapping
                    'ended_hadith_this_course' => $report?->ended_hadith_this_course ?? $report?->ended_hadith ?? '',
                    'activities_this_course' => $report?->activities_this_course ?? $report?->activities ?? '',
                    'report_notes' => $report?->notes ?? '',
                    'missing_days' => $student->missing_days ?? 0,

                    'points' => $totalPoints,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Reset logic for this student
                DB::table('points')->where('user_id', $student->user_id)->update([
                    'q_points' => 0, 'h_points' => 0, 'a_points' => 0, 'l_points' => 0,
                ]);
                DB::table('students')->where('user_id', $student->user_id)->update([
                    'missing_days' => 0,
                ]);
                DB::table('reports')->where('user_id', $student->user_id)->delete();
                DB::table('notifications')->where('notifiable_id', $student->user_id)->delete();
                if ($student->latest_id) {
                    DB::table('latests')->where('id', $student->latest_id)->delete();
                }
            }

            // == Archive Teachers ==
            $teachers = DB::table('users as t')
                ->leftJoin('jobs', 't.job_id', '=', 'jobs.id')
                ->leftJoin('areas', 't.area_id', '=', 'areas.id')
                ->where('t.daora_id', $daoraId)
                ->where('t.privilege', 3)
                ->select('t.id as user_id', 't.name as teacher_name', 't.family_status', 'jobs.name as job', 'areas.name as region')
                ->distinct()
                ->get();

            foreach ($teachers as $teacher) {
                $behavior = DB::table('user_behaviors')->where('user_id', $teacher->user_id)->first();
                $goals = DB::table('student_goals')->where('user_id', $teacher->user_id)->get();

                DB::table('archived_teachers')->insert([
                    'user_id' => $teacher->user_id,
                    'teacher_name' => $teacher->teacher_name,
                    'daora_id' => $daoraId,
                    'daora_name' => $daora->name ?? null,
                    'archived_at' => $now,
                    'family_status' => $teacher->family_status,
                    'job' => $teacher->job,
                    'region' => $teacher->region,

                    'traits' => $behavior?->traits ?? '',
                    'habits' => $behavior?->habits ?? '',
                    'discipline' => $behavior?->discipline ?? '',
                    'morals' => $behavior?->morals ?? '',
                    'skills' => $behavior?->skills ?? '',
                    'positive_actions' => $behavior?->positive_actions ?? '',
                    'negative_actions' => $behavior?->negative_actions ?? '',

                    'goal_category' => $goals->pluck('category')->implode(' | '),
                    'goal_text' => $goals->pluck('goal_text')->implode(' | '),
                    'goal_status' => $goals->pluck('status')->implode(' | '),
                    'goal_comments' => $goals->pluck('comments')->implode(' | '),

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'تمت الأرشفة بنجاح وتصفير الإحصائيات']);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    public function getArchivedDates($daoraId)
    {
        $dates = DB::table('archived_students')
            ->where('daora_id', $daoraId)
            ->select('archived_at')
            ->distinct()
            ->orderByDesc('archived_at')
            ->pluck('archived_at');

        return response()->json(['success' => true, 'dates' => $dates]);
    }

    public function getArchivedRecordsByDate($daoraId, $date)
    {
        $students = DB::table('archived_students')
            ->where('daora_id', $daoraId)
            ->where('archived_at', $date)
            ->get();

        $teachers = DB::table('archived_teachers')
            ->where('daora_id', $daoraId)
            ->where('archived_at', $date)
            ->get();

        return response()->json(['success' => true, 'students' => $students, 'teachers' => $teachers]);
    }

    public function searchArchives($name)
    {
        $students = DB::table('archived_students')
            ->where('student_name', 'LIKE', '%'.$name.'%')
            ->orderByDesc('archived_at')
            ->get();

        $teachers = DB::table('archived_teachers')
            ->where('teacher_name', 'LIKE', '%'.$name.'%')
            ->orderByDesc('archived_at')
            ->get();
        return response()->json(['success' => true, 'students' => $students, 'teachers' => $teachers]);
    }

  public function exportStudentsCSV($daoraId)
    {
        // زيادة وقت التنفيذ والذاكرة لتجنب أخطاء Timeout في حال كثرة البيانات
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // قائمة أسماء السور
        $surahNames = [
            'الفاتحة','البقرة','ال عمران','النساء','المائدة','الانعام','الاعراف','الانفال','التوبة','يونس',
            'هود','يوسف','الرعد','ابراهيم','الحجر','النحل','الاسراء','الكهف','مريم','طه',
            'الانبياء','الحج','المؤمنون','النور','الفرقان','الشعراء','النمل','القصص','العنكبوت','الروم',
            'لقمان','السجدة','الاحزاب','سبا','فاطر','يس','الصافات','ص','الزمر','غافر',
            'فصلت','الشورى','الزخرف','الدخان','الجاثية','الاحقاف','محمد','الفتح','الحجرات','ق',
            'الذاريات','الطور','النجم','القمر','الرحمن','الواقعة','الحديد','المجادلة','الحشر','الممتحنة',
            'الصف','الجمعة','المنافقون','التغابن','الطلاق','التحريم','الملك','القلم','الحاقة','المعارج',
            'نوح','الجن','المزمل','المدثر','القيامة','الانسان','المرسلات','النبأ','النازعات','عبس',
            'التكوير','الانفطار','المطففين','الانشقاق','البروج','الطارق','الاعلى','الغاشية','الفجر','البلد',
            'الشمس','الليل','الضحى','الشرح','التين','العلق','القدر','البينة','الزلزلة','العاديات',
            'القارعة','التكاثر','العصر','الهمزة','الفيل','قريش','الماعون','الكوثر','الكافرون','النصر',
            'المسد','الاخلاص','الفلق','الناس',
        ];

        // استعلام جلب الطلاب
        $sql = "
            SELECT 
                u.id,
                u.name,
                TIMESTAMPDIFF(YEAR, u.age, CURDATE()) AS age_years,
                areas.name AS area_name,
                u.skills,
                u.notes,
                s.id AS student_id,
                s.missing_days,
                s.ended_quraan_in_aukaf
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN areas ON u.area_id = areas.id
            WHERE u.daora_id = ?
        ";

        try {
            $rows = DB::select($sql, [$daoraId]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطأ في الاستعلام: ' . $e->getMessage()], 500);
        }

        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'لا توجد بيانات لهذه الدورة'], 404);
        }

        // --- إضافة جديدة: جلب جميع اختبارات الأوقاف لطلاب هذه الدورة وتجهيزها ---
        $awqafTests = DB::table('user_tests')
            ->join('tests', 'user_tests.test_id', '=', 'tests.id')
            ->where('tests.daora_id', $daoraId)
            ->where('tests.aukaf', 1) // حصراً سبور الأوقاف
            ->select('user_tests.user_id', 'user_tests.rating', 'user_tests.the_part_to_test_in')
            ->get();

        $studentAwqafTests = [];
        foreach ($awqafTests as $test) {
            $userId = $test->user_id;
            if (!isset($studentAwqafTests[$userId])) {
                $studentAwqafTests[$userId] = ['passed' => [], 'failed' => []];
            }
            
            // تحويل الجزء المسبور (the_part_to_test_in) إلى نص مقروء
            $testPart = json_decode($test->the_part_to_test_in, true);
            $testPartStr = is_array($testPart) ? implode(', ', $testPart) : $test->the_part_to_test_in;

            $passingGrade = 80; 

            if ($test->rating >= $passingGrade) {
                $studentAwqafTests[$userId]['passed'][] = "المقطع: $testPartStr (العلامة: {$test->rating})";
            } else {
                $studentAwqafTests[$userId]['failed'][] = "المقطع: $testPartStr (العلامة: {$test->rating})";
            }
        }
        // -----------------------------------------------------------------------

        // دالة مساعدة للقرآن
        $formatQuraan = function ($items) use ($surahNames) {
            if (empty($items) || !is_array($items)) return '';
            $parts = [];
            foreach ($items as $item) {
                $num = $item['num'] ?? null;
                $surah = ($num !== null && isset($surahNames[$num])) ? $surahNames[$num] : '؟؟';
                $from = $item['from'] ?? '?';
                $to = $item['to'] ?? '?';
                $mark = $item['mark'] ?? '?';
                $parts[] = "سورة {$surah} (من {$from} إلى {$to}) العلامة: {$mark}%";
            }
            return implode(' | ', $parts);
        };

        // --- إضافة جديدة: دالة مساعدة لفك تشفير وتنسيق الأحاديث ---
        $formatHadith = function ($items) {
            if (empty($items) || !is_array($items)) return '';
            $parts = [];
            foreach ($items as $item) {
                $num = $item['num'] ?? '?';
                $mark = $item['mark'] ?? '?';
                $type = isset($item['type']) ? ($item['type'] == 'ghaiban' ? 'غيباً' : 'نظراً') : '';
                $parts[] = "حديث رقم {$num} ($type) العلامة: {$mark}%";
            }
            return implode(' | ', $parts);
        };
        // ----------------------------------------------------------

        // بناء البيانات النهائية لكل طالب
        $csvData = [];
        foreach ($rows as $row) {
            // 1. جلب أسماء الأساتذة
            $teachers = DB::table('student_teacher')
                ->join('users as t', 'student_teacher.teacher_id', '=', 't.id')
                ->where('student_teacher.student_id', $row->student_id)
                ->select('t.name', 'student_teacher.started_at', 'student_teacher.ended_at')
                ->get();

            $teachersStr = $teachers->map(function ($t) {
                $from = $t->started_at ? date('Y-m-d', strtotime($t->started_at)) : 'غير محدد';
                $to = $t->ended_at ? date('Y-m-d', strtotime($t->ended_at)) : 'الآن';
                return "{$t->name} (من {$from} إلى {$to})";
            })->implode(' | ');

            // 2. جلب بيانات التقرير للطالب
            $report = DB::table('reports')
                ->where('user_id', $row->id)
                ->orderByDesc('created_at')
                ->first();

            $ghaibanStr = '';
            $nazaranStr = '';
            $hadithStr = ''; // متغير الأحاديث الجديد

            if ($report) {
                // القرآن
                if (!empty($report->ended_quraan_this_course)) {
                    $quraan = json_decode($report->ended_quraan_this_course, true);
                    if (is_array($quraan)) {
                        $ghaibanStr = $formatQuraan($quraan['ghaiban'] ?? []);
                        $nazaranStr = $formatQuraan($quraan['nazaran'] ?? []);
                    }
                }
                
                // الأحاديث
                if (!empty($report->ended_hadith_this_course)) {
                    $hadith = json_decode($report->ended_hadith_this_course, true);
                    $hadithStr = $formatHadith($hadith);
                }
            }

            // 3. معالجة المسبور في الأوقاف (القديم من جدول الطلاب)
            $aukafStr = '';
            if (!empty($row->ended_quraan_in_aukaf)) {
                $decoded = json_decode($row->ended_quraan_in_aukaf, true);
                $aukafStr = is_array($decoded) ? implode(', ', $decoded) : ($row->ended_quraan_in_aukaf ?? '');
            }

            // --- 4. استخراج سبور الأوقاف الخاصة بالطالب من المصفوفة التي جهزناها ---
            $passedAwqafStr = '';
            $failedAwqafStr = '';
            if (isset($studentAwqafTests[$row->id])) {
                $passedAwqafStr = implode(' | ', $studentAwqafTests[$row->id]['passed']);
                $failedAwqafStr = implode(' | ', $studentAwqafTests[$row->id]['failed']);
            }
            // ----------------------------------------------------------------------

            // تجهيز الصف الخاص بالطالب للـ Excel
            $csvData[] = [
                'رقم الطالب' => $row->id,
                'اسم الطالب' => $row->name,
                'العمر' => $row->age_years,
                'المنطقة' => $row->area_name,
                'المهارات' => $row->skills,
                'ملاحظات المستخدم' => $row->notes,
                'أيام الغياب' => $row->missing_days,
                'المسبور في الأوقاف' => $aukafStr,
                'أساتذة الطالب' => $teachersStr,
                'التسميع (غيباً)' => $ghaibanStr,
                'التسميع (نظراً)' => $nazaranStr,
                
                // الأعمدة الجديدة:
                'تسميع الأحاديث' => $hadithStr,
                'سبور الأوقاف (الناجح بها)' => $passedAwqafStr,
                'سبور الأوقاف (الراسب بها)' => $failedAwqafStr,
            ];
        }

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="students_daora_' . $daoraId . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $columns = array_keys($csvData[0]);

        $callback = function () use ($csvData, $columns) {
            $file = fopen('php://output', 'w');
            
            // إضافة BOM لتوافق اللغة العربية مع برامج Excel
            fputs($file, "\xEF\xBB\xBF");
            
            fputcsv($file, $columns);

            foreach ($csvData as $row) {
                fputcsv($file, array_values($row));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}