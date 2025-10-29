<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Support\Facades\Storage;
use App\Models\Daora;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DaoraController extends Controller
{




     public function getJobsAndAreas()
    {
        try {
            // جلب كل السجلات من جدولي الأعمال والمناطق
            $jobs = Job::all();
            $areas = Area::all();

            // إرجاع البيانات في استجابة JSON منظمة
            return response()->json([
                'success' => true,
                'data' => [
                    'jobs' => $jobs,
                    'areas' => $areas,
                ]
            ], 200);

        } catch (\Exception $e) {
            // في حال حدوث أي خطأ
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function add_daora(Request $request)
{
    if (Auth::user()->privilege != 4) {
        return response()->json(['message' => 'لا يمكنك اضافة دورة'], 403);
    }

    $rules = [
        'daora_name' => 'required|string|max:255',
        'photo'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'name'       => 'required|string|max:255|unique:users,name',
        'password'   => 'required|string',
    ];

    $validated = Validator::make($request->all(), $rules);
    if ($validated->fails()) {
        return response()->json([
            'status'   => 'error',
            'messages' => 'هناك خطأ بالبيانات المدخلة',
            'errors'   => $validated->errors()
        ], 403);
    }

    try {
        DB::beginTransaction();

        // إنشاء مشرف جديد
        $user = User::create([
            'name'      => $request->name,
            'password'  => Hash::make($request->password),
            'privilege' => 3,
        ]);

        // معالجة صورة الدورة
        $photoName = null;
        $photoHash = null;
        if ($request->hasFile('photo')) {
            $image     = $request->file('photo');
            $photoName = uniqid('daora_') . '.' . $image->getClientOriginalExtension();

            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $img     = $manager->read($image);
            $img->scaleDown(800);
            $savePath = storage_path('app/public/uploadsDaoras');
            if (!file_exists($savePath)) {
                mkdir($savePath, 0777, true);
            }
            $img->save($savePath . '/' . $photoName);

            $photoHash = hash_file('sha256', $savePath . '/' . $photoName);
        }

        // إنشاء دورة وربطها بالمشرف
        $daora = Daora::create([
            'name'               => $request->daora_name,
            'photo'              => $photoName,
            'photo_hash'         => $photoHash,
            'number_of_students' => 0,
            'admin_id'           => $user->id,
        ]);

        User::where("id",$user->id)->update(['daora_id' => $daora->id]);

        DB::commit();

        return response()->json([
            'message' => 'تم إنشاء الدورة والمشرف بنجاح',
            'daora'   => $daora,
            'user'    => $user,
        ], 200);
    } catch (\Throwable $th) {
        DB::rollBack();
        if ($th->getCode() == 23000) {
            return response()->json(['message' => 'هذا الأستاذ موجود بالفعل، حاول تغيير الاسم'], 500);
        }
        return response()->json(['error' => $th->getMessage()], 500);
    }
}


public function delete_daora($id)
{
    if (Auth::user()->privilege != 4) {
        return response()->json(['message' => 'لا يمكنك حذف دورة'], 403);
    }

    try {
        DB::beginTransaction();

        $daora = Daora::findOrFail($id);

        $daoraData = [
            'id'                => $daora->id,
            'name'              => $daora->name,
            'photo'             => $daora->photo,
            'number_of_students'=> $daora->number_of_students,
        ];

        $daora->delete(); // البوتيد في Daora و User بيتكفل بحذف الصور

        DB::commit();

        return response()->json([
            'message'       => 'تم حذف الدورة والمشرف والطلاب بنجاح',
            'deleted_daora' => $daoraData
        ], 200);

    } catch (\Throwable $th) {
        DB::rollBack();
        return response()->json(['error' => $th->getMessage()], 500);
    }
}


public function get_all_daoras()
{
    try {
        $daoras = DB::table('daoras')
            ->leftJoin('users as admins', 'daoras.admin_id', '=', 'admins.id')
            ->select(
                'daoras.id as daora_id',
                'daoras.name as daora_name',
                DB::raw("CONCAT('" . url('storage/uploadsDaoras') . "/', daoras.photo) as daora_photo_url"),
                'daoras.number_of_students',
                'daoras.showable', // 👈 هنا أضفنا العمود من الداتابيز
                'admins.id as admin_id',
                'admins.name as admin_name',
                DB::raw("CONCAT('" . url('storage/uploadsAdmins') . "/', admins.photo) as admin_photo_url")
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'daoras' => $daoras
        ], 200);

    } catch (\Throwable $th) {
        return response()->json([
            'status' => 'error',
            'message' => $th->getMessage()
        ], 500);
    }
}



public function getPhotos(Request $request)
{
    // استقبل مصفوفة ids من الواجهة
    $ids = $request->input('ids'); // [1, 2, 3]

    if (!$ids || !is_array($ids)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid ids'
        ], 400);
    }

    $daoras = DB::table('daoras')
        ->whereIn('id', $ids)
        ->get(['id', 'photo']);

    $photos = [];

    foreach ($daoras as $daora) {
        if ($daora->photo && Storage::disk('public')->exists("uploadsDaoras/" . $daora->photo)) {
            $photos[$daora->id] = base64_encode(
                Storage::disk('public')->get("uploadsDaoras/" . $daora->photo)
            );
        } else {
            $photos[$daora->id] = null; // أو صورة افتراضية
        }
    }

    return response()->json([
        'status' => 'success',
        'photos' => $photos
    ]);
}


public function change_my_daora(Request $request)
{
    $rules = [
        'user_id'    => 'required|exists:users,id',
        'new_daora_id' => 'required|exists:daoras,id',
    ];

    $validated = Validator::make($request->all(), $rules);
    if ($validated->fails()) {
        return response()->json([
            'status'   => 'error',
            'messages' => 'هناك خطأ بالبيانات المدخلة',
            'errors'   => $validated->errors()
        ], 403);
    }

    try {
        DB::beginTransaction();

        $user = User::findOrFail($request->user_id);
        $oldDaoraId = $user->daora_id;
        $newDaoraId = $request->new_daora_id;

        // لو المستخدم كان ضمن دورة قديمة ننقص العدد منها
        if ($oldDaoraId) {
            Daora::where('id', $oldDaoraId)->decrement('number_of_students');
        }

        // نزيد عدد طلاب الدورة الجديدة
        Daora::where('id', $newDaoraId)->increment('number_of_students');

        // نحدث المستخدم
        $user->update(['daora_id' => $newDaoraId]);

        DB::commit();

        return response()->json([
            'status'  => 'success',
            'message' => 'تم نقل المستخدم إلى الدورة الجديدة بنجاح',
            'user'    => $user
        ], 200);

    } catch (\Throwable $th) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'error'  => $th->getMessage()
        ], 500);
    }
}


}
