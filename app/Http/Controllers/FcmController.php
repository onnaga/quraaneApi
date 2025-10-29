<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class FcmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api'); // JWT guard
    }

    // تحديث FCM token مباشرة
    public function updateToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        User::where('id', $user->id)->update([
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
