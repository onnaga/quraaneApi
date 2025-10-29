<?php

namespace App\Notifications;

class latest_quraan_with_homework extends BaseNotification
{
    private $num  = [];
    private $homework_num = [];

    public function __construct($data)
    {
        foreach ($data[0] as $sora) {
            $this->num[] = $sora->num;
        }
        foreach ($data[1] as $homework) {
            $this->homework_num[] = $homework->num;
        }
    }

    protected function getNotificationData(): array
    {
        return [
            'title'=> 'تم إضافة إنجاز قرآن جديد ',
            'body'=>'السورة هي : '.json_encode($this->num).' الوظيفة هي : '.json_encode($this->homework_num),
            'footer'=>'اضغط لأجل التفاصيل',
        ];
    }
}
