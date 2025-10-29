<?php

namespace App\Notifications;

class taken_by_teacher extends BaseNotification
{
    private $name;
    private $phone_number;

    public function __construct($data)
    {
        $this->name = $data->name;
        $this->phone_number = $data->phone_number;
    }

    protected function getNotificationData(): array
    {
        return [
            'title'=> "أهلا بك في حلقة الاستاذ {$this->name}",
            'body'=> 'تستطيع التواص معه عبر الرقم: ' . $this->phone_number,
            'footer'=> 'أنجز أفضل ما لديك يا فتى الإسلام',
        ];
    }
}
