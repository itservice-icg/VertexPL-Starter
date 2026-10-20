<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DispositionType;
use App\Models\NcrActionAttachment;
use App\Models\NcrCorrectiveAction;
use App\Models\NcrCorrectiveActionCycle;
use App\Models\NcrCorrectiveActionPlanSnapshot;
use App\Models\NcrApproval;
use App\Models\NcrDisposition;
use App\Models\NcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrPhaseFiveTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private DispositionType $dispositionType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->department = Department::create(['code' => 'OPS', 'name_th' => 'ฝ่ายปฏิบัติการ', 'is_active' => true]);
        $this->dispositionType = DispositionType::create(['code' => 'REWORK', 'name_th' => 'แก้ไขงาน', 'is_active' => true]);
    }

    public function test_action_verification_requires_permission_and_pending_status(): void
    {
        $report = $this->pendingVerification();
        $user = $this->user([]);

        $this->actingAs($user)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))->assertForbidden();

        $verifier = $this->user(['ncr.view', 'ncr.action.verify']);
        $report->update(['current_status' => 'ACTION_IN_PROGRESS']);
        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))->assertStatus(409);
        $this->assertDatabaseCount('ncr_action_verifications', 0);
    }

    public function test_verification_requires_detail_for_both_passed_and_failed_results(): void
    {
        $report = $this->pendingVerification();
        $verifier = $this->user(['ncr.view', 'ncr.action.verify']);

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no,
            'result' => 'PASSED',
        ])->assertSessionHasErrors('verification_detail');

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no,
            'result' => 'FAILED',
        ])->assertSessionHasErrors('verification_detail');

        $this->actingAs($verifier)->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('x-data=\'{"result":"FAILED"}\'', false)
            ->assertSee('เหตุผลที่ส่งกลับ Rework');

        $this->assertDatabaseCount('ncr_action_verifications', 0);
    }

    public function test_passed_verification_records_history_and_sends_ncr_to_qa_queue(): void
    {
        $report = $this->pendingVerification();
        $verifier = $this->user(['ncr.view', 'ncr.action.verify']);

        $this->actingAs($verifier)->get(route('ncr.show', $report))->assertOk()->assertSee('Action Verification');
        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))->assertRedirect();

        $report->refresh();
        $this->assertSame('QA_VERIFIED', $report->current_status);
        $this->assertSame(2, (int) $report->version_no);
        $this->assertNull($report->current_owner_user_id);
        $this->assertNull($report->current_owner_department_id);
        $this->assertDatabaseHas('ncr_action_verifications', [
            'ncr_report_id' => $report->id,
            'verification_round' => 1,
            'result' => 'PASSED',
            'verified_by' => $verifier->id,
        ]);
        $this->assertDatabaseHas('ncr_status_history', ['ncr_report_id' => $report->id, 'action_code' => 'VERIFY_ACTION_PASSED', 'to_status' => 'QA_VERIFIED']);
        $this->assertDatabaseHas('ncr_corrective_actions', ['ncr_report_id' => $report->id, 'action_status' => 'VERIFIED']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'ncr', 'action' => 'ncr.verify_action_passed']);
    }

    public function test_qa_can_view_ncr_after_passing_or_failing_action_verification(): void
    {
        $qaDepartment = Department::create(['code' => 'QA-VERIFY', 'name_th' => 'ฝ่ายประกันคุณภาพ', 'is_active' => true]);
        $qa = $this->user(['ncr.view', 'ncr.action.verify', 'ncr.qa.followup'], $qaDepartment);

        $passedReport = $this->pendingVerification('NCR-2026-VERIFY-PASS');
        $this->actingAs($qa)
            ->post(route('ncr.corrective-actions.verify', $passedReport), $this->verificationPayload($passedReport))
            ->assertRedirect(route('ncr.show', $passedReport));
        $this->assertSame('QA_VERIFIED', $passedReport->fresh()->current_status);
        $this->actingAs($qa)->get(route('ncr.show', $passedReport))->assertOk();

        $failedReport = $this->pendingVerification('NCR-2026-VERIFY-FAIL');
        $this->actingAs($qa)
            ->post(route('ncr.corrective-actions.verify', $failedReport), [
                'version_no' => $failedReport->version_no,
                'result' => 'FAILED',
                'verification_detail' => 'ตรวจหลักฐานแล้วยังไม่ผ่าน',
                'return_reason' => 'กรุณาแก้ไขหลักฐานและส่งตรวจใหม่',
            ])
            ->assertRedirect(route('ncr.show', $failedReport));
        $this->assertSame('RETURNED_FOR_REWORK', $failedReport->fresh()->current_status);
        $this->actingAs($qa)->get(route('ncr.show', $failedReport))->assertOk();
    }
    public function test_qa_can_view_ncr_after_closing_it(): void
    {
        $qaDepartment = Department::create(['code' => 'QA-CLOSE', 'name_th' => 'ฝ่ายประกันคุณภาพ', 'is_active' => true]);
        $qa = $this->user(['ncr.view', 'ncr.action.verify', 'ncr.qa.followup', 'ncr.close'], $qaDepartment);
        $report = $this->pendingVerification('NCR-2026-QA-CLOSE');

        $this->actingAs($qa)
            ->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))
            ->assertRedirect();
        $report->refresh();

        $this->actingAs($qa)
            ->post(route('ncr.qa-followup.store', $report), [
                'version_no' => $report->version_no,
                'followup_date' => now()->toDateString(),
                'effectiveness_result' => 'EFFECTIVE',
                'checklist_confirmed' => '1',
            ])
            ->assertRedirect();
        $this->assertSame('CLOSED', $report->fresh()->current_status);
        $this->assertDatabaseHas('ncr_qa_followups', [
            'ncr_report_id' => $report->id,
            'effectiveness_result' => 'EFFECTIVE',
            'followup_detail' => '',
        ]);
        $this->assertDatabaseHas('ncr_closures', ['ncr_report_id' => $report->id, 'closure_result' => 'CLOSED']);
        $this->actingAs($qa)->get(route('ncr.show', $report))->assertOk();
    }
    public function test_failed_verification_supports_start_rework_and_second_verification_round(): void
    {
        $report = $this->pendingVerification();
        $verifier = $this->user(['ncr.action.verify']);
        $responsible = $this->user(['ncr.view', 'ncr.action.manage']);

        NcrApproval::create([
            'ncr_report_id' => $report->id,
            'disposition_id' => $report->dispositions()->value('id'),
            'approval_stage' => 'CORRECTIVE_ACTION_APPROVAL',
            'approval_level' => 1,
            'approver_id' => $verifier->id,
            'decision' => 'APPROVED',
            'decided_at' => now(),
        ]);

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no,
            'result' => 'FAILED',
            'verification_detail' => 'ตรวจหลักฐานแล้วไม่ครบ',
            'return_reason' => 'เพิ่มผลทดสอบหลังแก้ไข',
        ])->assertRedirect();

        $report->refresh();
        $this->assertSame('RETURNED_FOR_REWORK', $report->current_status);
        $this->assertSame($this->department->id, $report->current_owner_department_id);

        $this->actingAs($responsible)->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('NCR ถูกส่งกลับให้ทำ Rework')
            ->assertSee('เหตุผลที่ส่งกลับ:')
            ->assertSee('ตรวจหลักฐานแล้วไม่ครบ')
            ->assertSee('href="#start-corrective-action-rework"', false)
            ->assertSee('id="start-corrective-action-rework"', false)
            ->assertSee('เริ่ม Rework');

        $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertRedirect();
        $report->refresh();
        $action = $report->correctiveActions()->where('corrective_action_cycle_id', $report->current_corrective_action_cycle_id)->firstOrFail();
        $this->assertSame('DISPOSITION_APPROVED', $report->current_status);
        $this->assertSame('OPEN', $action->fresh()->action_status);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();
        $approver = $this->caApprover();
        $report->refresh();
        $this->actingAs($approver)->post(route('ncr.corrective-actions.decision', $report), ['version_no' => $report->version_no, 'decision' => 'APPROVED'])->assertRedirect();
        $report->refresh();
        $this->assertSame('ACTION_IN_PROGRESS', $report->current_status);
        $this->assertSame('APPROVED', $action->fresh()->action_status);

        $action->update(['action_status' => 'COMPLETED', 'progress_percent' => 100, 'completion_note' => 'ดำเนินการแก้ไขรอบสองครบแล้ว', 'completed_date' => now()->toDateString(), 'updated_by' => $responsible->id]);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])->assertSessionHasNoErrors()->assertRedirect();
        $report->refresh();

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report, 'ตรวจรอบสองผ่าน'))->assertRedirect();
        $this->assertSame('QA_VERIFIED', $report->fresh()->current_status);
        $this->assertDatabaseCount('ncr_action_verifications', 2);
        $this->assertDatabaseHas('ncr_action_verifications', ['ncr_report_id' => $report->id, 'verification_round' => 1, 'result' => 'FAILED', 'verification_detail' => 'ตรวจหลักฐานแล้วไม่ครบ']);
        $this->assertDatabaseHas('ncr_status_history', ['ncr_report_id' => $report->id, 'action_code' => 'VERIFY_ACTION_FAILED', 'action_note' => 'ตรวจหลักฐานแล้วไม่ครบ']);
        $this->assertDatabaseHas('ncr_action_verifications', ['ncr_report_id' => $report->id, 'verification_round' => 2, 'result' => 'PASSED']);
    }

    public function test_rework_requires_responsible_department_and_cannot_skip_start_transition(): void
    {
        $report = $this->pendingVerification();
        $verifier = $this->user(['ncr.action.verify']);
        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no,
            'result' => 'FAILED',
            'verification_detail' => 'ไม่ผ่าน',
            'return_reason' => 'ทำใหม่',
        ]);
        $report->refresh();

        $otherDepartment = Department::create(['code' => 'OTHER', 'name_th' => 'หน่วยงานอื่น', 'is_active' => true]);
        $outsider = $this->user(['ncr.action.manage'], $otherDepartment);
        $this->actingAs($outsider)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertForbidden();

        $responsible = $this->user(['ncr.action.manage']);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])->assertStatus(409);
        $this->assertSame('RETURNED_FOR_REWORK', $report->fresh()->current_status);
    }

    public function test_verification_rejects_stale_version_and_action_from_another_report(): void
    {
        $report = $this->pendingVerification();
        $otherReport = $this->pendingVerification('NCR-2026-00999');
        $verifier = $this->user(['ncr.action.verify']);

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), array_merge($this->verificationPayload($report), [
            'corrective_action_id' => $otherReport->correctiveActions()->value('id'),
        ]))->assertSessionHasErrors('corrective_action_id');
        $this->assertDatabaseCount('ncr_action_verifications', 0);

        $report->update(['version_no' => 2]);
        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => 1,
            'result' => 'PASSED',
            'verification_detail' => 'ใช้ version เก่า',
        ])->assertStatus(409);
        $this->assertDatabaseCount('ncr_action_verifications', 0);
    }

    public function test_verification_uses_only_current_cycle_actions_and_preserves_failed_history(): void
    {
        [$report, $historicalAction, $currentAction] = $this->managedCycleReport('NCR-2026-CYCLE-VERIFY', 'COMPLETED', 'COMPLETED');
        $verifier = $this->user(['ncr.action.verify']);

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))->assertRedirect();

        $this->assertSame('COMPLETED', $historicalAction->fresh()->action_status);
        $this->assertSame('VERIFIED', $currentAction->fresh()->action_status);
        $this->assertSame('QA_VERIFIED', $report->fresh()->current_status);
    }

    public function test_historical_failed_actions_do_not_block_current_cycle_closure(): void
    {
        [$report, $historicalAction, $currentAction] = $this->managedCycleReport('NCR-2026-CYCLE-CLOSE', 'COMPLETED');
        $qa = $this->user(['ncr.action.verify', 'ncr.qa.followup', 'ncr.close']);
        $this->actingAs($qa)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))->assertRedirect();
        $report->refresh();
        $this->actingAs($qa)->post(route('ncr.qa-followup.store', $report), [
            'version_no' => $report->version_no,
            'followup_date' => now()->toDateString(),
            'effectiveness_result' => 'EFFECTIVE',
            'followup_detail' => 'ผลรอบปัจจุบันมีประสิทธิผล',
            'checklist_confirmed' => '1',
        ])->assertRedirect();

        $this->assertSame('REJECTED', $historicalAction->fresh()->action_status);
        $this->assertSame('VERIFIED', $currentAction->fresh()->action_status);
        $this->assertSame('CLOSED', $report->fresh()->current_status);
    }

    public function test_empty_current_cycle_cannot_inherit_legacy_action_results(): void
    {
        $report = $this->pendingVerification('NCR-2026-EMPTY-CYCLE');
        $actor = $this->user(['ncr.action.manage']);
        $cycle = NcrCorrectiveActionCycle::create([
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'origin' => 'INITIAL',
            'status' => 'APPROVED',
            'opened_by' => $actor->id,
            'opened_at' => now(),
        ]);
        $report->update([
            'current_status' => 'ACTION_IN_PROGRESS',
            'current_corrective_action_cycle_id' => $cycle->id,
        ]);

        $this->actingAs($actor)->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])
            ->assertSessionHasErrors('actions');
        $this->assertSame('ACTION_IN_PROGRESS', $report->fresh()->current_status);
    }

    public function test_historical_verified_action_cannot_satisfy_incomplete_current_cycle_verification(): void
    {
        [$report, $historicalAction, $currentAction] = $this->managedCycleReport('NCR-2026-INCOMPLETE-CYCLE', 'IN_PROGRESS', 'VERIFIED');
        $verifier = $this->user(['ncr.action.verify']);

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))
            ->assertSessionHasErrors('actions');
        $this->assertSame('VERIFIED', $historicalAction->fresh()->action_status);
        $this->assertSame('IN_PROGRESS', $currentAction->fresh()->action_status);
        $this->assertSame('PENDING_ACTION_VERIFICATION', $report->fresh()->current_status);
    }

    public function test_empty_current_cycle_cannot_be_ready_for_closure(): void
    {
        $report = $this->pendingVerification('NCR-2026-EMPTY-CLOSE');
        $qa = $this->user(['ncr.qa.followup', 'ncr.close']);
        $cycle = NcrCorrectiveActionCycle::create([
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'origin' => 'INITIAL',
            'status' => 'APPROVED',
            'opened_by' => $qa->id,
            'opened_at' => now(),
        ]);
        $report->update(['current_status' => 'QA_VERIFIED', 'current_corrective_action_cycle_id' => $cycle->id]);
        $this->actingAs($qa)->post(route('ncr.qa-followup.store', $report), [
            'version_no' => $report->version_no,
            'followup_date' => now()->toDateString(),
            'effectiveness_result' => 'EFFECTIVE',
            'followup_detail' => 'ผลติดตามเป็น EFFECTIVE',
            'checklist_confirmed' => '1',
        ])->assertSessionHasErrors('actions');
        $this->assertSame('QA_VERIFIED', $report->fresh()->current_status);
        $this->assertDatabaseMissing('ncr_qa_followups', ['ncr_report_id' => $report->id]);
        $this->assertDatabaseMissing('ncr_closures', ['ncr_report_id' => $report->id]);
    }

    public function test_verification_failure_starts_rework_with_a_new_draft_cycle_and_cloned_actions(): void
    {
        [$report, $cycleOne, $actionOne] = $this->managedPendingVerification('NCR-2026-REWORK-CYCLE');
        $verifier = $this->user(['ncr.action.verify']);
        $responsible = $this->user(['ncr.action.manage']);

        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no,
            'result' => 'FAILED',
            'verification_detail' => 'ผลรอบแรกไม่ผ่าน',
            'return_reason' => 'ปรับปรุง action plan',
        ])->assertRedirect();
        $report->refresh();
        $this->assertSame('RETURNED_FOR_REWORK', $report->current_status);

        $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertRedirect();
        $report->refresh();

        $cycleTwo = NcrCorrectiveActionCycle::query()->where('ncr_report_id', $report->id)->where('cycle_no', 2)->firstOrFail();
        $actionTwo = NcrCorrectiveAction::query()->where('corrective_action_cycle_id', $cycleTwo->id)->firstOrFail();
        $this->assertSame('DISPOSITION_APPROVED', $report->current_status);
        $this->assertSame($cycleTwo->id, $report->current_corrective_action_cycle_id);
        $this->assertSame('DRAFT', $cycleTwo->status);
        $this->assertNotSame($actionOne->id, $actionTwo->id);
        $this->assertSame($actionOne->id, $actionTwo->supersedes_corrective_action_id);
        $this->assertSame('OPEN', $actionTwo->action_status);
        $this->assertSame(0, (int) $actionTwo->progress_percent);
        $this->assertSame('COMPLETED', $actionOne->fresh()->action_status);
        $this->assertSame('APPROVED', $cycleOne->fresh()->status);
    }

    public function test_qa_followup_failure_and_direct_qa_return_each_create_a_new_draft_cycle(): void
    {
        foreach ([
            'followup' => 'QA_NOT_EFFECTIVE',
            'direct' => 'QA_RETURN_FOR_REWORK',
        ] as $path => $expectedOrigin) {
            [$report] = $this->managedPendingVerification('NCR-2026-REWORK-'.$path);
            $qa = $this->user(['ncr.action.verify', 'ncr.qa.followup']);
            $responsible = $this->user(['ncr.view', 'ncr.action.manage']);
            $this->actingAs($qa)->post(route('ncr.corrective-actions.verify', $report), $this->verificationPayload($report))->assertRedirect();
            $report->refresh();

            if ($path === 'followup') {
                $this->actingAs($qa)->post(route('ncr.qa-followup.store', $report), [
                    'version_no' => $report->version_no,
                    'followup_date' => now()->toDateString(),
                    'effectiveness_result' => 'NOT_EFFECTIVE',
                    'followup_detail' => 'ผลยังไม่เกิดประสิทธิผล',
                    'return_reason' => 'ต้องปรับปรุงใหม่',
                ])->assertRedirect();
            } else {
                $this->actingAs($qa)->post(route('ncr.qa-followup.return', $report), [
                    'version_no' => $report->version_no,
                    'reason' => 'QA ส่งกลับเพื่อแก้ไข',
                ])->assertRedirect();
            }
            $report->refresh();
            $this->assertSame('RETURNED_FOR_REWORK', $report->current_status);
            $expectedReason = $path === 'followup' ? 'ต้องปรับปรุงใหม่' : 'QA ส่งกลับเพื่อแก้ไข';
            $this->actingAs($responsible)->get(route('ncr.show', $report))
                ->assertOk()
                ->assertSee('เหตุผลที่ส่งกลับ:')
                ->assertSee($expectedReason)
                ->assertSee('href="#start-corrective-action-rework"', false);
            $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertRedirect();
            $report->refresh();
            $this->assertSame('DISPOSITION_APPROVED', $report->current_status);
            $this->assertDatabaseHas('ncr_corrective_action_cycles', [
                'ncr_report_id' => $report->id,
                'cycle_no' => 2,
                'origin' => $expectedOrigin,
                'status' => 'DRAFT',
            ]);
        }
    }

    public function test_rework_requires_new_cycle_approval_before_execution_resumes(): void
    {
        [$report, $cycleOne] = $this->managedPendingVerification('NCR-2026-REWORK-REAPPROVAL');
        $verifier = $this->user(['ncr.action.verify']);
        $responsible = $this->user(['ncr.action.manage']);
        $approver = $this->caApprover();
        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no,
            'result' => 'FAILED',
            'verification_detail' => 'ไม่ผ่านรอบแรก',
            'return_reason' => 'ต้องแก้ไขใหม่',
        ])->assertRedirect();
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertRedirect();
        $report->refresh();

        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit', $report), ['version_no' => $report->version_no])->assertStatus(409);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), ['version_no' => $report->version_no])->assertRedirect();
        $report->refresh();
        $cycleTwoId = (int) $report->current_corrective_action_cycle_id;
        $snapshotTwoId = (int) NcrCorrectiveActionPlanSnapshot::query()->where('corrective_action_cycle_id', $cycleTwoId)->value('id');
        $this->actingAs($approver)->post(route('ncr.corrective-actions.decision', $report), ['version_no' => $report->version_no, 'decision' => 'APPROVED'])->assertRedirect();

        $this->assertSame('ACTION_IN_PROGRESS', $report->fresh()->current_status);
        $this->assertDatabaseHas('ncr_approvals', [
            'corrective_action_cycle_id' => $cycleTwoId,
            'corrective_action_plan_snapshot_id' => $snapshotTwoId,
            'decision' => 'APPROVED',
        ]);
        $this->assertDatabaseHas('ncr_approvals', ['corrective_action_cycle_id' => $cycleOne->id, 'decision' => 'APPROVED']);
    }

    public function test_legacy_rework_requires_provable_approved_authorization(): void
    {
        foreach (['MISSING' => null, 'REJECTED' => 'REJECTED', 'PENDING' => 'PENDING'] as $case => $decision) {
            $report = $this->legacyReturnedForRework('NCR-2026-LEGACY-'.$case, $decision);
            $responsible = $this->user(['ncr.action.manage']);

            $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertStatus(409);
            $this->assertSame('RETURNED_FOR_REWORK', $report->fresh()->current_status);
            $this->assertNull($report->fresh()->current_corrective_action_cycle_id);
            $this->assertDatabaseCount('ncr_corrective_action_cycles', 0);
        }
    }

    public function test_legacy_scrap_rework_requires_approved_destruction_authorization(): void
    {
        foreach ([null, 'REJECTED'] as $destructionDecision) {
            $report = $this->legacyReturnedForRework('NCR-2026-LEGACY-SCRAP-'.($destructionDecision ?: 'MISSING'), 'APPROVED', 'SCRAP', $destructionDecision);
            $responsible = $this->user(['ncr.action.manage']);

            $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertStatus(409);
            $this->assertSame('RETURNED_FOR_REWORK', $report->fresh()->current_status);
            $this->assertDatabaseCount('ncr_corrective_action_cycles', 0);
        }
    }

    public function test_rework_preserves_historical_evidence_without_copying_it_to_cycle_two(): void
    {
        [$report, $cycleOne, $actionOne] = $this->managedPendingVerification('NCR-2026-REWORK-EVIDENCE');
        $attachment = $actionOne->attachments()->firstOrFail();
        $snapshotPayload = NcrCorrectiveActionPlanSnapshot::query()->where('corrective_action_cycle_id', $cycleOne->id)->value('plan_payload');
        Storage::disk('local')->put($attachment->storage_path, 'cycle one evidence');
        $verifier = $this->user(['ncr.action.verify']);
        $responsible = $this->user(['ncr.action.manage']);
        $this->actingAs($verifier)->post(route('ncr.corrective-actions.verify', $report), [
            'version_no' => $report->version_no, 'result' => 'FAILED', 'verification_detail' => 'ไม่ผ่าน', 'return_reason' => 'แก้ไขใหม่',
        ])->assertRedirect();
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $report->version_no])->assertRedirect();
        $actionTwo = NcrCorrectiveAction::query()->where('corrective_action_cycle_id', $report->fresh()->current_corrective_action_cycle_id)->firstOrFail();

        $this->assertCount(1, $actionOne->fresh()->attachments);
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->assertCount(0, $actionTwo->attachments);
        $this->assertSame($snapshotPayload, NcrCorrectiveActionPlanSnapshot::query()->where('corrective_action_cycle_id', $cycleOne->id)->value('plan_payload'));
    }

    public function test_rework_uses_the_current_cycle_return_event_when_all_timestamps_tie(): void
    {
        $this->travelTo(now()->startOfSecond());
        foreach (['verification' => 'VERIFICATION_FAILED', 'followup' => 'QA_NOT_EFFECTIVE', 'direct' => 'QA_RETURN_FOR_REWORK'] as $path => $origin) {
            [$report] = $this->managedPendingVerification('NCR-CYCLE-RETURN-'.$path);
            $actor = $this->user(['ncr.action.manage', 'ncr.action.approve', 'ncr.action.verify', 'ncr.qa.followup']);
            $workflow = app(\App\Services\NcrWorkflowService::class);
            $workflow->verifyActions($report, $actor, $report->version_no, ['result' => 'FAILED', 'verification_detail' => 'Old verification reason']);
            $report->refresh();
            $workflow->startRework($report, $actor, $report->version_no);
            $report->refresh();
            $workflow->submitCorrectiveActionsForApproval($report, $actor, $report->version_no);
            $report->refresh();
            $workflow->correctiveActionDecision($report, $actor, $report->version_no, 'APPROVED', null);
            $report->refresh();
            $action = $report->correctiveActions()->where('corrective_action_cycle_id', $report->current_corrective_action_cycle_id)->firstOrFail();
            $action->update(['action_status' => 'COMPLETED', 'progress_percent' => 100, 'completion_note' => 'Completed current cycle']);
            $workflow->submitActionsForVerification($report, $actor, $report->version_no);
            $report->refresh();
            $workflow->verifyActions($report, $actor, $report->version_no, ['result' => $path === 'verification' ? 'FAILED' : 'PASSED', 'verification_detail' => 'Current verification reason']);
            $report->refresh();
            $reason = 'Current verification reason';
            if ($path === 'followup') {
                $reason = 'Current QA followup reason';
                $workflow->saveQaFollowup($report, $actor, $report->version_no, ['followup_date' => now()->toDateString(), 'effectiveness_result' => 'NOT_EFFECTIVE', 'followup_detail' => 'QA detail', 'return_reason' => $reason]);
            } elseif ($path === 'direct') {
                $reason = 'Current direct QA reason';
                $workflow->returnForReworkFromQa($report, $actor, $report->version_no, $reason);
            }
            $report->refresh();
            // A newer unrelated history row must not replace the actual return event.
            $report->statusHistory()->create(['from_status' => 'RETURNED_FOR_REWORK', 'to_status' => 'RETURNED_FOR_REWORK', 'action_code' => 'UNRELATED_NOTE', 'action_note' => 'Unrelated', 'changed_at' => now(), 'changed_by' => $actor->id]);
            $workflow->startRework($report, $actor, $report->version_no);
            $report->refresh();
            $this->assertSame(3, (int) $report->currentCorrectiveActionCycle->cycle_no);
            $this->assertSame($origin, $report->currentCorrectiveActionCycle->origin);
            $this->assertSame($reason, $report->statusHistory()->reorder()->where('action_code', 'START_REWORK')->orderByDesc('id')->value('action_note'));
            $clone = $report->correctiveActions()->where('corrective_action_cycle_id', $report->current_corrective_action_cycle_id)->firstOrFail();
            $this->assertSame($reason, $clone->progressHistory()->value('progress_note'));
            $this->assertSame('Old verification reason', $report->actionVerifications()->where('verification_round', 1)->value('verification_detail'));
        }
    }

    public function test_direct_qa_return_does_not_inherit_an_older_qa_followup_failure(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$report] = $this->managedPendingVerification('NCR-OLD-QA-FOLLOWUP');
        $actor = $this->user(['ncr.action.manage', 'ncr.action.verify', 'ncr.qa.followup']);
        $workflow = app(\App\Services\NcrWorkflowService::class);
        $workflow->verifyActions($report, $actor, $report->version_no, ['result' => 'PASSED', 'verification_detail' => 'Passed first cycle']);
        $report->refresh();
        $workflow->saveQaFollowup($report, $actor, $report->version_no, ['followup_date' => now()->toDateString(), 'effectiveness_result' => 'NOT_EFFECTIVE', 'followup_detail' => 'Old QA detail', 'return_reason' => 'Old QA reason']);
        $report->refresh();
        $workflow->startRework($report, $actor, $report->version_no);
        $report->refresh();
        $workflow->submitCorrectiveActionsForApproval($report, $actor, $report->version_no);
        $report->refresh();
        $workflow->correctiveActionDecision($report, $actor, $report->version_no, 'APPROVED', null);
        $report->refresh();
        $report->correctiveActions()->where('corrective_action_cycle_id', $report->current_corrective_action_cycle_id)
            ->update(['action_status' => 'COMPLETED', 'progress_percent' => 100, 'completion_note' => 'Completed']);
        $workflow->submitActionsForVerification($report, $actor, $report->version_no);
        $report->refresh();
        $workflow->verifyActions($report, $actor, $report->version_no, ['result' => 'PASSED', 'verification_detail' => 'Passed second cycle']);
        $report->refresh();
        $workflow->returnForReworkFromQa($report, $actor, $report->version_no, 'Current direct QA reason');
        $report->refresh();
        $workflow->startRework($report, $actor, $report->version_no);
        $report->refresh();
        $this->assertSame('QA_RETURN_FOR_REWORK', $report->currentCorrectiveActionCycle->origin);
        $this->assertSame('Current direct QA reason', $report->statusHistory()->reorder()->where('action_code', 'START_REWORK')->orderByDesc('id')->value('action_note'));
        $this->assertSame('Old QA reason', $report->qaFollowups()->value('return_reason'));
    }

    public function test_rework_does_not_reuse_a_return_event_before_the_current_cycle_opened(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$report] = $this->managedPendingVerification('NCR-MISSING-CURRENT-RETURN');
        $actor = $this->user(['ncr.action.manage', 'ncr.action.verify']);
        $workflow = app(\App\Services\NcrWorkflowService::class);
        $workflow->verifyActions($report, $actor, $report->version_no, ['result' => 'FAILED', 'verification_detail' => 'Old reason']);
        $report->refresh();
        $workflow->startRework($report, $actor, $report->version_no);
        $report->refresh();
        $report->currentCorrectiveActionCycle->update(['status' => 'APPROVED']);
        $report->update(['current_status' => 'RETURNED_FOR_REWORK']);
        $version = $report->version_no;
        $this->actingAs($actor)->post(route('ncr.corrective-actions.start-rework', $report), ['version_no' => $version])->assertStatus(409);
        $this->assertSame($version, $report->fresh()->version_no);
        $this->assertSame(2, $report->correctiveActionCycles()->count());
        $this->assertSame(2, $report->correctiveActions()->count());
    }

    public function test_first_cycle_qa_returns_preserve_their_reason_in_rework_history(): void
    {
        foreach (['followup', 'direct'] as $path) {
            [$report] = $this->managedPendingVerification('NCR-FIRST-QA-'.$path);
            $actor = $this->user(['ncr.action.manage', 'ncr.action.verify', 'ncr.qa.followup']);
            $workflow = app(\App\Services\NcrWorkflowService::class);
            $workflow->verifyActions($report, $actor, $report->version_no, ['result' => 'PASSED', 'verification_detail' => 'Passed']);
            $report->refresh();
            if ($path === 'followup') {
                $workflow->saveQaFollowup($report, $actor, $report->version_no, ['followup_date' => now()->toDateString(), 'effectiveness_result' => 'NOT_EFFECTIVE', 'followup_detail' => 'QA detail', 'return_reason' => 'QA reason']);
            } else {
                $workflow->returnForReworkFromQa($report, $actor, $report->version_no, 'QA reason');
            }
            $report->refresh();
            $workflow->startRework($report, $actor, $report->version_no);
            $this->assertSame('QA reason', $report->statusHistory()->where('action_code', 'START_REWORK')->value('action_note'));
        }
    }

    private function pendingVerification(string $number = 'NCR-2026-00001'): NcrReport
    {
        $reporter = $this->user([]);
        $report = NcrReport::create([
            'ncr_no' => $number,
            'report_date' => now()->toDateString(),
            'found_date' => now()->toDateString(),
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $this->department->id,
            'related_department_id' => $this->department->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'unit_name' => 'PCS',
            'problem_description' => 'ทดสอบ Phase 5',
            'current_status' => 'PENDING_ACTION_VERIFICATION',
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        NcrDisposition::create([
            'ncr_report_id' => $report->id,
            'disposition_type_id' => $this->dispositionType->id,
            'revision_no' => 1,
            'disposition_detail' => 'ดำเนินการแก้ไข',
            'responsible_department_id' => $this->department->id,
            'proposed_due_date' => now()->addDays(7)->toDateString(),
            'status' => 'APPROVED',
            'proposed_at' => now(),
            'proposed_by' => $reporter->id,
            'approved_at' => now(),
            'approved_by' => $reporter->id,
        ]);

        $action = NcrCorrectiveAction::create([
            'ncr_report_id' => $report->id,
            'action_no' => 1,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'แก้ไขสาเหตุแล้ว',
            'responsible_department_id' => $this->department->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'completed_date' => now()->toDateString(),
            'progress_percent' => 100,
            'action_status' => 'COMPLETED',
            'is_required' => true,
            'completion_note' => 'ดำเนินการครบแล้ว',
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        NcrActionAttachment::create([
            'corrective_action_id' => $action->id,
            'original_filename' => 'evidence.pdf',
            'stored_filename' => 'evidence.pdf',
            'storage_path' => 'ncr/test/evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_at' => now(),
            'uploaded_by' => $reporter->id,
            'is_active' => true,
        ]);

        return $report->fresh();
    }

    private function managedCycleReport(string $number, string $currentActionStatus, string $historicalActionStatus = 'REJECTED'): array
    {
        $report = $this->pendingVerification($number);
        $historicalAction = $report->correctiveActions()->firstOrFail();
        $historicalCycle = NcrCorrectiveActionCycle::create([
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'origin' => 'INITIAL',
            'status' => 'APPROVED',
            'opened_by' => $historicalAction->created_by,
            'opened_at' => now(),
        ]);
        $historicalAction->update(['corrective_action_cycle_id' => $historicalCycle->id, 'action_status' => $historicalActionStatus]);
        $currentCycle = NcrCorrectiveActionCycle::create([
            'ncr_report_id' => $report->id,
            'cycle_no' => 2,
            'origin' => 'VERIFICATION_FAILED',
            'status' => 'APPROVED',
            'opened_by' => $historicalAction->created_by,
            'opened_at' => now(),
        ]);
        $currentAction = NcrCorrectiveAction::create([
            'ncr_report_id' => $report->id,
            'corrective_action_cycle_id' => $currentCycle->id,
            'action_no' => 2,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'แผนรอบปัจจุบัน',
            'responsible_department_id' => $this->department->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'completed_date' => $currentActionStatus === 'COMPLETED' ? now()->toDateString() : null,
            'progress_percent' => $currentActionStatus === 'COMPLETED' ? 100 : 0,
            'action_status' => $currentActionStatus,
            'is_required' => true,
            'completion_note' => $currentActionStatus === 'COMPLETED' ? 'ดำเนินการรอบปัจจุบันครบแล้ว' : null,
            'created_by' => $historicalAction->created_by,
            'updated_by' => $historicalAction->updated_by,
        ]);
        $report->update(['current_corrective_action_cycle_id' => $currentCycle->id]);

        return [$report->fresh(), $historicalAction->fresh(), $currentAction->fresh()];
    }

    private function managedPendingVerification(string $number): array
    {
        $report = $this->pendingVerification($number);
        $action = $report->correctiveActions()->firstOrFail();
        $cycle = NcrCorrectiveActionCycle::create([
            'ncr_report_id' => $report->id,
            'cycle_no' => 1,
            'origin' => 'INITIAL',
            'status' => 'APPROVED',
            'opened_by' => $action->created_by,
            'opened_at' => now(),
        ]);
        $snapshot = NcrCorrectiveActionPlanSnapshot::create([
            'corrective_action_cycle_id' => $cycle->id,
            'revision_no' => 1,
            'plan_hash' => hash('sha256', 'cycle-one'),
            'plan_payload' => [],
            'report_version_at_submit' => $report->version_no,
            'submitted_by' => $action->created_by,
            'submitted_at' => now(),
        ]);
        NcrApproval::create([
            'ncr_report_id' => $report->id,
            'disposition_id' => $report->dispositions()->value('id'),
            'corrective_action_cycle_id' => $cycle->id,
            'corrective_action_plan_snapshot_id' => $snapshot->id,
            'approval_stage' => 'CORRECTIVE_ACTION_APPROVAL',
            'approval_level' => 1,
            'approver_id' => $action->created_by,
            'decision' => 'APPROVED',
            'decided_at' => now(),
        ]);
        $action->update(['corrective_action_cycle_id' => $cycle->id]);
        $report->update(['current_corrective_action_cycle_id' => $cycle->id]);

        return [$report->fresh(), $cycle->fresh(), $action->fresh()];
    }

    private function legacyReturnedForRework(string $number, ?string $correctiveDecision, ?string $dispositionCode = null, ?string $destructionDecision = null): NcrReport
    {
        $report = $this->pendingVerification($number);
        if ($dispositionCode === 'SCRAP') {
            $scrap = DispositionType::firstOrCreate(['code' => 'SCRAP'], ['name_th' => 'เสนอทำลาย', 'is_active' => true]);
            $report->dispositions()->firstOrFail()->update(['disposition_type_id' => $scrap->id]);
        }
        $action = $report->correctiveActions()->firstOrFail();
        $report->update(['current_status' => 'RETURNED_FOR_REWORK']);
        if ($correctiveDecision !== null) {
            NcrApproval::create([
                'ncr_report_id' => $report->id,
                'disposition_id' => $report->dispositions()->value('id'),
                'approval_stage' => 'CORRECTIVE_ACTION_APPROVAL',
                'approval_level' => 1,
                'approver_id' => $action->created_by,
                'decision' => $correctiveDecision,
                'decided_at' => now(),
            ]);
        }
        if ($destructionDecision !== null) {
            NcrApproval::create([
                'ncr_report_id' => $report->id,
                'disposition_id' => $report->dispositions()->value('id'),
                'approval_stage' => 'DESTRUCTION_COMMITTEE_APPROVAL',
                'approval_level' => 1,
                'approver_id' => $action->created_by,
                'decision' => $destructionDecision,
                'decided_at' => now(),
            ]);
        }

        return $report->fresh();
    }

    private function verificationPayload(NcrReport $report, string $detail = 'ตรวจหลักฐานและผลการแก้ไขแล้ว'): array
    {
        return [
            'version_no' => $report->version_no,
            'result' => 'PASSED',
            'verification_detail' => $detail,
        ];
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
}
