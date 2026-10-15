<?php

namespace App\Notifications;
use App\Services\QuranService;

class latest_quraan_with_homework extends BaseNotification
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
        $surahNames = array_map(fn($id) => QuranService::getSurahName($id), $this->num);
        $homeworkNames = array_map(fn($id) => QuranService::getSurahName($id), $this->homework_num);

        return [
            'title' => 'تم إضافة إنجاز قرآن جديد ',
            'body' => 'السورة هي : '.implode(' ، ', $surahNames).' الوظيفة هي : '.implode(' ، ', $homeworkNames),
            'footer' => 'تقبل الله منا ومنكم',
        ];
    }
}
