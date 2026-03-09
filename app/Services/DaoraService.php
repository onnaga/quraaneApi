<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Daora;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DaoraService
{
    /**
     * Create a new Daora and an Admin for it.
     */
    public function createDaoraWithAdmin(array $data, $imageFile = null)
    {
        DB::beginTransaction();
        try {
            // 1. إنشاء مشرف جديد
            $user = User::create([
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'privilege' => User::ROLE_SUPERVISOR, // 3
            ]);

            // 2. معالجة صورة الدورة إذا وجدت
            $photoName = null;
            $photoHash = null;

            if ($imageFile) {
                $photoName = uniqid('daora_').'.'.$imageFile->getClientOriginalExtension();

                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver);
                $img = $manager->read($imageFile);
                $img->scaleDown(800);

                $savePath = storage_path('app/public/uploadsDaoras');
                if (! file_exists($savePath)) {
                    mkdir($savePath, 0777, true);
                }

                $img->save($savePath.'/'.$photoName);
                $photoHash = hash_file('sha256', $savePath.'/'.$photoName);
            }

            // 3. إنشاء دورة وربطها بالمشرف
            $daora = Daora::create([
                'name' => $data['daora_name'],
                'photo' => $photoName,
                'photo_hash' => $photoHash,
                'number_of_students' => 0,
                'admin_id' => $user->id,
            ]);

            // 4. تحديث المشرف
            $user->update(['daora_id' => $daora->id]);

            DB::commit();

            return [
                'success' => true,
                'daora' => $daora,
                'user' => $user,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    /**
     * Delete Daora by ID.
     */
    public function deleteDaora($id)
    {
        DB::beginTransaction();
        try {
            $daora = Daora::findOrFail($id);

            $daoraData = [
                'id' => $daora->id,
                'name' => $daora->name,
                'photo' => $daora->photo,
                'number_of_students' => $daora->number_of_students,
            ];

            // يقوم الحذف هنا بالتكفل بحذف الصور والمشرفين بناءً على البوتيد القديم (كلام المستخدم القديم)
            $daora->delete();

            DB::commit();

            return [
                'success' => true,
                'deleted_daora' => $daoraData,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get All Daoras with details.
     */
    public function getAllDaoras()
    {
        return DB::table('daoras')
            ->leftJoin('users as admins', 'daoras.admin_id', '=', 'admins.id')
            ->select(
                'daoras.id as daora_id',
                'daoras.name as daora_name',
                DB::raw("CONCAT('".url('storage/uploadsDaoras')."/', daoras.photo) as daora_photo_url"),
                'daoras.number_of_students',
                'daoras.showable',
                'admins.id as admin_id',
                'admins.name as admin_name',
                DB::raw("CONCAT('".url('storage/uploadsAdmins')."/', admins.photo) as admin_photo_url")
            )->get();
    }

    /**
     * Get explicit Daoras Photos by IDs.
     */
    public function getDaorasPhotos(array $ids)
    {
        $daoras = DB::table('daoras')
            ->whereIn('id', $ids)
            ->get(['id', 'photo']);

        $photos = [];
        foreach ($daoras as $daora) {
            if ($daora->photo && Storage::disk('public')->exists('uploadsDaoras/'.$daora->photo)) {
                $photos[$daora->id] = base64_encode(
                    Storage::disk('public')->get('uploadsDaoras/'.$daora->photo)
                );
            } else {
                $photos[$daora->id] = null;
            }
        }

        return $photos;
    }

    /**
     * Change Daora of specific user.
     */
    public function changeUserDaora($userId, $newDaoraId)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);
            $oldDaoraId = $user->daora_id;

            if ($oldDaoraId) {
                Daora::where('id', $oldDaoraId)->decrement('number_of_students');
            }

            Daora::where('id', $newDaoraId)->increment('number_of_students');
            $user->update(['daora_id' => $newDaoraId]);

            DB::commit();

            return [
                'success' => true,
                'user' => $user,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Jobs and Areas.
     */
    public function getJobsAndAreas()
    {
        return [
            'jobs' => Job::all(),
            'areas' => Area::all(),
        ];
    }
}
