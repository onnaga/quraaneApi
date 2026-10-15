<?php

namespace App\Notifications;

class ReportRequestNotification extends BaseNotification
{
    private string $requesterName;
    private string $requestType;

    public function __construct(string $requesterName, string $requestType)
    {
        $this->requesterName = $requesterName;
        $this->requestType = $requestType;
    }

    protected function getNotificationData(): array
    {
        $body = $this->requestType === 'mosque_report'
            ? 'نرجو منك إرسال تقرير عن مسجدك إلى مدير التطبيق.'
            : "مدير الدورة ({$this->requesterName}) يطلب تقريراً منك.";

        return [
            'title' => '📊 طلب تقرير',
            'body' => $body,
            'footer' => 'اضغط للتوجه إلى صفحة إدارة التقارير',
            'type' => 'report_request',
            'color' => 'red',
            'action' => 'open_reports_tab',
        ];
    }
}
