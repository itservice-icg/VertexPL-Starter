<?php

namespace App\Listeners;

use App\Events\NcrWorkflowActivity;
use App\Models\NcrNotification;
use App\Models\User;

class CreateNcrNotification
{
    public function handle(NcrWorkflowActivity $event): void
    {
        $report = $event->report;
        $ids = collect($report->current_owner_user_id ? [$report->current_owner_user_id] : [])
            ->merge($report->current_owner_department_id ? User::where('department_id', $report->current_owner_department_id)->where('is_active', true)->pluck('id') : [])
            ->unique();
        foreach ($ids as $userId) {
            NcrNotification::create([
                'user_id' => $userId, 'ncr_report_id' => $report->id,
                'notification_type' => $event->action,
                'title' => "แจ้งเตือน {$report->ncr_no}",
                'message' => "สถานะเปลี่ยนเป็น {$report->current_status}",
                'channel' => 'IN_APP', 'is_read' => false, 'sent_at' => now(), 'created_at' => now(),
            ]);
        }
    }
}
