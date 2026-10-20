<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrNotification;
use App\Models\NcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_shows_live_status_without_rewriting_event_history(): void
    {
        $department = Department::create(['code' => 'QA', 'name_th' => 'QA', 'is_active' => true]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->givePermissionTo(Permission::findOrCreate('ncr.view'));
        $report = NcrReport::create([
            'ncr_no' => 'NCR-NOTIFICATION-001', 'report_date' => now()->toDateString(),
            'found_date' => now()->toDateString(), 'reporter_id' => $user->id,
            'reporter_department_id' => $department->id, 'problem_description' => 'Test',
            'current_status' => 'INITIAL_REVIEW', 'version_no' => 1,
            'defect_quantity' => 1, 'total_quantity' => 10,
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $notification = NcrNotification::create([
            'user_id' => $user->id, 'ncr_report_id' => $report->id,
            'notification_type' => 'SUBMIT', 'title' => "NCR {$report->ncr_no} ต้องดำเนินการ",
            'message' => 'สถานะเปลี่ยนเป็น INITIAL_REVIEW', 'channel' => 'IN_APP',
            'is_read' => false, 'created_at' => now(),
        ]);
        $report->update(['current_status' => 'CLOSED']);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertSee('แจ้งเตือน '.$report->ncr_no)
            ->assertDontSee("NCR {$report->ncr_no} ต้องดำเนินการ")
            ->assertSee('สถานะปัจจุบัน')
            ->assertSee(config('ncr.status_labels.CLOSED'))
            ->assertSee('เหตุการณ์เมื่อแจ้งเตือน')
            ->assertSee('สถานะเปลี่ยนเป็น INITIAL_REVIEW');
        $this->assertSame('สถานะเปลี่ยนเป็น INITIAL_REVIEW', $notification->fresh()->message);
    }
}
