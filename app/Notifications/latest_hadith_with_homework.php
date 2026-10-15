<?php

namespace App\Notifications;
use App\Services\QuranService;

class latest_hadith_with_homework extends BaseNotification
{
    private $num = [];

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
        $hadithTitles = array_map(fn($id) => QuranService::getHadithTitle($id), $this->num);
        $homeworkTitles = array_map(fn($id) => QuranService::getHadithTitle($id), $this->homework_num);

        return [
            'title' => 'تم إضافة إنجاز جديد في الأحاديث',
            'body' => 'الحديث هو : '.implode(' ، ', $hadithTitles).
                     ' الوظيفة هي  : '.implode(' ، ', $homeworkTitles),
            'footer' => 'تقبل الله منا ومنكم',
        ];
    }
}
