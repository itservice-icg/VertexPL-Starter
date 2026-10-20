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

class NcrDispositionApprovalRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Department $reporterDepartment;
    private Department $responsibleDepartment;
    private ProblemType $problemType;
    private SeverityLevel $severity;
    private DispositionType $dispositionType;

    protected function setUp(): void
    {
        parent::setUp();

        Unit::create(['code' => 'PCS', 'name_th' => 'ชิ้น', 'is_active' => true]);
        Product::create(['code' => 'P001', 'name_th' => 'Test Product', 'is_active' => true]);

        $this->reporterDepartment = Department::create([
            'code' => 'QA-APP',
            'name_th' => 'Quality Assurance',
            'is_active' => true,
        ]);
        $this->responsibleDepartment = Department::create([
            'code' => 'OPS-APP',
            'name_th' => 'Operations',
            'is_active' => true,
        ]);
        $this->problemType = ProblemType::create([
            'code' => 'PROCESS',
            'name_th' => 'Process',
            'is_active' => true,
        ]);
        $this->severity = SeverityLevel::create([
            'code' => 'HIGH',
            'name_th' => 'High',
            'is_active' => true,
        ]);
        $this->dispositionType = DispositionType::create([
            'code' => 'REWORK',
            'name_th' => 'Rework',
            'is_active' => true,
        ]);
    }

    public function test_approver_is_redirected_to_visible_queue_when_approval_moves_ncr_to_responsible_department(): void
    {
        $reporter = $this->user(['ncr.create', 'ncr.submit'], $this->reporterDepartment);

        $this->actingAs($reporter)->post(route('ncr.store'), [
            'report_date' => now()->format('Y-m-d'),
            'found_date' => now()->format('Y-m-d'),
            'problem_type_id' => $this->problemType->id,
            'severity_level_id' => $this->severity->id,
            'problem_description' => 'Disposition approval redirect fixture.',
            'items' => [[
                'product_id' => Product::firstOrFail()->id,
                'product_name' => 'Test Product',
                'defect_quantity' => 1,
                'total_quantity' => 10,
                'unit_name' => 'PCS',
            ]],
        ])->assertRedirect();

        $report = NcrReport::firstOrFail();

        $this->actingAs($reporter)
            ->post(route('ncr.submit', $report), ['version_no' => $report->version_no])
            ->assertRedirect();

        $reviewer = $this->user(['ncr.review', 'ncr.disposition.manage'], $this->reporterDepartment);
        $this->actingAs($reviewer)
            ->post(route('ncr.initial-review', $report), [
                'version_no' => $report->fresh()->version_no,
                'review_result' => 'COMPLETE',
                'disposition_type_id' => $this->dispositionType->id,
                'disposition_detail' => 'Proceed with rework.',
                'responsible_department_id' => $this->responsibleDepartment->id,
                'proposed_due_date' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertRedirect();

        $report->refresh();

        $approver = $this->user(['ncr.view', 'ncr.disposition.approve'], $this->reporterDepartment);
        $report->refresh();

        $response = $this->actingAs($approver)->post(route('ncr.manager-decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'APPROVED',
        ]);

        $response->assertRedirect(route('ncr.show', $report));
        $this->assertSame('DISPOSITION_APPROVED', $report->fresh()->current_status);
        $this->assertSame(
            $this->responsibleDepartment->id,
            $report->fresh()->current_owner_department_id
        );

        foreach ([
            'DISPOSITION_APPROVED',
            'ACTION_IN_PROGRESS',
            'PENDING_ACTION_VERIFICATION',
            'RETURNED_FOR_REWORK',
            'QA_VERIFIED',
            'CLOSED',
        ] as $status) {
            $report->update(['current_status' => $status]);

            $this->actingAs($approver)
                ->get(route('ncr.show', $report))
                ->assertOk()
                ->assertSee($report->ncr_no);
        }
    }

    private function user(array $permissions, Department $department): User
    {
        $user = User::factory()->create(['department_id' => $department->id]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}
