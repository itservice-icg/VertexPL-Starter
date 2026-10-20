<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DispositionType;
use App\Models\NcrCorrectiveAction;
use App\Models\NcrCorrectiveActionCycle;
use App\Models\NcrDisposition;
use App\Models\NcrFormSignoff;
use App\Models\NcrReport;
use App\Models\NcrInitialReview;
use App\Models\ProblemType;
use App\Models\Product;
use App\Models\SeverityLevel;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\NcrFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrFieldPreservationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private ProblemType $problemType;
    private SeverityLevel $severity;
    private DispositionType $dispositionType;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NcrFoundationSeeder::class);
        $this->product = Product::create(['code' => 'P001', 'name_th' => 'สินค้า A', 'is_active' => true]);
        $this->department = Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);
        $this->problemType = ProblemType::where('code', 'RAW_MATERIAL')->firstOrFail();
        $this->severity = SeverityLevel::where('code', 'MEDIUM')->firstOrFail();
        $this->dispositionType = DispositionType::where('code', 'REWORK')->firstOrFail();
    }

    public function test_part_one_preserves_source_fields_units_percentage_masters_and_metadata(): void
    {
        $reporter = $this->user(['ncr.create', 'ncr.update', 'ncr.view']);

        $this->actingAs($reporter)->post(route('ncr.store'), $this->reportPayload('AI'))->assertRedirect();
        $report = NcrReport::firstOrFail();
        $item = $report->items()->firstOrFail();

        $this->assertSame('AI', $report->legacy_form_scope_code);
        $this->assertSame('ชื่อสามัญเดิม', $report->generic_name);
        $this->assertSame('REF-FM-001', $report->reference_no);
        $this->assertSame('KG', $item->defect_unit_name);
        $this->assertSame('PCS', $item->total_unit_name);
        $this->assertSame(20.0, $item->defect_percentage);

        $this->assertSame([
            'CUSTOMER_PRODUCT', 'FINISHED_GOODS', 'PACKAGING_SUPPLY',
            'RAW_MATERIAL', 'REPACK', 'WIP',
        ], ProblemType::whereIn('code', [
            'RAW_MATERIAL', 'PACKAGING_SUPPLY', 'WIP', 'REPACK', 'FINISHED_GOODS', 'CUSTOMER_PRODUCT',
        ])->orderBy('code')->pluck('code')->all());
        $this->assertDatabaseHas('disposition_types', ['code' => 'CUSTOMER_COORDINATION', 'is_active' => true]);
        $this->assertSame('FM-DQA-06', config('ncr.form.code'));
        $this->assertSame('03', config('ncr.form.revision'));

        $this->actingAs($reporter)->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('AI / UP')
            ->assertSee('ชื่อสามัญเดิม')
            ->assertSee('REF-FM-001')
            ->assertSee('20.00%')
            ->assertSee('FM-DQA-06');

        $updatePayload = $this->reportPayload('UP');
        $updatePayload['items'][0]['item_id'] = $item->id;
        $updatePayload['items'][0]['product_id'] = $item->product_id;
        $this->actingAs($reporter)->put(route('ncr.update', $report), $updatePayload + [
            'version_no' => $report->version_no,
        ])->assertRedirect();
        $this->assertSame('UP', $report->fresh()->legacy_form_scope_code);
    }

    public function test_item_units_use_master_codes_and_legacy_symbols_are_normalized(): void
    {
        $reporter = $this->user(['ncr.create', 'ncr.view']);
        Unit::where('code', 'KG')->update(['symbol' => 'kilogram']);

        $this->actingAs($reporter)
            ->get(route('ncr.create'))
            ->assertOk()
            ->assertSee('<option value="KG">กิโลกรัม (KG)</option>', false);

        $payload = $this->reportPayload('AI');
        $payload['items'][0]['defect_unit_name'] = 'kilogram';
        $this->actingAs($reporter)->post(route('ncr.store'), $payload)->assertRedirect();

        $this->assertSame('KG', NcrReport::firstOrFail()->items()->firstOrFail()->defect_unit_name);
    }

    public function test_item_units_reject_values_outside_the_units_master(): void
    {
        $reporter = $this->user(['ncr.create']);
        $payload = $this->reportPayload('AI');
        $payload['items'][0]['defect_unit_name'] = 'kilograms';

        $this->actingAs($reporter)
            ->post(route('ncr.store'), $payload)
            ->assertSessionHasErrors('items.0.defect_unit_name');

        $this->assertDatabaseCount('ncr_reports', 0);
    }

    public function test_part_two_rejects_other_decision_and_original_approval_still_works(): void
    {
        [$report, $disposition] = $this->pendingManagerReport();
        $manager = $this->user(['ncr.view', 'ncr.disposition.approve']);

        $this->actingAs($manager)->post(route('ncr.manager-decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'OTHER',
            'decision_note' => 'ขอข้อมูลประกอบเพิ่มเติม',
        ])->assertSessionHasErrors('decision');

        $report->refresh();
        $this->assertSame('PENDING_MANAGER_APPROVAL', $report->current_status);
        $this->assertSame('PENDING', $disposition->fresh()->status);
        $this->assertDatabaseMissing('ncr_approvals', [
            'ncr_report_id' => $report->id,
            'decision' => 'OTHER',
        ]);

        $this->actingAs($manager)->post(route('ncr.manager-decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'APPROVED',
        ])->assertRedirect();
        $this->assertSame('DISPOSITION_APPROVED', $report->fresh()->current_status);
    }

    public function test_completed_stage_two_keeps_its_review_and_disposition_visible(): void
    {
        $report = $this->report('ACTION_IN_PROGRESS');
        $reviewer = $this->user(['ncr.view']);
        NcrInitialReview::create([
            'ncr_report_id' => $report->id,
            'review_round' => 1,
            'is_complete' => true,
            'completeness_note' => 'Stage two review evidence',
            'review_result' => 'COMPLETE',
            'reviewed_at' => '2026-08-27 09:30:00',
            'reviewed_by' => $reviewer->id,
        ]);
        $disposition = $this->approvedDisposition($report);
        $disposition->update(['disposition_detail' => 'Stage two disposition evidence']);

        $this->actingAs($reviewer)
            ->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('data-stage-two-summary', false)
            ->assertSee('Stage two review evidence')
            ->assertSee('Stage two disposition evidence')
            ->assertSee($reviewer->name);
    }

    public function test_part_two_preserves_responsible_acknowledgement_and_passive_form_signoffs(): void
    {
        $report = $this->report('DISPOSITION_APPROVED');
        $this->approvedDisposition($report);
        $responsible = $this->user(['ncr.view', 'ncr.action.manage']);
        NcrInitialReview::create([
            'ncr_report_id' => $report->id,
            'review_round' => 1,
            'is_complete' => true,
            'review_result' => 'COMPLETE',
            'reviewed_at' => now(),
            'reviewed_by' => $responsible->id,
        ]);
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.store', $report), [
            'version_no' => $report->version_no,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'ดำเนินการแก้ไขตาม FM-DQA-06',
            'responsible_department_id' => $this->department->id,
            'owner_user_id' => $responsible->id,
            'assignee_ids' => [$responsible->id],
            'responsible_acknowledged_at' => '2026-08-26 10:30:00',
            'due_date' => '2026-08-31',
            'progress_percent' => 0,
            'action_status' => 'OPEN',
        ])->assertRedirect();

        $this->assertDatabaseHas('ncr_corrective_actions', [
            'ncr_report_id' => $report->id,
            'owner_user_id' => $responsible->id,
            'responsible_acknowledged_at' => '2026-08-26 10:30:00',
        ]);
        $report->refresh();
        $this->actingAs($responsible)->post(route('ncr.corrective-actions.submit-approval', $report), [
            'version_no' => $report->version_no,
        ])->assertRedirect();

        $approver = $this->user(['ncr.action.approve']);
        $report->refresh();
        $this->actingAs($approver)->post(route('ncr.corrective-actions.decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'APPROVED',
        ])->assertRedirect();

        foreach (['DEPARTMENT_MANAGER_ACK', 'DEPUT_MD_FACTORY_ACK', 'MANAGING_DIRECTOR_ACK'] as $stage) {
            NcrFormSignoff::create([
                'ncr_report_id' => $report->id,
                'form_part' => $stage === 'DEPARTMENT_MANAGER_ACK' ? 1 : 3,
                'signoff_stage' => $stage,
                'signer_user_id' => $responsible->id,
                'signer_name_snapshot' => $responsible->name,
                'signoff_result' => 'RECORDED',
                'signed_at' => '2026-08-26 11:00:00',
                'recorded_by' => $responsible->id,
            ]);
        }

        $this->assertSame('ACTION_IN_PROGRESS', $report->fresh()->current_status);
        $this->assertDatabaseCount('ncr_form_signoffs', 3);
        $this->actingAs($responsible)->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('วันที่รับทราบ')
            ->assertSee($report->correctiveActions()->firstOrFail()->responsible_acknowledged_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i'))
            ->assertSee('FM-DQA-06 Sign-off Trace')
            ->assertSee('DEPUT_MD_FACTORY_ACK');
    }

    public function test_part_three_supports_more_than_two_followups_next_date_and_closure_trace(): void
    {
        $report = $this->report('QA_VERIFIED');
        $this->approvedDisposition($report);
        $actor = $this->user(['ncr.view', 'ncr.qa.followup', 'ncr.close']);
        NcrCorrectiveAction::create([
            'ncr_report_id' => $report->id,
            'action_no' => 1,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'แก้ไขแล้ว',
            'responsible_department_id' => $this->department->id,
            'owner_user_id' => $actor->id,
            'due_date' => '2026-08-31',
            'completed_date' => '2026-08-26',
            'progress_percent' => 100,
            'action_status' => 'VERIFIED',
            'is_required' => true,
            'completion_note' => 'ครบถ้วน',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->saveFollowup($actor, $report, 'NEED_MORE_TIME', 'รอติดตามซ้ำ', '2026-08-28');
        $this->saveFollowup($actor, $report, 'NEED_MORE_TIME', 'ผลรอบสองยังต้องติดตาม', '2026-08-29');
        $this->saveFollowup($actor, $report, 'EFFECTIVE', 'ผลรอบสามยืนยันต่อเนื่อง');

        $this->assertDatabaseCount('ncr_qa_followups', 3);
        $this->assertDatabaseHas('ncr_qa_followups', [
            'ncr_report_id' => $report->id,
            'followup_round' => 1,
            'effectiveness_result' => 'NEED_MORE_TIME',
        ]);
        $this->assertSame('2026-08-28', $report->qaFollowups()->where('followup_round', 1)->firstOrFail()->next_followup_date->toDateString());

        $this->assertSame('CLOSED', $report->fresh()->current_status);
        $this->assertDatabaseHas('ncr_closures', [
            'ncr_report_id' => $report->id,
            'closure_result' => 'CLOSED',
            'closure_note' => 'ผลรอบสามยืนยันต่อเนื่อง',
            'closed_by' => $actor->id,
        ]);
        $this->actingAs($actor)->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('ติดตามครั้งถัดไป: 28/08/2026')
            ->assertSee('ผลรอบสามยืนยันต่อเนื่อง')
            ->assertSee('QA Sign-off / ผู้ปิด')
            ->assertDontSee('บันทึก QA Follow-up');
    }

    private function saveFollowup(User $actor, NcrReport $report, string $result, string $detail, ?string $nextDate = null): void
    {
        $payload = [
            'version_no' => $report->version_no,
            'followup_date' => '2026-08-26',
            'effectiveness_result' => $result,
            'followup_detail' => $detail,
        ];
        if ($result !== 'EFFECTIVE') $payload['return_reason'] = 'ยังไม่ปิด NCR';
        if ($result === 'EFFECTIVE') $payload['checklist_confirmed'] = '1';
        if ($nextDate) $payload['next_followup_date'] = $nextDate;
        $this->actingAs($actor)->post(route('ncr.qa-followup.store', $report), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $report->refresh();
    }

    private function reportPayload(string $scope): array
    {
        return [
            'legacy_form_scope_code' => $scope,
            'report_date' => '2026-08-26',
            'found_date' => '2026-08-26',
            'found_time' => '09:15',
            'problem_type_id' => $this->problemType->id,
            'severity_level_id' => $this->severity->id,
            'company_name' => 'VertexPL',
            'generic_name' => 'ชื่อสามัญเดิม',
            'reference_no' => 'REF-FM-001',
            'found_location' => 'คลังสินค้า',
            'problem_description' => 'รายละเอียดความผิดปกติจากแบบฟอร์มเดิม',
            'items' => [[
                'product_id' => $this->product->id,
                'product_code' => 'P001',
                'product_name' => 'สินค้า A',
                'po_no' => 'PO-001',
                'lot_no' => 'LOT-001',
                'customer_name' => 'ลูกค้า A',
                'defect_quantity' => 2,
                'defect_unit_name' => 'KG',
                'total_quantity' => 10,
                'total_unit_name' => 'PCS',
                'problem_description' => 'รายละเอียดรายการ',
            ]],
        ];
    }

    private function pendingManagerReport(): array
    {
        $report = $this->report('PENDING_MANAGER_APPROVAL');
        $disposition = NcrDisposition::create([
            'ncr_report_id' => $report->id,
            'disposition_type_id' => $this->dispositionType->id,
            'revision_no' => 1,
            'disposition_detail' => 'เสนอ Rework',
            'responsible_department_id' => $this->department->id,
            'proposed_due_date' => '2026-08-31',
            'status' => 'PENDING',
            'proposed_at' => now(),
            'proposed_by' => $report->reporter_id,
        ]);
        return [$report, $disposition];
    }

    private function approvedDisposition(NcrReport $report): NcrDisposition
    {
        $disposition = NcrDisposition::create([
            'ncr_report_id' => $report->id,
            'disposition_type_id' => $this->dispositionType->id,
            'revision_no' => 1,
            'disposition_detail' => 'ดำเนินการแก้ไข',
            'responsible_department_id' => $this->department->id,
            'proposed_due_date' => '2026-08-31',
            'status' => 'APPROVED',
            'proposed_at' => now(),
            'proposed_by' => $report->reporter_id,
            'approved_at' => now(),
            'approved_by' => $report->reporter_id,
        ]);
        if ($report->current_status === 'DISPOSITION_APPROVED') {
            $cycle = NcrCorrectiveActionCycle::create([
                'ncr_report_id' => $report->id,
                'cycle_no' => 1,
                'origin' => 'INITIAL',
                'status' => 'DRAFT',
                'opened_by' => $report->reporter_id,
                'opened_at' => now(),
            ]);
            $report->update(['current_corrective_action_cycle_id' => $cycle->id]);
        }
        return $disposition;
    }
    private function report(string $status): NcrReport
    {
        $reporter = $this->user([]);
        return NcrReport::create([
            'ncr_no' => 'NCR-2026-'.str_pad((string) (NcrReport::count() + 1), 5, '0', STR_PAD_LEFT),
            'report_date' => '2026-08-26',
            'found_date' => '2026-08-26',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $this->department->id,
            'related_department_id' => $this->department->id,
            'problem_type_id' => $this->problemType->id,
            'severity_level_id' => $this->severity->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'unit_name' => 'PCS',
            'defect_unit_name' => 'PCS',
            'total_unit_name' => 'PCS',
            'problem_description' => 'ทดสอบ FM-DQA-06',
            'current_status' => $status,
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['department_id' => $this->department->id]);
        foreach ($permissions as $permission) $user->givePermissionTo(Permission::findOrCreate($permission));
        return $user;
    }
}
