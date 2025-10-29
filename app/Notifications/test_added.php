<?php

namespace App\Notifications;

class test_added extends BaseNotification
{
    private $type;
    private $at;
    private $notes;

    public function __construct($test)
    {
        $this->type = $test->aukaf ? 'أوقاف' : 'ترشيحي';
        $this->at = $test->at;
        $this->notes = $test->notes;
    }

    protected function getNotificationData(): array
    {
        return [
            'title'=> "تم إضافة سبر جديد : " . $this->at,
            'body'=> 'الملاحظات: ' . $this->notes,
            'footer'=> 'النوع: ' . $this->type,
        ];
    }
}
