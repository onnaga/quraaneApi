<?php

namespace App\Notifications;

// تأكد من استيراد الكلاس الأساسي للإشعارات

class delete_latest_quraan_notification extends BaseNotification
{



    protected function getNotificationData(): array
    {
        // استخدام مودل السور لجلب اسم السورة بناءً على الرقم

        return [
            'title' => 'تم حذف إنجاز قرآن',
            'body'  => 'تم حذف إنجاز من آخر تسميع ',
            
        ];
    }
}