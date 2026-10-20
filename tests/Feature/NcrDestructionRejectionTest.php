<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DispositionType;
use App\Models\NcrReport;
use App\Models\ProblemType;
use App\Models\Product;
use App\Models\SeverityLevel;
use App\Models\User;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrDestructionRejectionTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private ProblemType $problem;
    private SeverityLevel $severity;
    private DispositionType $scrapDisposition;

    protected function setUp(): void
    {
        parent::setUp();

        Unit::create(['code' => 'PCS', 'name_th' => 'ชิ้น', 'is_active' => true]);
        Product::create(['code' => 'P001', 'name_th' => 'สินค้า A', 'is_active' => true]);

        $this->department = Department::create([
            'code' => 'OPS',
            'name_th' => 'Operations',
            'is_active' => true,
        ]);
        $this->problem = ProblemType::create([
            'code' => 'PROCESS',
            'name_th' => 'กระบวนการ',
            'is_active' => true,
        ]);
        $this->severity = SeverityLevel::create([
            'code' => 'HIGH',
            'name_th' => 'สูง',
            'is_active' => true,
        ]);
        $this->scrapDisposition = DispositionType::create([
            'code' => 'SCRAP',
            'name_th' => 'เสนอทำลาย',
            'is_active' => true,
        ]);
    }

    public function test_rejected_scrap_destruction_returns_to_corrective_action_and_preserves_unset_report_due_date(): void
    {
        $report = $this->pendingDestructionApproval();
        $cycle = $report->currentCorrectiveActionCycle()->firstOrFail();
        $snapshot = $cycle->snapshots()->latest('revision_no')->firstOrFail();
        $approver = $this->user(['ncr.destruction.approve']);
        $targetTime = $report->due_date?->toDateString();
        $version = (int) $report->version_no;
        $reason = 'ทำลายไม่ได้ ต้องดำเนินการแก้ไขตามแผน';

        $this->actingAs($approver)
            ->post(route('ncr.destruction-decision', $report), [
                'version_no' => $version,
                'decision' => 'REJECTED',
                'decision_note' => $reason,
            ])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame('DISPOSITION_APPROVED', $report->current_status);
        $this->assertSame('REJECTED', $cycle->fresh()->status);
        $this->assertNull($report->current_owner_user_id);
        $this->assertSame($this->department->id, $report->current_owner_department_id);
        $this->assertSame($targetTime, $report->due_date?->toDateString());
        $this->assertSame($version + 1, (int) $report->version_no);

        $this->assertDatabaseHas('ncr_dispositions', [
            'ncr_report_id' => $report->id,
            'status' => 'APPROVED',
            'responsible_department_id' => $this->department->id,
        ]);
        $this->assertDatabaseHas('ncr_approvals', [
            'ncr_report_id' => $report->id,
            'approval_stage' => 'DESTRUCTION_COMMITTEE_APPROVAL',
            'decision' => 'REJECTED',
            'decision_note' => $reason,
            'approver_id' => $approver->id,
            'corrective_action_cycle_id' => $cycle->id,
            'corrective_action_plan_snapshot_id' => $snapshot->id,
        ]);
        $this->assertDatabaseHas('ncr_status_history', [
            'ncr_report_id' => $report->id,
            'from_status' => 'PENDING_DESTRUCTION_APPROVAL',
            'to_status' => 'DISPOSITION_APPROVED',
            'action_code' => 'REJECT_DESTRUCTION',
            'action_note' => $reason,
            'assigned_department_id' => $this->department->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'ncr',
            'action' => 'ncr.reject_destruction',
        ]);

        $this->actingAs($approver)
            ->post(route('ncr.destruction-decision', $report), [
                'version_no' => $version,
                'decision' => 'REJECTED',
                'decision_note' => 'stale request',
            ])
            ->assertStatus(409);
    }

    public function test_approved_scrap_destruction_links_current_cycle_and_snapshot_before_execution(): void
    {
        $report = $this->pendingDestructionApproval();
        $cycle = $report->currentCorrectiveActionCycle()->firstOrFail();
        $snapshot = $cycle->snapshots()->latest('revision_no')->firstOrFail();
        $approver = $this->user(['ncr.destruction.approve']);

        $this->actingAs($approver)
            ->post(route('ncr.destruction-decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'APPROVED',
                'decision_note' => 'Approved for this exact plan revision.',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('ACTION_IN_PROGRESS', $report->current_status);
        $this->assertDatabaseHas('ncr_approvals', [
            'ncr_report_id' => $report->id,
            'approval_stage' => 'DESTRUCTION_COMMITTEE_APPROVAL',
            'decision' => 'APPROVED',
            'corrective_action_cycle_id' => $cycle->id,
            'corrective_action_plan_snapshot_id' => $snapshot->id,
        ]);
        $this->assertSame(1, $report->correctiveActions()
            ->where('corrective_action_cycle_id', $cycle->id)
            ->where('action_status', 'IN_PROGRESS')
            ->count());
    }

    public function test_rejected_destruction_requires_a_new_plan_submission_and_new_destruction_decision(): void
    {
        $report = $this->pendingDestructionApproval();
        $cycle = $report->currentCorrectiveActionCycle()->firstOrFail();
        $firstSnapshot = $cycle->snapshots()->latest('revision_no')->firstOrFail();
        $destructionApprover = $this->user(['ncr.destruction.approve']);

        $this->actingAs($destructionApprover)
            ->post(route('ncr.destruction-decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'REJECTED',
                'decision_note' => 'Revise the plan before destruction.',
            ])
            ->assertRedirect();

        $report->refresh();
        $owner = $report->correctiveActions()->firstOrFail()->owner;
        $this->actingAs($owner)
            ->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])
            ->assertRedirect();

        $secondSnapshot = $cycle->fresh()->snapshots()->latest('revision_no')->firstOrFail();
        $this->assertSame(2, (int) $secondSnapshot->revision_no);
        $this->assertSame($firstSnapshot->plan_payload, $firstSnapshot->fresh()->plan_payload);

        $approver = $this->caApprover();
        $report->refresh();
        $this->actingAs($approver)
            ->post(route('ncr.corrective-actions.decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'APPROVED',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('PENDING_DESTRUCTION_APPROVAL', $report->current_status);
        $this->assertDatabaseMissing('ncr_approvals', [
            'ncr_report_id' => $report->id,
            'approval_stage' => 'DESTRUCTION_COMMITTEE_APPROVAL',
            'decision' => 'APPROVED',
            'corrective_action_plan_snapshot_id' => $secondSnapshot->id,
        ]);
        $this->actingAs($owner)
            ->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])
            ->assertStatus(409);
    }

    public function test_destruction_decision_requires_its_dedicated_permission_without_writing_an_approval(): void
    {
        $report = $this->pendingDestructionApproval();
        $unprivileged = $this->user([]);

        $this->actingAs($unprivileged)
            ->post(route('ncr.destruction-decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'APPROVED',
            ])
            ->assertForbidden();

        $this->assertSame('PENDING_DESTRUCTION_APPROVAL', $report->fresh()->current_status);
        $this->assertDatabaseCount('ncr_approvals', 2);
    }

    public function test_approved_destruction_failure_after_approval_write_rolls_back_every_workflow_write(): void
    {
        $this->assertDestructionFailureRollsBack('APPROVED');
    }

    public function test_rejected_destruction_failure_after_approval_write_rolls_back_every_workflow_write(): void
    {
        $this->assertDestructionFailureRollsBack('REJECTED');
    }

    private function assertDestructionFailureRollsBack(string $decision): void
    {
        $report = $this->pendingDestructionApproval();
        $cycle = $report->currentCorrectiveActionCycle()->firstOrFail();
        $snapshot = $cycle->snapshots()->latest('revision_no')->firstOrFail();
        $action = $report->correctiveActions()->where('corrective_action_cycle_id', $cycle->id)->firstOrFail();
        $approver = $this->user(['ncr.destruction.approve']);
        $before = [
            'status' => $report->current_status,
            'version' => $report->version_no,
            'owner_user' => $report->current_owner_user_id,
            'owner_department' => $report->current_owner_department_id,
            'cycle_status' => $cycle->status,
            'snapshot_count' => $cycle->snapshots()->count(),
            'approval_count' => $report->approvals()->count(),
            'history_count' => $report->statusHistory()->count(),
            'audit_count' => \DB::table('audit_logs')->where('auditable_type', NcrReport::class)->where('auditable_id', $report->id)->count(),
            'action_status' => $action->action_status,
        ];

        config(['ncr.testing.fail_destruction_decision_after_approval_write' => true]);
        try {
            $this->actingAs($approver)
                ->post(route('ncr.destruction-decision', $report), [
                    'version_no' => $report->version_no,
                    'decision' => $decision,
                    'decision_note' => 'Injected failure must roll back.',
                ])
                ->assertStatus(500);
        } finally {
            config(['ncr.testing.fail_destruction_decision_after_approval_write' => false]);
        }

        $report->refresh();
        $this->assertSame($before['status'], $report->current_status);
        $this->assertSame($before['version'], $report->version_no);
        $this->assertSame($before['owner_user'], $report->current_owner_user_id);
        $this->assertSame($before['owner_department'], $report->current_owner_department_id);
        $this->assertSame($before['cycle_status'], $cycle->fresh()->status);
        $this->assertSame($before['snapshot_count'], $cycle->snapshots()->count());
        $this->assertSame($before['approval_count'], $report->approvals()->count());
        $this->assertSame($before['history_count'], $report->statusHistory()->count());
        $this->assertSame($before['audit_count'], \DB::table('audit_logs')->where('auditable_type', NcrReport::class)->where('auditable_id', $report->id)->count());
        $this->assertSame($before['action_status'], $action->fresh()->action_status);
        $this->assertSame($snapshot->plan_payload, $snapshot->fresh()->plan_payload);
    }

    private function pendingDestructionApproval(): NcrReport
    {
        $reporter = $this->user(['ncr.create', 'ncr.submit']);

        $this->actingAs($reporter)->post(route('ncr.store'), [
            'report_date' => now()->format('Y-m-d'),
            'found_date' => now()->format('Y-m-d'),
            'problem_type_id' => $this->problem->id,
            'severity_level_id' => $this->severity->id,
            'problem_description' => 'พบความผิดปกติ',
            'items' => [[
                'product_id' => Product::firstOrFail()->id,
                'product_name' => 'สินค้า A',
                'defect_quantity' => 1,
                'total_quantity' => 10,
                'unit_name' => 'PCS',
            ]],
        ])->assertRedirect();

        $report = NcrReport::firstOrFail();
        $this->actingAs($reporter)
            ->post(route('ncr.submit', $report), ['version_no' => $report->version_no])
            ->assertRedirect();

        $reviewer = $this->user(['ncr.review', 'ncr.disposition.manage']);
        $report->refresh();
        $report->refresh();
        $this->actingAs($reviewer)
            ->post(route('ncr.initial-review', $report), [
                'version_no' => $report->version_no,
                'review_result' => 'COMPLETE',
                'disposition_type_id' => $this->scrapDisposition->id,
                'disposition_detail' => 'เสนอทำลายสินค้าที่ไม่เป็นไปตามข้อกำหนด',
                'responsible_department_id' => $this->department->id,
                'proposed_due_date' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertRedirect();

        $manager = $this->user(['ncr.disposition.approve']);
        $report->refresh();
        $this->actingAs($manager)
            ->post(route('ncr.manager-decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'APPROVED',
            ])
            ->assertRedirect();

        $report->refresh();
        $responsible = $reviewer;
        $responsible->givePermissionTo(Permission::findOrCreate('ncr.action.manage'));
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), [
            'version_no' => $report->version_no,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'เสนอวิธีการแก้ไขก่อนพิจารณาทำลาย',
            'responsible_department_id' => $this->department->id,
            'owner_user_id' => $responsible->id,
            'assignee_ids' => [$responsible->id],
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'progress_percent' => 0,
            'action_status' => 'OPEN',
        ])->assertRedirect();

        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), [
            'version_no' => $report->version_no,
        ])->assertRedirect();

        $approver = $this->caApprover();
        $report->refresh();
        $this->actingAs($approver)->post(route('ncr.corrective-actions.decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'APPROVED',
        ])->assertRedirect();

        return $report->fresh();
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['department_id' => $this->department->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }
        return $user;
    }

    private function caApprover(): User
    {
        $approver = $this->user(['ncr.action.manage']);
        $approver->update(['department_role' => 'DEPARTMENT_HEAD']);

        return $approver;
    }
}
