<?php

namespace App\Notifications;

class latest_notes extends BaseNotification
{
    private $lost_points;
    private $notes;

    public function __construct($data)
    {
        $this->notes = $data[0];
        $this->lost_points = $data[1];
    }

    protected function getNotificationData(): array
    {
        return [
            'title'=> 'ثم إضافة إنذار جديد',
            'body'=>'الإنذار هو  : '.$this->notes,
            'footer'=>'النقاط المخصومة : '.$this->lost_points,
        ];
    }
}
