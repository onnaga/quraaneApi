<?php

namespace App\Notifications;

class TeacherTaskNotification extends BaseNotification
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    protected function getNotificationData(): array
    {
        return [
            'title' => 'تنبيه المهام اليومية',
            'body' => $this->message,
            'footer' => 'نرجو استكمال المهام بأقرب وقت',
            'type' => 'daily_task',
            'color' => 'red', // لون أحمر للتنبيه
        ];
    }
}
