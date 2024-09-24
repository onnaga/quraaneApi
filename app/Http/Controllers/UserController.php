<?php

namespace App\Http\Controllers;

use App\Models\point;
use App\Models\student;
use App\Models\User;
use App\Notifications\taken_by_teacher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','register',]]);
    }

    public function register (Request $request)
    {
        try {
        $save_path=storage_path('\uploadsUser');

    $rules = [
        'name' => 'required',
        'password' => 'required',
        'phone_number'=>'required',
        'age'=>'required',
        'ended_quraan_in_aukaf'=>'required',
    ];
    // if($request->file('photo')!=null)
    // array_push($rules,['photo_hash'=>'required']);
    //Create a validator, unlike $this->validate(), this does not automatically redirect on failure, leaving the final control to you :)
    $validated = Validator::make($request->all(), $rules);

    //Check if the validation failed, return your custom formatted code here.
    if($validated->fails())
    {
        return response()->json(['status' => 'error', 'messages' => 'هناك خطأ في البيانات المدخلة', 'errors' => $validated->errors()],403);
    }

    $image = $request->file('photo');




    if (!file_exists($save_path)) {
        mkdir($save_path, 777, true);
    }
    if ($image == null) {
        $user = User::create([
            'name'=>$request->name,
            'phone_number'=>$request->phone_number,
            'password' => Hash::make( $request->password),
            'photo'=> null,
            'age'=>$request->age,
            'privilege'=>1,
            'photo_hash'=>null
        ]);
    }else{
        $user = User::create([
            'name'=>$request->name,
            'phone_number'=>$request->phone_number,
            'password' => Hash::make( $request->password),
            'photo'=> rand(0,9999999) .'.' . $image->getClientOriginalExtension(),
            'age'=>$request->age,
            'privilege'=>1,
            'photo_hash'=>$request->photo_hash
        ]);
    }




    $token = Auth::login($user);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'يوجد خطأ ما ',
            ], 401);
        }

        $teacher_id = student::create([
            'user_id'=>$user->id,
            'ended_quraan_in_aukaf'=>$request->ended_quraan_in_aukaf,
            'missing_days'=>0
        ])->teacher_id;

        $teacher_name =null;
        if($teacher_id)
        $teacher_name =User::find($teacher_id)->name;

        $manager = new ImageManager(new Driver);
        if($image!=null){
        $image = $manager->read($request->file('photo'));
        $image->save($save_path.'\\' . $user->photo);
    }
        return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'age'=> $user->age,
                'phone_number'=> $user->phone_number,
                'privilege'=> $user->privilege,
                'teacher_id' => $teacher_id,
                'teache_name'=>$teacher_name,
                'created_at'=>$user->created_at,
                'token' => $token,
            ],200);
        } catch (\Throwable $th) {
            if($th->getCode()==23000)
             return response()->json(['message'=>'انت مسجل بالفعل , اذا لم تكن مسجلا من قبل حاول تغيير الاسم'],500);
            return response()->json(['error'=>$th->getMessage() , 'line'=>$th->getTrace(),'line3'=>$th->getLine()],500);
        }

    }
    public function login(Request $request)
    {
        try {


        //Define your validation rules here.
    $rules = [
        'name' => 'required ',
        'password' => 'required'
    ];
    //Create a validator, unlike $this->validate(), this does not automatically redirect on failure, leaving the final control to you :)
    $validated = Validator::make($request->all(), $rules);

    //Check if the validation failed, return your custom formatted code here.
    if($validated->fails())
    {
        return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة', 'errors' => $validated->errors()],403);
    }

    //If not failed, the code will reach here

        $credentials = $request->only('name', 'password');

        $token = Auth::attempt($credentials);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'كلمة السر خاطئة أو أنك لست مسجل من قبل',
            ], 401);
        }
        $teacher_id= false;
        $user = Auth::user();
        $student = student::where('user_id',$user->id)->first();
        if($student)
        $teacher_id = $student->teacher_id;

        $teacher_name=null;
        if($teacher_id)
        $teacher_name = User::find($teacher_id)->name;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'age'=> $user->age,
            'phone_number'=> $user->phone_number,
            'privilege'=> $user->privilege,
            'photo_hash'=> $user->photo_hash,
            'teacher_id' => $teacher_id,
            'teache_name'=>$teacher_name,
            'token' => $token,
            ],200);
        } catch (\Throwable $th) {
            return response()->json([$th->getMessage()]);
        }

    }
    public function update_password( Request $request){
        try {
        $rules = [
            'old_password' => 'required',
            'password' => 'required',
        ];
        //Create a validator, unlike $this->validate(), this does not automatically redirect on failure, leaving the final control to you :)
        $validated = Validator::make($request->all(), $rules);

        //Check if the validation failed, return your custom formatted code here.
        if($validated->fails())
        {
            return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة', 'errors' => $validated->errors()],403);
        }
        $user = Auth::user();
        $credentials = ['name'=>$user->name ,'password'=> $request->old_password];
        //there we check if the password is right , if not the token woyldnt generated
        $token =Auth::attempt($credentials);
        if($token){
            $credentials = ['name'=>$user->name ,'password'=> $request->password];
            $update = User::where('id',$user->id)->update([
                "password"=>Hash::make($request->password)
            ]);
            $token =Auth::attempt($credentials);

            return response()->json([
                'token'=>$token,
                'update'=>$update
            ],200);

        }
        else{
            return response()->json([
                'message'=>'كلمة السر السابقة خاطئة',
            ],401);
        }




            } catch (\Throwable $th) {
                return response()->json(['error'=>$th->getMessage()],500);
            }
    }

    public function update_details( Request $request){
        try {
        $rules = [
            'phone_number' => 'required',
            'age' => 'required',
        ];
        //Create a validator, unlike $this->validate(), this does not automatically redirect on failure, leaving the final control to you :)
        $validated = Validator::make($request->all(), $rules);

        //Check if the validation failed, return your custom formatted code here.
        if($validated->fails())
        {
            return response()->json(['status' => 'error', 'messages' => 'البيانات المدخلة غير صحيحة', 'errors' => $validated->errors()],403);
        }
        $user = Auth::user();

        //there we check if the password is right , if not the token woyldnt generated

        $update = User::where('id',$user->id)->update([
            'phone_number' => $request->phone_number,
            'age' =>$request->age,
        ]);

            return response()->json([
                'update'=>$update
            ],200);


        // else{
        //     return response()->json([
        //         'message'=>'كلمة السر السابقة خاطئة',
        //     ],401);
        // }




            } catch (\Throwable $th) {
                return response()->json(['error'=>$th->getMessage()],500);
            }
    }
    public function get_personal_data(Request $request ){

        try {
            $user = Auth::user();
            $teacher_id = null;
            $teacher_name = null ;
            if (   //$user->privilege > 1
           // and
             $request->id!=null
            ) {
            $user = User::find($request->id);
            if(!$user)
            return response()->json("لا يوجد مستخدم يملك هذا الرقم الخاص");
            $teacher_id =student::where('user_id',$user->id)->first()->teacher_id;
            if($teacher_id)
            $teacher_name =User::find($teacher_id)->name;
            }elseif ($user->privilege==1) {
            $teacher_id = student::where('user_id',$user->id)->first()->teacher_id;
            if($teacher_id)
            $teacher_name =User::find($teacher_id)->name;
            }

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'age'=> $user->age,
                'phone_number'=> $user->phone_number,
                'privilege'=> $user->privilege,
                'photo_hash'=> $user->photo_hash,
                'teacher_id' => $teacher_id,
                'teache_name'=>$teacher_name,
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error'=>$th->getMessage() , 'line'=>$th->getTrace(),'line3'=>$th->getLine()],500);

            return response()->json(['error'=>$th->getMessage()]);

        }
    }
    public function update_photo(Request $request)
    {

        try {
    $rules = [
'photo'=>'required',
'photo_hash'=>'required',
    ];
    $validated = Validator::make($request->all(), $rules);

    if($validated->fails())
    {
        return response()->json(['status' => 'error', 'messages' => 'يوجد مشكلة ببالبيانات المدخلة', 'errors' => $validated->errors()],403);
    }

    $image =$request->file('photo');
    $photo_hash = $request->photo_hash;
    $new_photo = rand(0,9999999) .'.' . $image->getClientOriginalExtension();
    $old_Photo =Auth::user()->photo;
    $save_path=storage_path('\uploadsUser');



    $manager = new ImageManager(new Driver);

    $image = $manager->read($request->file('photo'));
    $image->save($save_path.'\\' . $new_photo);
    $delete_the_photo_from_storage = File::delete($save_path.'\\' .$old_Photo);
    $updated = User::where('id',Auth::user()->id)->update([
        'photo'=>$new_photo,
        'photo_hash'=>$photo_hash
    ]);
    return response()->json([
        'photo_hash'=>$photo_hash,
        'updated'=>$updated
    ]);
} catch (\Throwable $th) {
    return response()->json([
        'error'=>$th->getMessage()
    ]);
}


    }




    public function add_admin (Request $request)
    {
        try {
        if(Auth::user()->privilege==3){
    $rules = [
        'name' => 'required',
        'password' => 'required',
        'privilege'=> 'required'
    ];
    //Create a validator, unlike $this->validate(), this does not automatically redirect on failure, leaving the final control to you :)
    $validated = Validator::make($request->all(), $rules);

    //Check if the validation failed, return your custom formatted code here.
    if($validated->fails())
    {
        return response()->json(['status' => 'error', 'messages' => 'هناك خطأ بالبيانات المدخلة ', 'errors' => $validated->errors()],403);
    }


    $user = User::create([
        'name'=>$request->name,
        'password' => Hash::make( $request->password),
        'privilege'=>$request->privilege,


    ]);

        return response()->json([
                'user' => $user,
            ],200);
        }else{
        return response()->json([
            'message' => 'لا يمكنك إضافة أساتذة',
        ],403);
        }
    } catch (\Throwable $th) {
            if($th->getCode()==23000)
             return response()->json(['message'=>'هذا الأتاذ موجود بالفعل حاول تغيير الاسم واعادة المحاولة '],500);
            return response()->json(['error'=>$th->getMessage()],500);
        }



    }


    public function delete_user (Request $request)
    {
        $user_id = $request->user_id;
        if(!$request->user_id)
        return response()->json(['user id is required']);
        try {
        if(Auth::user()->privilege==3){
            $deleted_from_students=false;

                $deleted_from_students = student::where('user_id',$user_id)->delete();

            try {
                $deleted_from_users = User::find($user_id)->deleteOrFail();
                return response()->json([
                    'deleted_from_users_table' => $deleted_from_users,
                    'deleted_from_students_table' => $deleted_from_students,
                ],200);
            } catch (\Throwable $th) {
                return response()->json([
                    'هذا المستخدم غير موجود او أنه تم حذفه'
                ],200);

            }
        }else{
        return response()->json([
            'message' => 'عذرا , انت لا تمتلك صلاحية الحذف',
        ],403);
        }
    } catch (\Throwable $th) {

            return response()->json(['error'=>$th->getMessage()],500);
        }



    }


    public function showAll(Request $request)
    {
        if(!$request->teacher_id){
        $admins = User::where('privilege','>',1)->get();
        // $users =  User::where('privilege','=',1)->get(['id','name','phone_number','photo_hash']);
        return response()->json(
            $admins,
        //    'users'=>$users
        );

    }
    else{
        $students = student::where('teacher_id',$request->teacher_id)->get('user_id');
        $users=[];
        foreach ($students as $student) {
            array_push($users,User::find($student->user_id,['id','name','phone_number','photo_hash']));
        }

        // $save_path=storage_path('\uploadsUser');
        // $admins = User::where([['id','=',$teacher_id],['privilege','>',1]])->get(['id','name','phone_number','age','photo_hash']);
        // foreach ($admins as $admin) {
        //     $admin->photo = base64_encode(File::get($save_path.'\\' .$admin->photo));
        // }
        // foreach ($students as $student) {
        //     $student->User = User::find($student->user_id);
        //     // $student->User->photo = base64_encode(File::get($save_path.'\\' .$student->User->photo));
        //     $student->teacher_id =  User::find($student->teacher_id)->name;

        // }

        return response()->json([
           'users'=>$users
        ]);
    }
    }

    public function take_student(Request $request){

        try {
            $user = Auth::user();
            if($user->privilege >1){
                if(!$request->user_id){
                    return response()->json([
                        'user_id is required'
                    ]);
                }
                $student_id = student::where('user_id',$request->user_id)->first('id')->id;

                if(student::find($student_id)->teacher_id == null){
                $update =student::where('id',$student_id)->update([
                    'teacher_id'=>$user->id
                ]);
                $the_user_to_note = User::where('id','=',student::find($student_id)->user_id)->get();
                $teacher = User::find($user->id);
                Notification::send(
                    $the_user_to_note, new taken_by_teacher($teacher));
                return response()->json([
                    'update'=>$update
                ]);
            }else{
                return response()->json([
                    'error'=>'يوجد استاذ لهذا الطالب بالفعل , يمكنك الطلب من الاستاذ أن يترك هذا الطالب ومن ثم تعيد المحاولة'
                ]);
            }
        }
            else{
                return response()->json([
                    'error'=>'لا تملك صلاحية استلام طلاب '
                ]);

            }


        } catch (\Throwable $th) {
            return response()->json([
                'error'=>$th->getMessage()
            ]);
        }

    }

    public function leave_student(Request $request){

        try {
            $user = Auth::user();
            if($user->privilege >1){
                if(!$request->user_id){
                    return response()->json([
                        'user_id is required'
                    ]);
                }
                $student_id = student::where('user_id',$request->user_id)->first('id')->id;
                $update =student::where('id',$student_id)->update([
                    'teacher_id'=>null
                ]);
                return response()->json([
                    'update'=>$update
                ]);
            }
            else{
                return response()->json([
                    'error'=>'لا تملك صلاحية ترك مستخدمين'
                ]);

            }


        } catch (\Throwable $th) {
            return response()->json([
                'error'=>$th->getMessage()
            ]);
        }

    }

    public function add_wanting_students(Request $request)
    {
        if(Auth::user()->privilege >1){
        try {
            $body = $request->getContent();
            $ids = json_decode($body);
            foreach ($ids as $id) {

            $user_missing_days = student::where('user_id',$id)->first()->missing_days;
            $updated = student::where('user_id',$id)->update([
                'missing_days'=>$user_missing_days+1
            ]);
            }


            return response()->json(['updated'=>$updated]);
        } catch (\Throwable $th) {
            if($th->getMessage() =="Attempt to read property \"missing_days\" on null")
            return response()->json(['error'=>'انت تحاول وضع غياب لطالب لست استاذا له']);
            return response()->json(['error'=>$th->getMessage()]);
        }
    }
    else{
        return response()->json([
            'message'=>'لا تملك صلاحية التفقد '
        ],403);
    }

    }


    public function show_notifications()
    {

        try {
        $User_id = Auth::user()->id;
        $unreaded_notes=DB::table('notifications')->where([['notifiable_id','=',$User_id],['read_at','=',null]])->get("data");
        $readed_notes=DB::table('notifications')->where([['notifiable_id','=',$User_id],['read_at','!=',null]])->get("data");
        $decoded_unreaded_notes = [];
        $decoded_readed_notes = [];
        foreach ($unreaded_notes as $unreaded_note) {
            array_push($decoded_unreaded_notes,json_decode($unreaded_note->data));
        }
        foreach ($readed_notes as $readed_note) {
            array_push($decoded_readed_notes,json_decode($readed_note->data));
        }



            return response()->json(['unreaded_notes'=>$decoded_unreaded_notes,'readed_notes'=>$decoded_readed_notes]);
        } catch (\Throwable $th) {
            return response()->json(['th'=>$th->getMessage()]);
        }


    }


    public function read_notifications()
    {

        try {
            $User_id = Auth::user()->id;
            $make_readed =DB::table('notifications')->
            where([['notifiable_id','=',$User_id],['read_at','=',null]])->update([
                'read_at'=>now()
            ]);
            return response()->json(['make_readed'=>$make_readed]);
        } catch (\Throwable $th) {
            return response()->json(['th'=>$th->getMessage()]);
        }


    }




    public function get_score(Request $request)
    {
try{
    if(Auth::user()->privilege > 1){
        if(!$request->id){
            return response()->json(["the id is required"]);
        }


        $student = student::where('user_id',$request->id)->first(['point_id','ended_quraan_in_aukaf','missing_days']);

        $student->points =point::find($student->point_id);
    }else{

    $student = student::where('user_id',Auth::user()->id)->first(['point_id','ended_quraan_in_aukaf','missing_days']);

    $student->points =point::find($student->point_id);
    }

    return response()->json($student);


} catch (\Throwable $th) {
    return response()->json([
        'error'=>$th->getMessage()
    ]);
}


    }



    public function get_user_by_id(Request $request)
    {
        if(!$request->user_id)
        return response()->json('user id is required');
try{

    if(User::find($request->user_id)==null)
    return response()->json("هذا المستحدم غير موجود");
    return response()->json(User::find($request->user_id));
} catch (\Throwable $th) {
    return response()->json([
        'error'=>$th->getMessage()
    ]);
}


    }



    public function show_users_without_teacher(Request $request)
    {
    try{
    $students = student::where('teacher_id',null)->get(['user_id','ended_quraan_in_aukaf']);
    if($students->isEmpty())
    return response()->json("لا يوجد طلاب غير مستلمين ");

    foreach ($students as $student) {
        $user_info = User::find($student->user_id,);
        // ['name','id','phone_number','age']
        $student->name = $user_info->name;
        $student->phone_number = $user_info->phone_number;
        $student->age = $user_info->age;

    }
    return response()->json($students);
} catch (\Throwable $th) {
    return response()->json([
        'error'=>$th->getMessage()
    ]);
}


    }



}
