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
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrPhaseFourTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private ProblemType $problem;
    private SeverityLevel $severity;
    private DispositionType $dispositionType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Unit::create(['code' => 'PCS', 'name_th' => 'ชิ้น', 'is_active' => true]);
        Product::create(['code' => 'P001', 'name_th' => 'สินค้า A', 'is_active' => true]);
        $this->department = Department::create(['code' => 'OPS', 'name_th' => 'ฝ่ายปฏิบัติการ', 'is_active' => true]);
        $this->problem = ProblemType::create(['code' => 'PROCESS', 'name_th' => 'กระบวนการ', 'is_active' => true]);
        $this->severity = SeverityLevel::create(['code' => 'HIGH', 'name_th' => 'สูง', 'is_active' => true]);
        $this->dispositionType = DispositionType::create(['code' => 'REWORK', 'name_th' => 'แก้ไขงาน', 'is_active' => true]);
    }

    public function test_responsible_department_can_create_multiple_actions_before_department_head_approval_starts_workflow(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $payload = $this->actionPayload($actor, $report);

        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $payload)->assertRedirect();
        $report->refresh();
        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), array_merge($payload, ['version_no' => $report->version_no, 'action_type' => 'PREVENTIVE', 'corrective_action_detail' => 'ป้องกันการเกิดซ้ำ']))->assertRedirect();

        $report->refresh();
        $this->assertSame('DISPOSITION_APPROVED', $report->current_status);
        $this->assertCount(2, $report->correctiveActions);
        $this->assertDatabaseHas('ncr_action_progress_history', ['to_progress_percent' => 0, 'to_status' => 'OPEN']);

        $report = $this->approveCorrectiveActions($report, $actor);
        $this->assertSame('ACTION_IN_PROGRESS', $report->current_status);
        $this->assertSame(2, $report->correctiveActions()->where('action_status', 'APPROVED')->count());
    }

    public function test_corrective_action_form_uses_approved_department_and_keeps_owner_selectable(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $actor->givePermissionTo(Permission::findOrCreate('ncr.view'));

        $this->actingAs($actor)
            ->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('OPS - ฝ่ายปฏิบัติการ')
            ->assertSee('name="responsible_department_id" value="'.$this->department->id.'"', false)
            ->assertDontSee('<select id="corrective-action-new-department"', false)
            ->assertSee('<select id="corrective-action-new-owner" name="owner_user_id"', false);
    }

    public function test_action_owner_is_required_and_must_belong_to_responsible_department(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $payload = $this->actionPayload($actor, $report);

        $this->actingAs($actor)
            ->post(route('ncr.corrective-actions.store', $report), array_merge($payload, ['owner_user_id' => '']))
            ->assertSessionHasErrors('owner_user_id');

        $otherDepartment = Department::create(['code' => 'OTHER-OWNER', 'name_th' => 'แผนกอื่น', 'is_active' => true]);
        $otherUser = $this->user([], $otherDepartment);
        $this->actingAs($actor)
            ->post(route('ncr.corrective-actions.store', $report), array_merge($payload, ['owner_user_id' => $otherUser->id]))
            ->assertSessionHasErrors('owner_user_id');

        $this->assertDatabaseCount('ncr_corrective_actions', 0);
        $this->actingAs($actor)
            ->post(route('ncr.corrective-actions.store', $report), $payload)
            ->assertRedirect();
        $this->assertDatabaseHas('ncr_corrective_actions', ['ncr_report_id' => $report->id, 'owner_user_id' => $actor->id]);
    }

    public function test_action_owner_dropdown_only_lists_approved_department_users(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $actor->givePermissionTo(Permission::findOrCreate('ncr.view'));
        $otherDepartment = Department::create(['code' => 'OTHER-UI', 'name_th' => 'แผนกอื่น', 'is_active' => true]);
        $otherUser = $this->user([], $otherDepartment);
        $response = $this->actingAs($actor)->get(route('ncr.show', $report))->assertOk();
        $response->assertSee('name="owner_user_id"', false);
        $response->assertSee('value="'.$actor->id.'"', false);
        $response->assertDontSee('value="'.$otherUser->id.'">'.$otherUser->name, false);
    }

    public function test_server_rejects_corrective_action_department_different_from_approved_disposition(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $otherDepartment = Department::create(['code' => 'QA2', 'name_th' => 'ฝ่ายอื่น', 'is_active' => true]);
        $payload = array_merge($this->actionPayload($actor, $report), [
            'responsible_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($actor)
            ->post(route('ncr.corrective-actions.store', $report), $payload)
            ->assertSessionHasErrors('responsible_department_id');

        $this->assertDatabaseCount('ncr_corrective_actions', 0);
    }

    public function test_initial_action_plan_submission_creates_an_immutable_current_cycle_snapshot(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);

        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))
            ->assertRedirect();

        $report->refresh();
        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])
            ->assertRedirect();

        $this->assertDatabaseHas('ncr_corrective_action_cycles', [
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'status' => 'PENDING_APPROVAL',
        ]);
        $cycleId = (int) DB::table('ncr_corrective_action_cycles')
            ->where('ncr_report_id', $report->id)
            ->value('id');

        $this->assertDatabaseHas('ncr_corrective_action_plan_snapshots', [
            'corrective_action_cycle_id' => $cycleId,
            'revision_no' => 1,
        ]);
    }

    public function test_snapshot_captures_stable_metadata_for_attachments_present_at_submission(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), array_merge(
            $this->actionPayload($responsible, $report),
            ['attachments' => [UploadedFile::fake()->create('plan-evidence.pdf', 12, 'application/pdf')]]
        ))->assertRedirect();
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();

        $snapshot = DB::table('ncr_corrective_action_plan_snapshots')->first();
        $payload = json_decode($snapshot->plan_payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('plan-evidence.pdf', $payload[0]['attachments'][0]['original_filename']);
        $this->assertSame('application/pdf', $payload[0]['attachments'][0]['mime_type']);
        $this->assertSame(12 * 1024, $payload[0]['attachments'][0]['file_size']);
        $this->assertArrayNotHasKey('storage_path', $payload[0]['attachments'][0]);
    }

    public function test_disposition_approval_creates_draft_cycle_one_and_actions_belong_to_it(): void
    {
        $report = $this->approvedReport();

        $this->assertDatabaseHas('ncr_corrective_action_cycles', [
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'origin' => 'INITIAL',
            'status' => 'DRAFT',
        ]);
        $cycleId = (int) DB::table('ncr_corrective_action_cycles')
            ->where('ncr_report_id', $report->id)
            ->value('id');

        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))
            ->assertRedirect();

        $this->assertDatabaseHas('ncr_corrective_actions', [
            'ncr_report_id' => $report->id,
            'corrective_action_cycle_id' => $cycleId,
        ]);
    }

    public function test_rejection_and_resubmission_create_new_snapshot_and_link_approval_to_current_snapshot(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();

        $cycleId = (int) DB::table('ncr_corrective_action_cycles')->where('ncr_report_id', $report->id)->value('id');
        $snapshotOneId = (int) DB::table('ncr_corrective_action_plan_snapshots')->where('corrective_action_cycle_id', $cycleId)->where('revision_no', 1)->value('id');
        $approver = $this->caApprover();
        $report->refresh();
        $this->actingAs($approver)->post(route('ncr.corrective-actions.decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'REJECTED',
            'decision_note' => 'ต้องปรับปรุงแผน',
        ])->assertRedirect();
        $this->assertDatabaseHas('ncr_corrective_action_cycles', ['id' => $cycleId, 'status' => 'REJECTED']);
        $this->assertDatabaseHas('ncr_approvals', ['corrective_action_cycle_id' => $cycleId, 'corrective_action_plan_snapshot_id' => $snapshotOneId, 'decision' => 'REJECTED']);

        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), array_merge(
            $this->actionPayload($responsible, $report),
            ['action_type' => 'PREVENTIVE', 'corrective_action_detail' => 'เพิ่ม Action หลังถูกส่งกลับแก้ไข']
        ))->assertRedirect();
        $this->assertSame(2, $report->correctiveActions()->count());

        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();
        $snapshotTwoId = (int) DB::table('ncr_corrective_action_plan_snapshots')->where('corrective_action_cycle_id', $cycleId)->where('revision_no', 2)->value('id');
        $this->assertNotSame($snapshotOneId, $snapshotTwoId);
    }

    public function test_approval_is_linked_to_the_current_cycle_and_its_submitted_snapshot(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();

        $cycleId = (int) DB::table('ncr_corrective_action_cycles')->where('ncr_report_id', $report->id)->value('id');
        $snapshotId = (int) DB::table('ncr_corrective_action_plan_snapshots')->where('corrective_action_cycle_id', $cycleId)->where('revision_no', 1)->value('id');
        $approver = $this->caApprover();
        $report->refresh();
        $this->actingAs($approver)->post(route('ncr.corrective-actions.decision', $report), ['version_no' => $report->version_no, 'decision' => 'APPROVED'])->assertRedirect();

        $this->assertDatabaseHas('ncr_corrective_action_cycles', ['id' => $cycleId, 'status' => 'APPROVED']);
        $this->assertDatabaseHas('ncr_approvals', [
            'ncr_report_id' => $report->id,
            'corrective_action_cycle_id' => $cycleId,
            'corrective_action_plan_snapshot_id' => $snapshotId,
            'approval_stage' => 'CORRECTIVE_ACTION_APPROVAL',
            'decision' => 'APPROVED',
        ]);
    }

    public function test_duplicate_or_stale_plan_submission_cannot_create_another_snapshot(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();

        $report->refresh();
        $submittedVersion = $report->version_no;
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $submittedVersion])->assertRedirect();
        $this->assertDatabaseCount('ncr_corrective_action_plan_snapshots', 1);

        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $submittedVersion])->assertStatus(409);
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertStatus(409);
        $this->assertDatabaseCount('ncr_corrective_action_plan_snapshots', 1);
    }

    public function test_draft_plan_edit_is_allowed_for_the_current_cycle(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report->refresh();
        $action = $report->correctiveActions()->firstOrFail();

        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['corrective_action_detail' => 'แผนฉบับแก้ไขในร่าง']
        ))->assertRedirect();

        $this->assertSame('แผนฉบับแก้ไขในร่าง', $action->fresh()->corrective_action_detail);
        $this->assertNotNull($action->fresh()->corrective_action_cycle_id);
    }

    public function test_pending_plan_modification_is_rejected_without_changing_snapshot_or_action(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report->refresh();
        $action = $report->correctiveActions()->firstOrFail();
        $originalDetail = $action->corrective_action_detail;
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();
        $report->refresh();
        $snapshot = DB::table('ncr_corrective_action_plan_snapshots')->first();

        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['corrective_action_detail' => 'ต้องห้ามแก้ไขระหว่างรออนุมัติ']
        ))->assertStatus(409);

        $this->assertSame($originalDetail, $action->fresh()->corrective_action_detail);
        $this->assertSame($snapshot->plan_payload, DB::table('ncr_corrective_action_plan_snapshots')->first()->plan_payload);
    }

    public function test_approved_plan_modification_is_rejected_but_authorized_status_update_is_allowed(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report = $this->approveCorrectiveActions($report->fresh(), $responsible);
        $action = $report->correctiveActions()->firstOrFail();
        $originalDetail = $action->corrective_action_detail;
        $snapshotPayload = DB::table('ncr_corrective_action_plan_snapshots')->first()->plan_payload;

        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['corrective_action_detail' => 'ต้องห้ามแก้แผนที่อนุมัติแล้ว']
        ))->assertStatus(409);
        $this->assertSame($originalDetail, $action->fresh()->corrective_action_detail);
        $this->assertSame($snapshotPayload, DB::table('ncr_corrective_action_plan_snapshots')->first()->plan_payload);

        $report->refresh();
        $completedDate = now()->toDateString();
        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['progress_percent' => 50, 'action_status' => 'IN_PROGRESS', 'completed_date' => $completedDate]
        ))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, (int) $action->fresh()->progress_percent);
        $this->assertSame('IN_PROGRESS', $action->fresh()->action_status);
        $this->assertSame($completedDate, $action->fresh()->completed_date->format('Y-m-d'));
    }

    public function test_action_execution_fields_are_only_editable_after_the_plan_is_approved(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $responsible->givePermissionTo(Permission::findOrCreate('ncr.view'));

        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))
            ->assertRedirect();

        $this->actingAs($responsible)
            ->get(route('ncr.show', $report->fresh()))
            ->assertOk()
            ->assertDontSee('<select name="action_status"', false);

        $report = $this->approveCorrectiveActions($report->fresh(), $responsible);

        $approvedPage = $this->actingAs($responsible)
            ->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('<select name="action_status"', false)
            ->assertSee('แผน Corrective Action อนุมัติแล้ว ข้อมูลแผน ผู้รับผิดชอบ และไฟล์หลักฐานถูกล็อก', false);

        $approvedHtml = $approvedPage->getContent();
        $this->assertStringContainsString('value="IN_PROGRESS" selected', $approvedHtml);
        $this->assertStringContainsString('readonly', $approvedHtml);
        $this->assertStringContainsString('disabled', $approvedHtml);
    }

    public function test_approved_plan_status_update_accepts_datetime_local_acknowledgement_value(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $acknowledgedAt = '2026-09-17 08:50:00';
        $payload = array_merge($this->actionPayload($responsible, $report), [
            'responsible_acknowledged_at' => $acknowledgedAt,
        ]);

        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.store', $report), $payload)
            ->assertRedirect();
        $report = $this->approveCorrectiveActions($report->fresh(), $responsible);
        $action = $report->correctiveActions()->firstOrFail();

        $this->actingAs($responsible)
            ->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
                $payload,
                [
                    'version_no' => $report->version_no,
                    'responsible_acknowledged_at' => '2026-09-17T08:50',
                    'progress_percent' => 50,
                    'action_status' => 'IN_PROGRESS',
                ]
            ))
            ->assertRedirect();

        $this->assertSame(0, (int) $action->fresh()->progress_percent);
        $this->assertSame('IN_PROGRESS', $action->fresh()->action_status);
    }

    public function test_scrap_progress_update_is_rejected_before_destruction_approval(): void
    {
        $scrap = DispositionType::create(['code' => 'SCRAP', 'name_th' => 'เสนอทำลาย', 'is_active' => true]);
        $report = $this->approvedReport($scrap);
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report = $this->approveCorrectiveActions($report->fresh(), $responsible);
        $action = $report->correctiveActions()->firstOrFail();

        $this->assertSame('PENDING_DESTRUCTION_APPROVAL', $report->current_status);
        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['progress_percent' => 50, 'action_status' => 'IN_PROGRESS']
        ))->assertStatus(409);
        $this->assertSame(0, (int) $action->fresh()->progress_percent);
        $this->assertSame('APPROVED', $action->fresh()->action_status);
    }

    public function test_approved_plan_attachment_or_owner_change_cannot_bypass_plan_guard(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->openingManager($report);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))->assertRedirect();
        $report = $this->approveCorrectiveActions($report->fresh(), $responsible);
        $action = $report->correctiveActions()->firstOrFail();
        $additionalAssignee = $this->user([]);

        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['owner_user_id' => $additionalAssignee->id, 'progress_percent' => 50, 'action_status' => 'IN_PROGRESS']
        ))->assertStatus(409);

        $this->actingAs($responsible)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($responsible, $report),
            ['attachments' => [UploadedFile::fake()->create('late-plan-change.pdf', 10, 'application/pdf')], 'progress_percent' => 50, 'action_status' => 'IN_PROGRESS']
        ))->assertStatus(409);
        $this->assertSame($responsible->id, $action->fresh()->owner_user_id);
        $this->assertSame(0, $action->fresh()->attachments()->count());
    }

    public function test_responsible_department_revises_corrective_action_after_department_head_rejection(): void
    {
        $report = $this->approvedReport();
        $responsible = $this->user(['ncr.view', 'ncr.action.manage']);

        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($responsible, $report))
            ->assertRedirect();

        $report->refresh();
        $responsible->revokePermissionTo('ncr.action.manage');
        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])
            ->assertForbidden();
        $responsible->givePermissionTo(Permission::findOrCreate('ncr.action.manage'));

        $this->actingAs($responsible)
            ->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])
            ->assertRedirect();
        $this->assertDatabaseHas('ncr_status_history', [
            'ncr_report_id' => $report->id,
            'action_code' => 'SUBMIT_CORRECTIVE_ACTION_APPROVAL',
            'changed_by' => $responsible->id,
        ]);
        $this->actingAs($responsible)
            ->get(route('ncr.show', $report->fresh()))
            ->assertOk()
            ->assertSee('ส่ง Corrective Action ขออนุมัติ');

        $approver = $this->caApprover();
        $report->refresh();
        $this->actingAs($approver)
            ->post(route('ncr.corrective-actions.decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'REJECTED',
                'decision_note' => 'กรุณาแก้ไขวิธีการและระยะเวลาดำเนินการ',
            ])->assertRedirect();

        $report->refresh();
        $this->actingAs($responsible)
            ->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('แก้ไข Corrective Action ตามเหตุผลที่ส่งกลับ')
            ->assertSee('เหตุผลที่ส่งกลับแก้ไข Corrective Action:')
            ->assertSee('กรุณาแก้ไขวิธีการและระยะเวลาดำเนินการ')
            ->assertSee('href="#corrective-action"', false)
            ->assertSee('data-ncr-dialog-open="edit-corrective-action-'.$report->correctiveActions()->first()->id.'"', false);

        $action = $report->correctiveActions()->firstOrFail();
        $this->actingAs($responsible)
            ->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
                $this->actionPayload($responsible, $report),
                ['corrective_action_detail' => 'วิธีการแก้ไขฉบับปรับปรุง']
            ))->assertRedirect();

        $this->assertSame('OPEN', $action->fresh()->action_status);
        $this->assertSame('วิธีการแก้ไขฉบับปรับปรุง', $action->fresh()->corrective_action_detail);
    }

    public function test_action_completion_does_not_require_a_completion_note(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $payload = array_merge($this->actionPayload($actor, $report), ['action_status' => 'COMPLETED', 'progress_percent' => 80]);

        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $payload)->assertRedirect();
        $this->assertDatabaseCount('ncr_corrective_actions', 1);
        $this->assertNull($report->correctiveActions()->firstOrFail()->completion_note);
    }

    public function test_submit_for_verification_does_not_require_attachment(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $payload = $this->actionPayload($actor, $report);
        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $payload);

        $report = $this->approveCorrectiveActions($report->fresh(), $actor);
        $action = $report->correctiveActions()->latest('action_no')->firstOrFail();
        $this->actingAs($actor)->put(route('ncr.corrective-actions.update', [$report, $action]), array_merge(
            $this->actionPayload($actor, $report),
            [
                'action_status' => 'COMPLETED',
                'completed_date' => now()->format('Y-m-d'),
                'completion_note' => 'ดำเนินการเสร็จสิ้นแล้ว',
            ]
        ))->assertRedirect();
        $this->assertSame(100, (int) $action->fresh()->progress_percent);
        $report->refresh();
        $this->actingAs($actor)->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])->assertRedirect();
        $this->assertSame('PENDING_ACTION_VERIFICATION', $report->fresh()->current_status);
    }

    public function test_incomplete_latest_action_cannot_be_submitted_for_verification(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($actor, $report));

        $report = $this->approveCorrectiveActions($report->fresh(), $actor);
        $this->actingAs($actor)
            ->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])
            ->assertSessionHasErrors('actions');

        $actor->givePermissionTo(Permission::findOrCreate('ncr.view'));
        $this->actingAs($actor)
            ->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('ยังส่งตรวจไม่ได้ — แก้ Required Actions ต่อไปนี้ให้ครบ')
            ->assertSee('สถานะยังไม่เป็น “เสร็จแล้ว”')
            ->assertDontSee('Progress')
            ->assertDontSee('ยังไม่ได้บันทึกผลการดำเนินงาน')
            ->assertSee('data-ncr-dialog-open="edit-corrective-action-'.$report->correctiveActions()->latest('action_no')->value('id').'"', false);

        $this->assertSame('ACTION_IN_PROGRESS', $report->fresh()->current_status);
        $this->assertSame('APPROVED', $report->correctiveActions()->latest('action_no')->firstOrFail()->action_status);
    }

    public function test_only_latest_action_can_be_updated(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($actor, $report));
        $report->refresh();
        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), array_merge(
            $this->actionPayload($actor, $report),
            ['action_type' => 'PREVENTIVE']
        ));
        $this->assertSame(2, $report->correctiveActions()->count());

        $report = $this->approveCorrectiveActions($report->fresh(), $actor);
        $firstAction = $report->correctiveActions()->orderBy('action_no')->firstOrFail();

        $this->actingAs($actor)
            ->put(route('ncr.corrective-actions.update', [$report, $firstAction]), array_merge(
                $this->actionPayload($actor, $report),
                ['action_status' => 'IN_PROGRESS']
            ))
            ->assertSessionHasNoErrors()
            ->assertStatus(409);

        $this->assertSame('APPROVED', $firstAction->fresh()->action_status);
    }

    public function test_action_management_requires_permission(): void
    {
        $report = $this->approvedReport();
        $actor = $this->user([]);

        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $this->actionPayload($actor, $report))->assertForbidden();
    }

    public function test_other_department_cannot_create_update_or_submit_actions(): void
    {
        $report = $this->approvedReport();
        $responsibleActor = $this->openingManager($report);
        $otherDepartment = Department::create(['code' => 'QA2', 'name_th' => 'ฝ่ายอื่น', 'is_active' => true]);
        $otherActor = $this->user(['ncr.action.manage'], $otherDepartment);

        $invalidPayload = array_merge($this->actionPayload($otherActor, $report), [
            'responsible_department_id' => $otherDepartment->id,
        ]);
        $this->actingAs($otherActor)
            ->post(route('ncr.corrective-actions.store', $report), $invalidPayload)
            ->assertRedirect()
            ->assertSessionHasErrors('responsible_department_id');
        $this->assertSame(0, $report->correctiveActions()->count());

        $this->actingAs($responsibleActor)->post(
            route('ncr.corrective-actions.store', $report),
            $this->actionPayload($responsibleActor, $report)
        )->assertRedirect();
        $report->refresh();
        $action = $report->correctiveActions()->firstOrFail();
        $originalDetail = $action->corrective_action_detail;

        $this->actingAs($otherActor)->put(
            route('ncr.corrective-actions.update', [$report, $action]),
            array_merge($invalidPayload, ['version_no' => $report->version_no])
        )->assertForbidden();
        $this->assertSame($originalDetail, $action->fresh()->corrective_action_detail);
        $this->actingAs($otherActor)->post(
            route('ncr.corrective-actions.submit-approval', $report),
            ['version_no' => $report->version_no]
        )->assertForbidden();
    }

    public function test_stale_version_is_rejected_without_creating_another_action(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $payload = $this->actionPayload($actor, $report);

        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $payload)->assertRedirect();
        $this->actingAs($actor)->post(route('ncr.corrective-actions.store', $report), $payload)->assertStatus(409);

        $this->assertDatabaseCount('ncr_corrective_actions', 1);
    }

    public function test_stale_version_is_rejected_for_action_update_and_submit(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $this->actingAs($actor)->post(
            route('ncr.corrective-actions.store', $report),
            $this->actionPayload($actor, $report)
        )->assertRedirect();

        $report->refresh();
        $action = $report->correctiveActions()->firstOrFail();
        $stalePayload = array_merge($this->actionPayload($actor, $report), [
            'progress_percent' => 25,
            'action_status' => 'IN_PROGRESS',
        ]);
        $this->actingAs($actor)->put(
            route('ncr.corrective-actions.update', [$report, $action]),
            $stalePayload
        )->assertRedirect();

        $this->actingAs($actor)->put(
            route('ncr.corrective-actions.update', [$report, $action]),
            $stalePayload
        )->assertStatus(409);
        $this->actingAs($actor)->post(
            route('ncr.corrective-actions.submit-approval', $report),
            ['version_no' => $stalePayload['version_no']]
        )->assertStatus(409);
        $this->assertSame(0, (int) $action->fresh()->progress_percent);
    }

    public function test_action_attachments_are_limited_and_download_requires_view_permission(): void
    {
        $report = $this->approvedReport();
        $actor = $this->openingManager($report);
        $tooManyFiles = [];
        for ($index = 1; $index <= 11; $index++) {
            $tooManyFiles[] = UploadedFile::fake()->create("evidence-{$index}.pdf", 10, 'application/pdf');
        }

        $this->actingAs($actor)->post(
            route('ncr.corrective-actions.store', $report),
            array_merge($this->actionPayload($actor, $report), ['attachments' => $tooManyFiles])
        )->assertSessionHasErrors('attachments');

        $this->actingAs($actor)->post(
            route('ncr.corrective-actions.store', $report),
            array_merge($this->actionPayload($actor, $report), [
                'attachments' => [UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')],
            ])
        )->assertRedirect();
        $report->refresh();
        $action = $report->correctiveActions()->firstOrFail();
        $attachment = $action->attachments()->firstOrFail();
        $additionalFiles = [];
        for ($index = 1; $index <= 10; $index++) {
            $additionalFiles[] = UploadedFile::fake()->create("additional-{$index}.pdf", 10, 'application/pdf');
        }
        $this->actingAs($actor)->put(
            route('ncr.corrective-actions.update', [$report, $action]),
            array_merge($this->actionPayload($actor, $report), ['attachments' => $additionalFiles])
        )->assertSessionHasErrors('attachments');
        $this->assertSame(1, $action->attachments()->where('is_active', true)->count());

        $this->actingAs($actor)->get(
            route('ncr.corrective-action-attachments.download', [$report, $attachment])
        )->assertForbidden();
    }

    private function approvedReport(?DispositionType $dispositionType = null): NcrReport
    {
        $reporter = $this->user(['ncr.create', 'ncr.submit']);
        $this->actingAs($reporter)->post(route('ncr.store'), [
            'report_date' => now()->format('Y-m-d'),
            'found_date' => now()->format('Y-m-d'),
            'problem_type_id' => $this->problem->id,
            'severity_level_id' => $this->severity->id,
            'problem_description' => 'พบความผิดปกติ',
            'items' => [['product_id' => Product::firstOrFail()->id, 'product_name' => 'สินค้า A', 'defect_quantity' => 1, 'total_quantity' => 10, 'unit_name' => 'PCS']],
        ]);
        $report = NcrReport::firstOrFail();
        $this->actingAs($reporter)->post(route('ncr.submit', $report), ['version_no' => $report->version_no]);
        $reviewer = $this->user(['ncr.review', 'ncr.disposition.manage']);
        $this->actingAs($reviewer)->post(route('ncr.initial-review', $report->fresh()), [
            'version_no' => $report->fresh()->version_no,
            'review_result' => 'COMPLETE',
            'disposition_type_id' => ($dispositionType ?: $this->dispositionType)->id,
            'disposition_detail' => 'ดำเนินการแก้ไข',
            'responsible_department_id' => $this->department->id,
            'proposed_due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);
        $manager = $this->user(['ncr.disposition.approve']);
        $this->actingAs($manager)->post(route('ncr.manager-decision', $report->fresh()), ['version_no' => $report->fresh()->version_no, 'decision' => 'APPROVED']);
        return $report->fresh();
    }

    private function actionPayload(User $actor, NcrReport $report): array
    {
        return [
            'version_no' => $report->version_no,
            'action_type' => 'CORRECTIVE',
            'root_cause_detail' => 'สาเหตุจากกระบวนการ',
            'immediate_correction' => 'แยกสินค้าและแก้ไขจุดผิดพลาด',
            'corrective_action_detail' => 'ปรับปรุงขั้นตอนการทำงาน',
            'preventive_action_detail' => 'เพิ่มจุดตรวจยืนยัน',
            'responsible_department_id' => $this->department->id,
            'owner_user_id' => $actor->id,
            'assignee_ids' => [$actor->id],
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'progress_percent' => 0,
            'action_status' => 'OPEN',
        ];
    }

    private function approveCorrectiveActions(NcrReport $report, User $responsible): NcrReport
    {
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

    private function user(array $permissions, ?Department $department = null): User
    {
        $user = User::factory()->create(['department_id' => ($department ?: $this->department)->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }
        return $user;
    }

    private function caApprover(?Department $department = null): User
    {
        $approver = $this->user(['ncr.action.manage'], $department);
        $approver->update(['department_role' => 'DEPARTMENT_HEAD']);

        return $approver;
    }

    private function openingManager(NcrReport $report): User
    {
        $manager = User::findOrFail($report->initialReviews()->latest('id')->value('reviewed_by'));
        $manager->givePermissionTo(Permission::findOrCreate('ncr.action.manage'));

        return $manager;
    }
}
