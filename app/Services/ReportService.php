<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\point;
use App\Models\report;
use App\Models\student;
use Illuminate\Support\Facades\Log;

class ReportService
{
    public function addLatestQuraan($data, $user_id, $teacher_id)
    {
        $report = report::firstOrCreate(
            ['user_id' => $user_id],
            ['teacher_id' => $teacher_id]
        );

        $report_quraan_data = $report->ended_quraan_this_course
            ? json_decode($report->ended_quraan_this_course)
            : null;

        if (is_object($report_quraan_data) && isset($report_quraan_data->ghaiban)) {
            $report_quraan = $report_quraan_data;
        } else {
            $report_quraan = (object) [
                'ghaiban' => [],
                'nazaran' => []
            ];
        }

        foreach ($data as $ended_sora) {
            $type = $ended_sora->type ?? 'ghaiban';

            if (!in_array($type, ['ghaiban', 'nazaran'])) {
                continue;
            }

            $skip = false;

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
                $ended_sora->type = $type; 

                if ($ended_sora->mark >= 80) {
                    $points = point::firstOrCreate(['user_id' => $user_id]);
                    $points->update([
                        'q_points' => $points->q_points + $ended_sora->point
                    ]);
                    student::where('user_id', $user_id)->update([
                        'point_id' => $points->id
                    ]);
                }
                $report_quraan->{$type}[] = $ended_sora;
            }
        }

        report::updateOrCreate(
            ['user_id' => $user_id, 'teacher_id' => $teacher_id],
            ['ended_quraan_this_course' => json_encode($report_quraan)]
        );

