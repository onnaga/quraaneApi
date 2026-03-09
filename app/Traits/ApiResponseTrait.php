<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * إرجاع استجابة نجاح مع أي بيانات بصيغتها الأصلية تماماً
     * لضمان توافقية الواجهة الأمامية (Zero Regression)
     *
     * @param  array|object  $data
     * @param  int  $statusCode
     */
    protected function successResponse($data = [], $statusCode = 200): JsonResponse
    {
        return response()->json($data, $statusCode);
    }

    /**
     * إرجاع استجابة خطأ بهيكلية متوافقة تماماً مع ما يتوقعه تطبيق الموبايل
     *
     * @param  mixed  $errors
     */
    protected function errorResponse(string $message, $errors = null, int $statusCode = 403): JsonResponse
    {
        $response = [
            'status' => 'error',
            'messages' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * استجابة خطأ بسيطة (رسالة فقط)
     */
    protected function errorSimpleMessage(string $message, int $statusCode = 403): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $statusCode);
    }
}
