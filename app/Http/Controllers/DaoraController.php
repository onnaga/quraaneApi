<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\Daora\AddDaoraRequest;
use App\Http\Requests\Api\Daora\ChangeDaoraRequest;
use App\Services\DaoraService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DaoraController extends Controller
{
    use ApiResponseTrait;

    protected $daoraService;

    public function __construct(DaoraService $daoraService)
    {
        $this->daoraService = $daoraService;
    }

    public function getJobsAndAreas()
    {
        try {
            $data = $this->daoraService->getJobsAndAreas();

            // إرجاع البيانات بنفس الشكل القديم
            return $this->successResponse([
                'success' => true,
                'data' => [
                    'jobs' => $data['jobs'],
                    'areas' => $data['areas'],
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->successResponse([
                'success' => false,
                'message' => 'Failed to fetch data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function add_daora(AddDaoraRequest $request)
    {
        $result = $this->daoraService->createDaoraWithAdmin(
            $request->validated(),
            $request->file('photo')
        );

        if (! $result['success']) {
            if (isset($result['code']) && $result['code'] == 23000) {
                return $this->errorSimpleMessage('هذا الأستاذ موجود بالفعل، حاول تغيير الاسم', 500);
            }

            return $this->successResponse(['error' => $result['error']], 500);
        }

        return $this->successResponse([
            'message' => 'تم إنشاء الدورة والمشرف بنجاح',
            'daora' => $result['daora'],
            'user' => $result['user'],
        ], 200);
    }

    public function delete_daora($id)
    {
        if (Auth::user()->privilege != 4) {
            return $this->errorSimpleMessage('لا يمكنك حذف دورة', 403);
        }

        $result = $this->daoraService->deleteDaora($id);

        if (! $result['success']) {
            return $this->successResponse(['error' => $result['error']], 500);
        }

        return $this->successResponse([
            'message' => 'تم حذف الدورة والمشرف والطلاب بنجاح',
            'deleted_daora' => $result['deleted_daora'],
        ], 200);
    }

    public function get_all_daoras()
    {
        try {
            $daoras = $this->daoraService->getAllDaoras();

            return $this->successResponse([
                'status' => 'success',
                'daoras' => $daoras,
            ], 200);

        } catch (\Exception $e) {
            return $this->successResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPhotos(Request $request)
    {
        $ids = $request->input('ids');

        if (! $ids || ! is_array($ids)) {
            return $this->successResponse([
                'status' => 'error',
                'message' => 'Invalid ids',
            ], 400);
        }

        $photos = $this->daoraService->getDaorasPhotos($ids);

        return $this->successResponse([
            'status' => 'success',
            'photos' => $photos,
        ]);
    }

    public function change_my_daora(ChangeDaoraRequest $request)
    {
        $result = $this->daoraService->changeUserDaora(
            $request->user_id,
            $request->new_daora_id
        );

        if (! $result['success']) {
            return $this->successResponse([
                'status' => 'error',
                'error' => $result['error'],
            ], 500);
        }

        return $this->successResponse([
            'status' => 'success',
            'message' => 'تم نقل المستخدم إلى الدورة الجديدة بنجاح',
            'user' => $result['user'],
        ], 200);
    }
}
