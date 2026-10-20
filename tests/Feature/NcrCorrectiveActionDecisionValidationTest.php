<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrCorrectiveAction;
use App\Models\NcrCorrectiveActionCycle;
use App\Models\NcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrCorrectiveActionDecisionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejection_without_a_reason_returns_a_thai_validation_error(): void
    {
        $department = Department::create([
            'code' => 'CA-DECISION',
            'name_th' => 'หน่วยงานทดสอบ',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->givePermissionTo(Permission::findOrCreate('ncr.view', 'web'));
        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-DECISION-00001',
            'report_date' => '2026-10-16',
            'found_date' => '2026-10-16',
            'reporter_id' => $user->id,
            'reporter_department_id' => $department->id,
            'defect_quantity' => 1,
            'total_quantity' => 1,
            'problem_description' => 'Decision validation fixture',
            'current_status' => 'DISPOSITION_APPROVED',
            'version_no' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $cycle = NcrCorrectiveActionCycle::create([
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'origin' => 'INITIAL',
            'status' => 'PENDING_APPROVAL',
            'opened_by' => $user->id,
            'opened_at' => now(),
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $report->update(['current_corrective_action_cycle_id' => $cycle->id]);
        NcrCorrectiveAction::create([
            'ncr_report_id' => $report->id,
            'corrective_action_cycle_id' => $cycle->id,
            'action_no' => 1,
            'action_type' => 'CORRECTION',
            'corrective_action_detail' => 'ทดสอบแผนแก้ไข',
            'responsible_department_id' => $department->id,
            'due_date' => '2026-10-23',
            'action_status' => 'PENDING_APPROVAL',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Gate::define('ncr.corrective-action.approve', fn (User $actor, NcrReport $ncr): bool => true);

        $this->followingRedirects()
            ->actingAs($user)
            ->from(route('ncr.show', $report))
            ->post(route('ncr.corrective-actions.decision', $report), [
                'version_no' => 1,
                'decision' => 'REJECTED',
                'decision_note' => '',
            ])
            ->assertOk()
            ->assertSee('กรุณาระบุเหตุผลเมื่อไม่อนุมัติ Corrective Action')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-errormessage="corrective-action-decision-note-error"', false);

        $this->assertSame('DISPOSITION_APPROVED', $report->fresh()->current_status);
    }
}
