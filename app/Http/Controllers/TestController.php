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

use function PHPUnit\Framework\stringContains;

class TestController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api');
    }


    public function test_note() {
        $test = test::find(10);
         Notification::send(User::where('id','!=',1)->get(), new test_added($test));
         return response()->json(['aaa']);
    }
    public function add_new_test (Request $request) {

        $user =Auth::user();
        if( $user->privilege == 3 ){
        try {
        $rules = [
        'at' => 'required',
        'notes'=>'required',
        ];
        $validated = Validator::make($request->all(), $rules);

        if($validated->fails())
        {
            return response()->json(['status' => 'error', 'messages' => 'يوجد خطأ بالبيانات المضافة', 'errors' => $validated->errors()],403);
        }

        $created_test= test::createOrFirst([
            'at'=>$request->at,
            'notes'=>$request->notes,
            'aukaf'=>false,
        ]);
        $users=User::where('id','!=',Auth::user()->id)->get();
        Notification::send(
            $users, new test_added($created_test)
        );

        return response()->json(
            ['created_test'=>$created_test]
        );
    }catch (\Throwable $th) {
        $pieces = explode(" ", $th->getMessage());
        $first_part = implode(" ", array_splice($pieces, 0, 7));
        if($first_part=="SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry")
        return response()->json(
            [
            'message'=> 'هذا السبر مضاف بالفعل سابقا , غير التاريخ والوقت وحاول مجددا',
            ]
        ,200);
        return response()->json(
                    [
                    'error'=> $th->getMessage()
                    ]
                ,500);

            }
    }
    else{
        return response()->json([
            'message'=>'لا تملك الصلاحية لإضافة سبر']
        ,403);
    }

        }


    public function show_tests() {
        $tests =test::orderBy('at','DESC')->get();

        return response()->json(
            $tests
        );

        }

        public function accept_test($test_id) {
            if(Auth::user()->privilege==1){
            $test =test::find($test_id);
            if($test){
                if($test->at > Carbon::now()){
                $user_id = Auth::user()->id;
                $user_test =user_test::where([['test_id','=',$test_id],['user_id','=',$user_id]])->first();

                    if(!$user_test){
                        $user_test = user_test::create(
                            [
                                    'test_id'=>$test_id,
                                    'user_id'=>$user_id,
                                    'the_part_to_test_in'=>0,
                                    'rating'=>0,
                                    'notes'=>0,
                            ]);
                            return response()->json(
                                $user_test
                            );
                    }else{
                        return response()->json(
                            'تم قبوله سابقا '
                         );
                    }


        }else{
            return response()->json(
               'إن هذا السبر قد انتهى حاول التسجيل في سبر غير منتهي'
            );
        }
        }
        else{
            return response()->json([
                'error'=>'الرقم الخاص بالسبر خاطئ'
            ]);
        }
    }else{
        return response()->json([
            'error'=>'لا يمكن للأساتذة التسجيل على سبر أنشئ حساب طالب وحاول مجددا'
        ]);
    }
            }

        public function delete_accepted_test($test_id) {
                if(Auth::user()->privilege==1){
                $test =test::find($test_id);
                if($test){
                    if($test->at > Carbon::now()){
                    $user_id = Auth::user()->id;
                    $deleted =user_test::where([['test_id','=',$test_id],['user_id','=',$user_id],['the_part_to_test_in','=',0]])->delete();
                            return response()->json([
                                'deleted'=>$deleted
                             ]);


            }else{
                return response()->json(
                   'السبر قد انتهى بالفعل'
                );
            }
            }
            else{
                return response()->json([
                    'error'=>'رقم السبر غير صحيح'
                ]);
            }
        }else{
            return response()->json([
                'error'=>'لا يمكن للأساتذة التسجيل على سبر'
            ]);
        }
        }


        public function delete_test($test_id) {
            if(Auth::user()->privilege==3){
                try {
                    $delete =test::where("id",$test_id)->delete();
                    return response()->json($delete);
                } catch (\Throwable $th) {

                    return response()->json([
                        'error'=>'رقم السبر غير صحيح'
                    ]);                }



    }else{
        return response()->json([
            'error'=>'لا تمتلك تصريح حذف السبر '
        ]);
    }
    }

        public function show_test_accepters($test_id) {
            try {
            $tests =user_test::where('test_id',$test_id)->get();
            foreach ($tests as $test) {
                $test->user_name = User::find($test->user_id)->name;
                $test->test_date = test::find($test->test_id)->at;
            }

            return response()->json(
                $tests
            );

                        } catch (\Throwable $th) {
                            return response()->json([
                                $th->getMessage(),
                                'trace'=>$th->getTrace()
                            ]);
                        }
        }


        public function update_test_accepter_data( Request $request,$test_id , $user_id) {

            if(Auth::user()->privilege >1){
            try {
                $rules = [
                    'the_part_to_test_in' => 'required',
                    'rating'=>'required',
                    'notes'=>'required',
                    ];
                    $validated = Validator::make($request->all(), $rules);

                    if($validated->fails())
                    {
                        return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة ', 'errors' => $validated->errors()],403);
                    }

                $user_test = user_test::where([['user_id','=',$user_id],['test_id','=',$test_id]])->first();
                if($user_test){
                    $updated =
                    user_test::where([['user_id','=',$user_id],['test_id','=',$test_id]])
                    ->update([
                    'the_part_to_test_in' => $request->the_part_to_test_in,
                    'rating'=>$request->rating,
                    'notes'=>$request->notes,
                    ]);


                    return response()->json(
                        ['updated'=>$updated]
                     );

                }
                else{
                    return response()->json(
                       ['error'=>'هذا المستخدم لم يسجل في هذا السبر ']

                    );
                }

                        } catch (\Throwable $th) {
                            return response()->json(
                                $th->getMessage()
                            );
                        }

                    }
                    else{
                        return response()->json([
                            'message'=>'لا تملك صلاحية لتغيير نتائج الطالب في السبر '
                        ]);
                    }
        }



        public function show_success_students_in_test($test_id) {
            try {
                $success_users = user_test::where([['test_id','=',$test_id],['rating','>=',80]])->get();
                $fail_users = user_test::where([['test_id','=',$test_id],['rating','<',80]])->get();
                foreach ($success_users as $success_user) {
                    $success_user->user_name = User::find($success_user->user_id)->name;
                }
                foreach ($fail_users as $fail_user) {
                    $fail_user->user_name = User::find($fail_user->user_id)->name;
                }
                    return response()->json(
                        ['success_users'=>$success_users,
                        'fail_users'=>$fail_users]
                     );
                        } catch (\Throwable $th) {
                            return response()->json(
                                ['message'=>$th->getMessage() ,$th->getTrace()]

                            );
                        }

        }




    public function make_aukaf_test_for_success_students (Request $request,$test_id) {

        if( Auth::user()->privilege == 3 ){
        try {
        $rules = [
        'at' => 'required',
        'notes'=>'required',
        ];
        $validated = Validator::make($request->all(), $rules);

        if($validated->fails())
        {
            return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة', 'errors' => $validated->errors()],403);
        }

        $aukaf_test = test::createOrFirst([
        'at'=>$request->at,
        'notes'=>$request->notes,
        'aukaf'=>true
        ]);

        $success_users= user_test::where([['test_id','=',$test_id],['rating','>=',80]])->get();

        foreach ($success_users as $succes_user) {
            user_test::firstOrCreate([
            'user_id'=>$succes_user->user_id,
            'test_id'=>$aukaf_test->id,
            'the_part_to_test_in'=>$succes_user->the_part_to_test_in,
            'rating'=>0,
            'notes'=>0
            ]);
        }
        $aukaf_users = user_test::where([['test_id','=',$aukaf_test->id]])->get();
        $aukaf_test->note = $aukaf_test->note . ",(success users at last test added automatically)".
        $users=User::where('id','!=',Auth::user()->id)->get();
        Notification::send(
            $users, new test_added($aukaf_test)
        );
        return response()->json([
            'aukaf_test'=>$aukaf_test,
            'aukaf_users'=>$aukaf_users
        ]);
    }catch (\Throwable $th) {
        $pieces = explode(" ", $th->getMessage());
        $first_part = implode(" ", array_splice($pieces, 0, 7));
        if($first_part=="SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry")
        return response()->json(
            [
            'message'=> 'السبر مضاف بالفعل حاول تغيير التاريخ ',
            ]
        ,200);
        return response()->json(
                    [
                    'error'=> $th->getMessage()
                    ]
                ,500);

            }
    }
    else{
        return response()->json([
            'message'=>'لا تملك صلاحية لتحويل الطلاب الناجحين الى سبر الاوقاف']
        ,403);
    }

        }



        public function update_aukaf_tests_after_the_test( Request $request, $test_id , $user_id) {
            try {
            $rules = [
                'rating'=>'required',
                'notes'=>'required',
                ];
                $validated = Validator::make($request->all(), $rules);

                if($validated->fails())
                {
                    return response()->json(['status' => 'error', 'messages' => 'توجد مشكلة في البيانات المدخلة', 'errors' => $validated->errors()],403);
                }

                $aukaf_test = user_test::where([['user_id','=',$user_id],['test_id','=',$test_id]])->first();
                $test_date = test::find($test_id,'at');
                if($aukaf_test){
                    if (Carbon::now()<$test_date) {
                        return response()->json(
                            ['error'=>'هذا السبر لم يبدأ بعد , حاول التعديل على نتائج الطلاب فيه بعد وقت البدء']
                         );
                    }
                    $updated =
                    user_test::where([['user_id','=',$user_id],['test_id','=',$test_id]])
                    ->update([
                    'rating'=>$request->rating,
                    'notes'=>$request->notes,
                    ]);
                    if($request->rating>=80){
                        $student_ended_quraan_in_aukaf = json_decode(student::where('user_id',$user_id)->first()->ended_quraan_in_aukaf);
                        $parts_in_aukaf_test= json_decode($aukaf_test->the_part_to_test_in);
                        $json_parts_to_DB = json_encode(array_push($student_ended_quraan_in_aukaf,$parts_in_aukaf_test));
                        student::where('user_id',$user_id)->update([
                            'ended_quraan_in_aukaf'=>$json_parts_to_DB,
                        ]);
                    }


                    return response()->json(
                        ['updated'=>$updated]
                     );

                }
                else{
                    return response()->json(
                       ['error'=>'هذا المستخدم غير مقبول في هذا السبر']

                    );
                }

                        } catch (\Throwable $th) {
                            return response()->json(
                                $th->getMessage()
                            );
                        }
        }



}
