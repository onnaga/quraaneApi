<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FcmChannel
{
    public function send($notifiable, Notification $notification)
    {
        // نتأكد أن المستخدم لديه توكن مسجل
        if (!$notifiable->fcm_token) {
            return;
        }

        try {
            // نستدعي الدالة التي ستجهز بيانات الإشعار
            $message = $notification->toFcm($notifiable);

            // نرسل الإشعار عبر Firebase
            Firebase::messaging()->send($message);
        } catch (\Exception $e) {
            \Log::error('FCM Service Error: ' . $e->getMessage());
        }
    }
}