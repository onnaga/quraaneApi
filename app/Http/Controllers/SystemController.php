<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    /**
     * يعيد بيانات الإصدار الحالي للتطبيق.
     * يقرأ القيم من ملف .env لتسهيل التعديل بدون تغيير الكود.
     */
    public function appVersion(): JsonResponse
    {
        return response()->json([
            'min_required_version' => env('APP_MIN_VERSION', '1.0.0'),
            'latest_version'      => env('APP_LATEST_VERSION', '1.0.0'),
            'update_url'          => env('APP_UPDATE_URL', ''),
            'force_update'        => filter_var(env('APP_FORCE_UPDATE', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
