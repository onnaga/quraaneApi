<?php

namespace App\Http\Controllers;

use App\Models\latest;
use App\Models\point;
use App\Models\report;
use App\Models\student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{

    public function  add_latest_quraan ($data,$user_id,$teacher_id)
    {
        $skip = false;
        $report = report::firstOrCreate(['user_id'=>$user_id] ,['teacher_id'=>$teacher_id]);
        if($report->ended_quraan_this_course!=null){
            $report_quraan=json_decode($report->ended_quraan_this_course);
            foreach ($data as $ended_sora) {
                $skip =false;
            if($ended_sora->mark<80)
                continue;
            foreach ($report_quraan as $sora) {
                if($ended_sora->num == $sora->num){
                    $skip = true;
                    $sora->number_of_repetitions +=1;
                    if($ended_sora->to > $sora->to){
                        $sora->to = $ended_sora->to;
                        $sora->mark = ($sora->mark+$ended_sora->mark)/2;
                        $sora->point = $sora->point+$ended_sora->point;
                        ////points table created when the report created /////
                        $points = point::where('user_id',$user_id)->first();
                        point::where('user_id',$user_id)->update([
                        'q_points'=>$points->q_points +$ended_sora->point
                        ]);
                    }
                }
                }
                if ($skip == false) {
                    ////points table created when the report created /////
                    $points = point::where('user_id',$user_id)->first();
                    point::where('user_id',$user_id)->update([
                    'q_points'=>$points->q_points +$ended_sora->point
                    ]);
                    array_push($report_quraan,$ended_sora);
                }


            }
            report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->update([
            'ended_quraan_this_course'=>json_encode($report_quraan)
            ]);
            $new_report = report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->first();
            $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
            $report->ended_hadith_this_course =json_decode($report->ended_hadith_this_course);
            $new_report->ended_hadith_this_course =json_decode($new_report->ended_hadith_this_course);
            $new_report->ended_quraan_this_course =json_decode($new_report->ended_quraan_this_course);
            return ['old_report'=>$report,'new_report'=>$new_report];
        }
        else{
            $latest_quraan =[];
            foreach ($data as $ended_sora) {
            if($ended_sora->mark<80)
                continue;
                $ended_sora->number_of_repetitions =1;
                array_push($latest_quraan,$ended_sora);
                 ////create the points table if it isnt exist /////

                $points = point::firstOrCreate(['user_id'=>$user_id]);
                point::where('user_id',$user_id)->update([
                'q_points'=>$points->q_points +$ended_sora->point
                ]);
                student::where('user_id',$user_id)->update([
                    'point_id'=>$points->id
                ]);
            }
            $new_report=report::updateOrCreate(
                ['user_id'=>$user_id,'teacher_id'=>$teacher_id],
                ['ended_quraan_this_course'=>json_encode($latest_quraan)]
            );
            $new_report->ended_hadith_this_course =json_decode($new_report->ended_hadith_this_course);
            $new_report->ended_quraan_this_course =json_decode($new_report->ended_quraan_this_course);
            return ['old_report'=>null,'new_report'=>$new_report];
        }



    }



    public function  add_latest_hadith ($data,$user_id,$teacher_id)
    {
        $skip = false;
        $report = report::firstOrCreate(['user_id'=>$user_id] ,['teacher_id'=>$teacher_id]);
        if($report->ended_hadith_this_course!=null){

            $report_hadith=json_decode($report->ended_hadith_this_course);
            foreach ($data as $ended_hadith) {
                $skip =false;
            if($ended_hadith->mark<80)
                continue;
            foreach ($report_hadith as $hadith) {
                if($ended_hadith->num == $hadith->num){
                    $skip = true;
                    $hadith->number_of_repetitions +=1;
                    if($ended_hadith->to > $hadith->to){
                        $hadith->to = $ended_hadith->to;
                        $hadith->mark = ($hadith->mark+$ended_hadith->mark)/2;
                        $hadith->point = $hadith->point+$ended_hadith->point;
                        ////points table created when the report created /////
                        $points = point::where('user_id',$user_id)->first();
                        point::where('user_id',$user_id)->update([
                        'h_points'=>$points->h_points +$ended_hadith->point
                        ]);
                    }
                }
                }
                if ($skip == false) {
                    ////points table created when the report created /////
                    $points = point::where('user_id',$user_id)->first();
                    point::where('user_id',$user_id)->update([
                    'h_points'=>$points->h_points +$ended_hadith->point
                    ]);
                    array_push($report_hadith,$ended_hadith);
                }


            }
            report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->update([
            'ended_hadith_this_course'=>json_encode($report_hadith)
            ]);
            $new_report = report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->first();
            $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
            $report->ended_hadith_this_course =json_decode($report->ended_hadith_this_course);
            $new_report->ended_hadith_this_course =json_decode($new_report->ended_hadith_this_course);
            $new_report->ended_quraan_this_course =json_decode($new_report->ended_quraan_this_course);
            return ['old_report'=>$report,'new_report'=>$new_report];

}
        else{
            $latest_hadith =[];
            foreach ($data as $ended_hadith) {
            if($ended_hadith->mark<80)
                continue;
                $ended_hadith->number_of_repetitions =1;
                array_push($latest_hadith,$ended_hadith);
                 ////create the points table if it isnt exist /////

                $points = point::firstOrCreate(['user_id'=>$user_id]);
                point::where('user_id',$user_id)->update([
                'h_points'=>$points->h_points +$ended_hadith->point
                ]);
                student::where('user_id',$user_id)->update([
                    'point_id'=>$points->id
                ]);
            }
            $new_report=report::updateOrCreate(
                ['user_id'=>$user_id,'teacher_id'=>$teacher_id],
                ['ended_hadith_this_course'=>json_encode($latest_hadith)]
            );
            $new_report->ended_hadith_this_course =json_decode($new_report->ended_hadith_this_course);
            $new_report->ended_quraan_this_course =json_decode($new_report->ended_quraan_this_course);
            return ['old_report'=>null,'new_report'=>$new_report];
        }



    }




    public function  add_latest_activity ($data,$user_id,$teacher_id)
    {
        $skip = false;
        $report = report::firstOrCreate(['user_id'=>$user_id] ,['teacher_id'=>$teacher_id]);
        if($report->activitis_this_course!=null){

            $report_activity=json_decode($report->activitis_this_course);
            foreach ($data as $new_activity) {
                $skip =false;
            if($new_activity->mark<40)
                continue;
            foreach ($report_activity as $activity) {
                if($new_activity->name == $activity->name){
                    $skip = true;
                    $activity->number_of_repetitions +=1;
                    $activity->mark = ($activity->mark+$new_activity->mark)/2;
                    $activity->point = $activity->point+$new_activity->point;
                    ////points table created when the report created /////
                    $points = point::where('user_id',$user_id)->first();
                    point::where('user_id',$user_id)->update([
                    'a_points'=>$points->a_points +$new_activity->point
                    ]);

                }
                }
                if ($skip == false) {
                    ////points table created when the report created /////
                    $points = point::where('user_id',$user_id)->first();
                    point::where('user_id',$user_id)->update([
                    'a_points'=>$points->a_points +$new_activity->point
                    ]);
                    array_push($report_activity,$new_activity);
                }


            }
            report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->update([
            'activitis_this_course'=>json_encode($report_activity)
            ]);
            $new_report = report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->first();
            $report->activitis_this_course = json_decode($report->activitis_this_course);
            $new_report->activitis_this_course =json_decode($new_report->activitis_this_course);
            return ['old_report'=>$report,'new_report'=>$new_report];

}
        else{
            $latest_activitis =[];
            foreach ($data as $new_activity) {
            if($new_activity->mark<40)
                continue;
                $new_activity->number_of_repetitions =1;
                array_push($latest_activitis,$new_activity);
                 ////create the points table if it isnt exist /////

                $points = point::firstOrCreate(['user_id'=>$user_id]);
                point::where('user_id',$user_id)->update([
                'a_points'=>$points->a_points +$new_activity->point
                ]);
                student::where('user_id',$user_id)->update([
                    'point_id'=>$points->id
                ]);
            }
            $new_report=report::updateOrCreate(
                ['user_id'=>$user_id,'teacher_id'=>$teacher_id],
                ['activitis_this_course'=>json_encode($latest_activitis)]
            );
            $new_report->activitis_this_course =json_decode($new_report->activitis_this_course);
            return ['old_report'=>null,'new_report'=>$new_report];
        }



    }



    public function  add_latest_note ($oneNote,$lost_point,$user_id,$teacher_id)
    {

        $report = report::firstOrCreate(['user_id'=>$user_id] ,['teacher_id'=>$teacher_id]);
        $notes = [];
        if(json_decode($report->notes)!=null)
        $notes = json_decode($report->notes);
        array_push($notes,['note'=>$oneNote,'lost_point'=>$lost_point]);

        report::where([['user_id',$user_id],['teacher_id',$teacher_id]])->update([
        'notes'=>json_encode($notes),
        ]);
        return $notes;
    }
    public function  show_reports ()
    {
        $reports = report::all();

        foreach ($reports as $report) {
            $report->user_name = User::find($report->user_id)->name;
            $report->teacher_id = User::find($report->teacher_id)->name;
            $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course);
            $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course);
            $report->activitis_this_course = json_decode($report->activitis_this_course);
            $report->notes = json_decode($report->notes);
        }

            return response()->json($reports);
        }

   public function  show_user_reports (Request $request)
    {
        if(Auth::user()->privilege>1){
        if(!$request->user_id)
        return response()->json('user_id required');
        $report = report::where('user_id',$request->user_id)->first(['ended_quraan_this_course','ended_hadith_this_course','activitis_this_course','notes']);

        $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course );
        $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course );
        $report->activitis_this_course = json_decode($report->activitis_this_course );
        $report->notes = json_decode($report->notes );
            return response()->json($report);
        }
        else{
            $report = report::where('user_id',Auth::user()->id)->first(['ended_quraan_this_course','ended_hadith_this_course','	activitis_this_course','notes']);
            $report->ended_hadith_this_course = json_decode($report->ended_hadith_this_course );
            $report->ended_quraan_this_course = json_decode($report->ended_quraan_this_course );
            $report->activitis_this_course = json_decode($report->activitis_this_course );
            $report->notes = json_decode($report->notes );
                return response()->json($report);
        }
    }



    }

