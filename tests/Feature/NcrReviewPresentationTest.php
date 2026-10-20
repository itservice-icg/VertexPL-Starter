<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DispositionType;
use App\Models\NcrReport;
use App\Models\NcrDisposition;
use App\Models\ProblemType;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrReviewPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_initial_review_draft_disposition_can_still_be_submitted(): void
    {
        $department = Department::create([
            'code' => 'QA',
            'name_th' => 'Quality Assurance',
            'is_active' => true,
        ]);
        $problemType = ProblemType::create([
            'code' => 'PROCESS',
            'name_th' => 'Process',
            'is_active' => true,
        ]);
        $severityLevel = SeverityLevel::create([
            'code' => 'HIGH',
            'name_th' => 'High',
            'is_active' => true,
        ]);
        $dispositionType = DispositionType::create([
            'code' => 'REWORK',
            'name_th' => 'Rework',
            'is_active' => true,
        ]);
        $reporter = User::factory()->create(['department_id' => $department->id]);
        $reviewer = User::factory()->create(['department_id' => $department->id]);
        $reviewer->givePermissionTo([
            Permission::findOrCreate('ncr.view'),
            Permission::findOrCreate('ncr.review'),
            Permission::findOrCreate('ncr.disposition.manage'),
        ]);
        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-REVIEW-PRESENTATION',
            'report_date' => '2026-08-20',
            'found_date' => '2026-08-20',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $department->id,
            'problem_type_id' => $problemType->id,
            'severity_level_id' => $severityLevel->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'problem_description' => 'Presentation boundary test',
            'current_status' => 'INITIAL_REVIEW',
            'current_owner_user_id' => $reviewer->id,
            'current_owner_department_id' => $department->id,
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        NcrDisposition::create([
            'ncr_report_id' => $report->id,
            'disposition_type_id' => $dispositionType->id,
            'revision_no' => 1,
            'disposition_detail' => 'Legacy draft from before automatic submission',
            'responsible_department_id' => $department->id,
            'proposed_due_date' => null,
            'status' => 'DRAFT',
            'proposed_at' => now(),
            'proposed_by' => $reviewer->id,
        ]);

        $response = $this->actingAs($reviewer)->get(route('ncr.show', $report));

        $response->assertOk();
        $response->assertDontSee('action="' . route('ncr.initial-review', $report) . '"', false);
        $response->assertSee('id="disposition-submit"', false);
        $response->assertDontSee('เริ่มการตรวจสอบเบื้องต้น');

        $this->actingAs($reviewer)
            ->post(route('ncr.disposition.submit', $report), ['version_no' => $report->fresh()->version_no])
            ->assertRedirect();
        $report->refresh();
        $this->assertSame('PENDING_MANAGER_APPROVAL', $report->current_status);

        $this->assertDatabaseHas('ncr_reports', [
            'id' => $report->id,
            'current_status' => 'PENDING_MANAGER_APPROVAL',
        ]);
        $this->assertDatabaseHas('ncr_dispositions', [
            'ncr_report_id' => $report->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_approved_disposition_page_identifies_the_next_corrective_action_owner(): void
    {
        $reporterDepartment = Department::create([
            'code' => 'PROD',
            'name_th' => 'ฝ่ายผลิต',
            'is_active' => true,
        ]);
        $responsibleDepartment = Department::create([
            'code' => 'QA',
            'name_th' => 'ฝ่ายประกันคุณภาพ',
            'is_active' => true,
        ]);
        $problemType = ProblemType::create([
            'code' => 'PROCESS',
            'name_th' => 'Process',
            'is_active' => true,
        ]);
        $severityLevel = SeverityLevel::create([
            'code' => 'HIGH',
            'name_th' => 'High',
            'is_active' => true,
        ]);
        $dispositionType = DispositionType::create([
            'code' => 'REWORK',
            'name_th' => 'Rework',
            'is_active' => true,
        ]);
        $reporter = User::factory()->create(['department_id' => $reporterDepartment->id]);
        $reviewer = User::factory()->create(['department_id' => $reporterDepartment->id]);
        $reviewer->givePermissionTo([
            Permission::findOrCreate('ncr.view'),
            Permission::findOrCreate('ncr.review'),
            Permission::findOrCreate('ncr.disposition.manage'),
        ]);
        $approver = User::factory()->create(['department_id' => $reporterDepartment->id]);
        $approver->givePermissionTo([
            Permission::findOrCreate('ncr.view'),
            Permission::findOrCreate('ncr.disposition.approve'),
        ]);
        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-APPROVED-PRESENTATION',
            'report_date' => '2026-08-20',
            'found_date' => '2026-08-20',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $reporterDepartment->id,
            'problem_type_id' => $problemType->id,
            'severity_level_id' => $severityLevel->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'problem_description' => 'Approved disposition presentation test',
            'current_status' => 'INITIAL_REVIEW',
            'current_owner_user_id' => $reviewer->id,
            'current_owner_department_id' => $reporterDepartment->id,
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        $this->actingAs($reviewer)
            ->post(route('ncr.initial-review', $report), [
                'version_no' => $report->version_no,
                'review_result' => 'COMPLETE',
                'disposition_type_id' => $dispositionType->id,
                'disposition_detail' => 'Correct the process',
                'responsible_department_id' => $responsibleDepartment->id,
                'proposed_due_date' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertRedirect();
        $report->refresh();
        $this->actingAs($approver)
            ->post(route('ncr.manager-decision', $report), [
                'version_no' => $report->version_no,
                'decision' => 'APPROVED',
                'decision_note' => 'อนุมัติ',
            ])
            ->assertRedirect();

        $response = $this->actingAs($approver)->get(route('ncr.show', $report));

        $response->assertSee($responsibleDepartment->name_th);
        $response->assertSee('หน่วยงานรับผิดชอบ Corrective Action');
        $response->assertSee('รอหน่วยงานรับผิดชอบจัดทำ Corrective Action');
        $response->assertDontSee('data-ncr-scroll="#corrective-action"', false);
    }
}
