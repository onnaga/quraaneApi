<?php

namespace App\Notifications;

class latest_activities extends BaseNotification
{
    private $activities_name = [];

    public function __construct($data)
    {
        foreach ($data as $activity) {
            $this->activities_name[] = is_array($activity) ? $activity['name'] : $activity->name;
        }
    }

    protected function getNotificationData(): array
    {
        return [
            'title' => 'تم إضافة إنجاز جديد في النشاطات ',
            'body' => 'الإنجاز هو : '.implode(' ، ', $this->activities_name),
            'footer' => 'تقبل الله منا ومنكم',
        ];
    }
}
