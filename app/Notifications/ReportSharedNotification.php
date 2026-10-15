<?php

namespace App\Notifications;

class ReportSharedNotification extends BaseNotification
{
    private string $authorName;
    private string $reportType;

    public function __construct(string $authorName, string $reportType)
    {
        $this->authorName = $authorName;
        $this->reportType = $reportType;
    }

    protected function getNotificationData(): array
    {
        $body = $this->reportType === 'mosque'
            ? "لقد قام مشرف المسجد ({$this->authorName}) بمشاركة تقرير المسجد الشامل."
            : "لقد قام الأستاذ ({$this->authorName}) بمشاركة تقرير حلقته.";

        return [
            'title' => '📄 استلام تقرير جديد',
            'body' => $body,
            'footer' => 'اضغط هنا للتوجه إلى التقارير لتصفحها',
            'type' => 'report_shared',
            'color' => 'blue',
            'action' => 'open_reports_tab',
        ];
    }
}
