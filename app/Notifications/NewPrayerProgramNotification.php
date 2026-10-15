<?php

namespace App\Notifications;

class NewPrayerProgramNotification extends BaseNotification
{
    private $programName;

    public function __construct($programName)
    {
        $this->programName = $programName;
    }

    protected function getNotificationData(): array
    {
        return [
            'title' => 'برنامج صلاة جديد!',
            'body' => 'تم إطلاق برنامج الصلاة [' . $this->programName . '] في دورتك، سارع بالمشاركة!',
            'footer' => 'برامج الصلاة'
        ];
    }
}
