<?php

namespace App\Http\Controllers;

use App\Models\latest;
use App\Models\point;
use App\Models\student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Notifications\latest_activitis;
use App\Notifications\latest_hadith_with_homework;
use App\Notifications\latest_notes;
use App\Notifications\latest_quraan_with_homework;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;

class LatestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function  add_latest_quraan (Request $request,$user_id)
    {
        try {
        if(auth('api')->user()->privilege>1){
        $student_id =student::where('user_id' , $user_id)->first('id')->id;
        $student =student::find($student_id);
        $teacher_id = $student->teacher_id;
        if ($teacher_id!=null) {
            //add latest quraan
        $data = json_decode($request->getContent());
        //data[0] is latest quraan data[1] is latest q_homework
        $user_id = $student->user_id;
        // $updated =false;
        $latest_created=false;
        if($student->latest_id){
             latest::where('id',$student->latest_id)->update([
                'quran'=>json_encode($data[0]),
                'q_homework'=>json_encode($data[1])
            ]);
        }else{
            $latest_created = latest::create([
                'quran'=>json_encode($data[0]),
                'q_homework'=>json_encode($data[1])
            ]);
            student::where('id',$student_id)->update([
                'latest_id'=>$latest_created->id
            ]);
            $latest_created->quran = json_decode($latest_created->quran);
        }
        $report_controller = new ReportController;
        $report_controller->add_latest_quraan($data[0],$user_id,$teacher_id);
                            ////////////////// make note ////////////////////
                $users=User::where('id','=',$student->user_id)->get();
                Notification::send(
                   $users, new latest_quraan_with_homework($data)
                );
                return response()->json([
                  'updated'=>true
                ],200);
        // return response()->json([
        //         'latest_created' => $latest_created,
        //         'updated' => $updated,
        //         'student'=>$student,
        //         'report'=>$report
        //     ],200);

        }
        else{
            return response()->json([
                'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاولة',
            ],403);

        }

    }else{
        return response()->json([
            'message' => 'لا تملك صلاحية اضافة التسميعات ',
        ],403);
        }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage(),'path'=>$th->getTrace()],500);
        }



    }



    public function  add_latest_hadith (Request $request,$user_id)
    {
        try {
        if(auth('api')->user()->privilege>1){
        $student_id =student::where('user_id' , $user_id)->first('id')->id;
        $student =student::find($student_id);
        $teacher_id = $student->teacher_id;
        if ($teacher_id!=null) {
        $data = json_decode($request->getContent());
        $user_id = $student->user_id;
        $updated =false;
        $latest_created=false;
        if($student->latest_id){
            $updated = latest::where('id',$student->latest_id)->update([
                'hadith'=>json_encode($data[0]),
                'h_homework'=>json_encode($data[1])
            ]);
        }else{
            $latest_created = latest::create([
                'hadith'=>json_encode($data[0])
            ]);
            student::where('id',$student_id)->update([
                'latest_id'=>$latest_created->id
            ]);

        }
        $report_controller = new ReportController;
        $report   =$report_controller->add_latest_hadith($data[0],$user_id,$teacher_id);
                    ////////////////// make note ////////////////////
                        $users=User::where('id','=',$student->user_id)->get();
                        Notification::send(
                        $users, new latest_hadith_with_homework($data)
                        );

                        return response()->json([
                            'updated'=>true
                        ],200);

        // return response()->json([
        //         'latest_created' => $latest_created,
        //         'updated' => $updated,
        //         'student'=>$student,
        //         'report'=>$report
        //     ],200);

        }
        else{
            return response()->json([
                'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاولة',
            ],403);

        }

    }else{
        return response()->json([
            'message' => 'لا تملك صلاحية اضافة تسميعات',
        ],403);
        }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage() , 'paht'=>$th->getTrace()],500);
        }



    }


    public function  add_latest_activity (Request $request,$user_id)
    {
        try {
        if(auth('api')->user()->privilege>1){
            $student_id =student::where('user_id' , $user_id)->first('id')->id;
            $student =student::find($student_id);
        $teacher_id = $student->teacher_id;
        if ($teacher_id!=null) {
            //add latest activity
        $data =   $request->getContent();

        $user_id = $student->user_id;
        $updated =false;
        $latest_created=false;
        if($student->latest_id){
            $updated = latest::where('id',$student->latest_id)->update([
                'activitis'=>$data
            ]);
        }else{
            $latest_created = latest::create([
                'activitis'=>$data
            ]);
            student::where('id',$student_id)->update([
                'latest_id'=>$latest_created->id
            ]);


        }
        $report_controller = new ReportController;
        $report   =$report_controller->add_latest_activity(json_decode($data),$user_id,$teacher_id);
            ////////////////// make note ////////////////////
            $users=User::where('id','=',$student->user_id)->get();
            Notification::send(
            $users, new latest_activitis(json_decode($data))
            );
        return response()->json([
                'latest_created' => $latest_created,
                'updated' => $updated,
                'student'=>$student,
                'report'=>$report
            ],200);

        }
        else{
            return response()->json([
                'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاول',
            ],403);

        }

    }else{
        return response()->json([
            'message' => 'لا تمتلك صلاحية اضافة نشاطات للطلاب',
        ],403);
        }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getTrace()],500);
        }
    }


    public function  add_latest_note (Request $request,$user_id)
    {
        try {
        if(auth('api')->user()->privilege>1){
        $student_id =student::where('user_id' , $user_id)->first('id')->id;
        $student =student::find($student_id);
        $teacher_id = $student->teacher_id;
        if ($teacher_id!=null) {
        //add latest note
        $note = $request->note;
        $lost_points= $request->lost_point;
        $user_id = $student->user_id;
        $updated =false;
        $latest_created=false;
        if($student->latest_id){
            $updated = latest::where('id',$student->latest_id)->update([
                'note'=>json_encode(['note'=> $note , 'LPoints'=>$lost_points])
            ]);
        }else{
            $latest_created = latest::create([
                'note'=>$note
            ]);
            student::where('id',$student_id)->update([
                'latest_id'=>$latest_created->id
            ]);


        }
        $report_controller = new ReportController;
        $report   =$report_controller->add_latest_note($note,$request->lost_point ,$user_id,$teacher_id);

        if ($request->lost_point != 0 ) {
            $l_points = point::find($student->point_id)->l_points;
            point::where('id',$student->point_id)->update([
                'l_points' =>$l_points + $request->lost_point
            ]);
        }

                    ////////////////// make note ////////////////////
                    $users=User::where('id','=',$student->user_id)->get();
                    Notification::send(
                    $users, new latest_notes([$request->note ,$request->lost_point])
                    );


        return response()->json([
                'creted'=>true
            ],200);

        }
        else{
            return response()->json([
                'message' => 'المستخدم لا يملك استاذ , يرجى اضافة استاذ له ومن ثم اعادة المحاول',
            ],403);

        }

    }else{
        return response()->json([
            'message' => 'لا تمتلك صلاحية لإضافة آخر الملاحظات',
        ],403);
        }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage()],500);
        }
    }

    public function  get_latest_for_student (Request $request)
    {
        try {
            if (Auth::user()->privilege > 1 ){
                if(!$request->user_id)
                return response()->json(["the user id is required"]);

                $latest_id = student::where('user_id',$request->user_id)->first('latest_id');
                $latest = latest::find($latest_id);
                return response()->json($latest);
            }
            else{
            $latest_id = student::where('user_id',Auth::user()->id)->first('latest_id');
            $latest = latest::find($latest_id);
            return response()->json($latest);
            }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage(),'path'=>$th->getTrace()],500);
        }



    }



    public function  get_rank_my_group (Request $request)
    {
        try {
            if (Auth::user()->privilege > 1 ){
                $teacher_id = Auth::user()->id;
                $students = student::where('teacher_id' , $teacher_id)->get(['user_id' , 'point_id','teacher_id']);

                foreach ($students as $student) {
                    if ($student->point_id!=null){
                        $student->user_name = User::find($student->user_id)->name;
                        $points = point::find($student->point_id);
                        $student->points = $points->q_points +$points->h_points+$points->a_points -$points->l_points ;
                        }else{
                            $student->user_name = User::find($student->user_id)->name;
                            $student->points =0;
                        }}

                return response()->json($students);
            }
            else{
                $teacher_id = student::where('user_id' , Auth::user()->id)->get('teacher_id')[0]->teacher_id;
                $students = student::where('teacher_id' , $teacher_id)->get(['user_id' , 'point_id','teacher_id']);

                foreach ($students as $student) {
                    if ($student->point_id!=null){
                        $student->user_name = User::find($student->user_id)->name;
                        $points = point::find($student->point_id);
                        $student->points = $points->q_points +$points->h_points+$points->a_points -$points->l_points ;
                        }else{
                            $student->user_name = User::find($student->user_id)->name;
                            $student->points =0;
                        }}
                return response()->json($students);
            }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage(),'path'=>$th->getTrace()],500);
        }



    }



    public function  get_rank_masjed (Request $request)
    {
        try {

                $students = student::get(['user_id' , 'point_id' ,'teacher_id']);
                foreach ($students as $student) {
                if ($student->point_id!=null){
                $student->user_name = User::find($student->user_id)->name;
                $points = point::find($student->point_id);
                $student->points = $points->q_points +$points->h_points+$points->a_points -$points->l_points ;
                }else{
                    $student->user_name = User::find($student->user_id)->name;
                    $student->points =0;
                }
            }
            return response()->json($students);
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage(),'path'=>$th->getTrace()],500);
        }
    }



}
