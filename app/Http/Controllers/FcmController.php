<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Api\Fcm\UpdateTokenRequest;

class FcmController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:api'); // JWT guard
    }

    // تحديث FCM token مباشرة
    public function updateToken(UpdateTokenRequest $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // التحديث مباشرة من خلال الكائن مفضل أكثر من استعلام DB جديد إذا كان الكائن موجوداً
        $user->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'message' => 'FCM token updated successfully.'
        ]);
    }

    // جلب كل الإشعارات للمستخدم الحالي
    public function getNotifications(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // استعلام مباشر على جدول notifications لتجنب undefined method
        $notifications = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    // تحديد كل الإشعارات غير المقروءة كمقروءة
    public function markAllAsRead(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All unread notifications marked as read.'
        ]);
    }
}
