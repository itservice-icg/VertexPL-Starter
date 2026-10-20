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

class NcrDispositionRejectionRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private ProblemType $problemType;
    private SeverityLevel $severity;
    private DispositionType $dispositionType;

    protected function setUp(): void
    {
        parent::setUp();
        Unit::create(['code' => 'PCS', 'name_th' => 'ชิ้น', 'is_active' => true]);
        Product::create(['code' => 'P001', 'name_th' => 'Test Product', 'is_active' => true]);
        $this->department = Department::create(['code' => 'QA', 'name_th' => 'Quality Assurance', 'is_active' => true]);
        $this->problemType = ProblemType::create(['code' => 'PROCESS', 'name_th' => 'Process', 'is_active' => true]);
        $this->severity = SeverityLevel::create(['code' => 'HIGH', 'name_th' => 'High', 'is_active' => true]);
        $this->dispositionType = DispositionType::create(['code' => 'REWORK', 'name_th' => 'Rework', 'is_active' => true]);
    }

    public function test_approver_can_view_ncr_after_rejecting_disposition(): void
    {
        $reporter = $this->user(['ncr.create', 'ncr.submit']);
        $this->actingAs($reporter)->post(route('ncr.store'), $this->reportPayload())->assertRedirect();
        $report = NcrReport::firstOrFail();
        $this->actingAs($reporter)->post(route('ncr.submit', $report), ['version_no' => $report->version_no])->assertRedirect();

        $reviewer = $this->user(['ncr.review', 'ncr.disposition.manage']);
        $this->actingAs($reviewer)->post(route('ncr.initial-review', $report), [
            'version_no' => $report->fresh()->version_no,
            'review_result' => 'COMPLETE',
            'disposition_type_id' => $this->dispositionType->id,
            'disposition_detail' => 'Proceed with rework.',
            'responsible_department_id' => $this->department->id,
            'proposed_due_date' => now()->addDays(7)->format('Y-m-d'),
        ])->assertRedirect();
        $report->refresh();
        $report->refresh();

        $approver = $this->user(['ncr.view', 'ncr.disposition.approve']);
        $response = $this->actingAs($approver)->post(route('ncr.manager-decision', $report), [
            'version_no' => $report->version_no,
            'decision' => 'REJECTED',
            'decision_note' => 'Please revise the disposition.',
        ]);

        $response->assertRedirect(route('ncr.show', $report));
        $this->assertSame('RETURNED_FOR_DISPOSITION_REVISION', $report->fresh()->current_status);
        $reviewer = User::findOrFail($report->dispositions()->first()->proposed_by);
        $reviewer->givePermissionTo(Permission::findOrCreate('ncr.view'));
        $this->actingAs($reviewer)->get(route('ncr.show', $report))
            ->assertOk()
            ->assertSee('เหตุผลที่ส่งกลับ: Please revise the disposition.')
            ->assertSee('href="#disposition-revision"', false)
            ->assertSee('id="disposition-revision"', false);
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['department_id' => $this->department->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }

    private function reportPayload(): array
    {
        return [
            'report_date' => now()->format('Y-m-d'),
            'found_date' => now()->format('Y-m-d'),
            'problem_type_id' => $this->problemType->id,
            'severity_level_id' => $this->severity->id,
            'problem_description' => 'Disposition rejection redirect fixture.',
            'items' => [[
                'product_id' => Product::firstOrFail()->id,
                'product_name' => 'Test Product',
                'defect_quantity' => 1,
                'total_quantity' => 10,
                'unit_name' => 'PCS',
            ]],
        ];
    }
}