        return true; 
    }

    public function removeLatestQuraan($sora_data, $item_was_successful, $points_to_remove, $user_id, $mark, $sora_index, $type, $report_quraan, $report, $quraan_item)
    {
        try {
            if ($sora_data) {
                if ($item_was_successful) {
                    $sora_data->success_repetitions = ($sora_data->success_repetitions ?? 1) - 1;
                } else {
                    $sora_data->failed_repetitions = ($sora_data->failed_repetitions ?? 1) - 1;
                }

                if ($item_was_successful && $points_to_remove > 0) {
                    $points = point::where('user_id', $user_id)->first();
                    if ($points) {
                        $new_points = $points->q_points - $points_to_remove;
                        $points->update([
                            'q_points' => $new_points < 0 ? 0 : $new_points
                        ]);
                    }
                }

                if ($item_was_successful) {
                    $sora_data->point = ($sora_data->point ?? 0) - $points_to_remove;
                    if ($sora_data->point < 0) $sora_data->point = 0;

                    if ($sora_data->success_repetitions > 0) {
                        $sora_data->mark = ($sora_data->mark * 2) - $mark;
                    } else {
                        $sora_data->mark = 0;
                    }
                }

                if ($sora_data->success_repetitions <= 0 && $sora_data->failed_repetitions <= 0) {
                    unset($report_quraan->{$type}[$sora_index]);
                    $report_quraan->{$type} = array_values($report_quraan->{$type});
                } else {
                    $report_quraan->{$type}[$sora_index] = $sora_data;
                }

                $report->update([
                    'ended_quraan_this_course' => json_encode($report_quraan)
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('خطأ في remove_latest_quraan: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'item' => $quraan_item,
                'trace' => $th->getTraceAsString()
            ]);
        }
    }

    public function removeLatestHadith($hadith_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_hadith_list, $report, $hadith_item) 
    {
        try {
            if ($item_was_successful) {
                $hadith_data->success_repetitions = ($hadith_data->success_repetitions ?? 1) - 1;
            } else {
                $hadith_data->failed_repetitions = ($hadith_data->failed_repetitions ?? 1) - 1;
            }

            if ($item_was_successful && $points_to_remove > 0) {
                $points = point::where('user_id', $user_id)->first();
                if ($points) {
                    $new_points = $points->h_points - $points_to_remove; 
                    $points->update([
                        'h_points' => $new_points < 0 ? 0 : $new_points 
                    ]);
                }
            }

            if ($item_was_successful) {
                $hadith_data->point = ($hadith_data->point ?? 0) - $points_to_remove;
                if ($hadith_data->point < 0) $hadith_data->point = 0;

                if ($hadith_data->success_repetitions > 0) {
                    $hadith_data->mark = ($hadith_data->mark * 2) - $mark;
                } else {
                    $hadith_data->mark = 0;
                }
            }

            if ($hadith_data->success_repetitions <= 0 && $hadith_data->failed_repetitions <= 0) {
                unset($report_hadith_list[$item_index]);
                $report_hadith_list = array_values($report_hadith_list);
            } else {
                $report_hadith_list[$item_index] = $hadith_data;
            }

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

    public function removeLatestActivity($activity_data, $item_was_successful, $points_to_remove, $user_id, $mark, $item_index, $report_activity_list, $report, $activity_item) 
    {
        try {
            if ($item_was_successful) {
                $activity_data->number_of_repetitions = ($activity_data->number_of_repetitions ?? 1) - 1;
            } else {
                $activity_data->failures = ($activity_data->failures ?? 1) - 1;
            }

            if ($item_was_successful && $points_to_remove > 0) {
                $points = point::where('user_id', $user_id)->first();
                if ($points) {
                    $new_points = $points->a_points - $points_to_remove;
                    $points->update([
                        'a_points' => $new_points < 0 ? 0 : $new_points
                    ]);
                }
            }

            if ($item_was_successful) {
                $activity_data->point = ($activity_data->point ?? 0) - $points_to_remove;
                if ($activity_data->point < 0) $activity_data->point = 0;

                if ($activity_data->number_of_repetitions > 0) {
                    $activity_data->mark = ($activity_data->mark * 2) - $mark;
                } else {
                    $activity_data->mark = 0;
                }
            }

            if ($activity_data->number_of_repetitions <= 0 && $activity_data->failures <= 0) {
                unset($report_activity_list[$item_index]);
                $report_activity_list = array_values($report_activity_list);
            } else {
                $report_activity_list[$item_index] = $activity_data;
            }

            $report->update([
                'activities_this_course' => json_encode($report_activity_list)
            ]);
            
            return null; 

        } catch (\Throwable $th) {
            Log::error('خطأ في remove_latest_activity: ' . $th->getMessage(), [
                'user_id' => $user_id,
                'item' => $activity_item,
                'trace' => $th->getTraceAsString()
            ]);
            return ["error" => $th->getMessage()]; 
        }
    }

    public function removeLatestNote($note_item, $user_id)
    {
        try {
            $item_to_delete = (object)$note_item;
            $points_to_restore = $item_to_delete->lost_point ?? 0;

            $report = Report::where('user_id', $user_id)->first();

            if (!$report || !$report->notes) {
                return;
            }

            $notes_list = json_decode($report->notes, true); 
            
            if (!is_array($notes_list)) {
                Log::warning("بنية تقرير الملاحظات غير صالحة للمستخدم: $user_id");
                return;
            }

            $item_found_and_removed = false;

            foreach ($notes_list as $index => $note) {
                if (!is_array($note) || !isset($note['note'], $note['lost_point'])) {
                    continue; 
                }

                if ($note['note'] == $item_to_delete->note && $note['lost_point'] == $points_to_restore) {
                    unset($notes_list[$index]);
                    $item_found_and_removed = true;
                    break;
                }
            }

            if ($item_found_and_removed) {
                $report->update([
                    'notes' => json_encode(array_values($notes_list))
                ]);

                if ($points_to_restore > 0) {
                    $points = point::where('user_id', $user_id)->first();
                    if ($points) {
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

    public function addLatestHadith($data, $user_id, $teacher_id)
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

    public function addLatestActivity($data, $user_id, $teacher_id)
    {
        $report = report::firstOrCreate(
            ['user_id' => $user_id],
            ['teacher_id' => $teacher_id]
        );

        $report_activity = $report->activities_this_course
            ? json_decode($report->activities_this_course, true)
            : [];

        foreach ($data as $new_activity) {
            $new_activity = (array) $new_activity; 
            $found = false;

            foreach ($report_activity as &$activity) {
                if ($new_activity['name'] === $activity['name']) {
                    $found = true;

                    if ($new_activity['mark'] >= 80) {
                        $activity['number_of_repetitions'] = ($activity['number_of_repetitions'] ?? 0) + 1;
                        $activity['mark'] = ($activity['mark'] + $new_activity['mark']) / 2;
                        $activity['point'] = ($activity['point'] ?? 0) + $new_activity['point'];

                        $points = point::firstOrCreate(['user_id' => $user_id]);
                        $points->update([
                            'a_points' => $points->a_points + $new_activity['point']
                        ]);
                    } else {
                        $activity['failures'] = ($activity['failures'] ?? 0) + 1;
                    }
                    break;
                }
            }

            if (!$found) {
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

        $report->update([
            'activities_this_course' => json_encode($report_activity)
        ]);
        return true;
    }

    public function addLatestNote($oneNote, $lost_point, $user_id, $teacher_id)
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

    public function getReportsByDaora($daora_id)
    {
        $reports = DB::table('reports')
            ->join('users as student_user', 'reports.user_id', '=', 'student_user.id')
            ->join('users as teacher_user', 'reports.teacher_id', '=', 'teacher_user.id')
            ->where('student_user.daora_id', $daora_id)
            ->select(
                'reports.*', 
                'student_user.name as user_name', 
                'teacher_user.name as teacher_name' 
            )
            ->get();

        foreach ($reports as $report) {
            $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
            $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course);
            $report->activities_this_course = json_decode($report->activities_this_course);
            $report->notes = json_decode($report->notes);
        }

        return $reports;
    }
}
