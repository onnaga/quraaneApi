<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\FcmChannel; // ✅ إضافة مهمة
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

abstract class BaseNotification extends Notification
{
    use Queueable;

    abstract protected function getNotificationData(): array;

    public function via(object $notifiable): array
    {
        // ✅ نخبر Laravel بأن يرسل الإشعار إلى قاعدة البيانات و إلى Firebase
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        // هذه الدالة ستحفظ البيانات في قاعدة البيانات (لا تغيير هنا)
        return $this->getNotificationData();
    }

    /**
     * ✅ دالة جديدة لتجهيز الرسالة لإرسالها عبر Firebase
     * سيتم استدعاؤها تلقائيًا من FcmChannel
     */
    public function toFcm(object $notifiable): CloudMessage
    {
        $data = $this->getNotificationData();

        return CloudMessage::withTarget('token', $notifiable->fcm_token)
            ->withNotification(FirebaseNotification::create(
                $data['title'],
                $data['body']
            ))
            ->withData([ // يمكنك إرسال بيانات إضافية هنا إذا أردت
                'notification_id' => $this->id,
            ]);
    }
}