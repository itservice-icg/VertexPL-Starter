<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrReport;
use App\Models\ProblemType;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrFlowBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private ProblemType $problemType;
    private SeverityLevel $severityLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'code' => 'QA',
            'name_th' => 'Quality Assurance',
            'is_active' => true,
        ]);
        $this->problemType = ProblemType::create([
            'code' => 'PROCESS',
            'name_th' => 'Process',
            'is_active' => true,
        ]);
        $this->severityLevel = SeverityLevel::create([
            'code' => 'HIGH',
            'name_th' => 'High',
            'is_active' => true,
        ]);
    }

    public function test_initial_review_post_from_draft_returns_conflict_without_mutating_workflow(): void
    {
        $report = $this->draftReport();
        $reviewer = $this->user(['ncr.review']);
        $version = $report->version_no;

        $this->actingAs($reviewer)
            ->post(route('ncr.initial-review', $report), [
                'version_no' => $version,
                'review_result' => 'INCOMPLETE',
                'completeness_note' => 'Required information is missing',
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('ncr_reports', [
            'id' => $report->id,
            'current_status' => 'DRAFT',
            'version_no' => $version,
        ]);
        $this->assertDatabaseCount('ncr_initial_reviews', 0);
        $this->assertDatabaseCount('ncr_status_history', 0);
    }

    public function test_disposition_submission_post_from_draft_returns_conflict_without_mutating_workflow(): void
    {
        $report = $this->draftReport();
        $reviewer = $this->user(['ncr.disposition.manage']);
        $version = $report->version_no;

        $this->actingAs($reviewer)
            ->post(route('ncr.disposition.submit', $report), [
                'version_no' => $version,
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('ncr_reports', [
            'id' => $report->id,
            'current_status' => 'DRAFT',
            'version_no' => $version,
        ]);
        $this->assertDatabaseCount('ncr_dispositions', 0);
        $this->assertDatabaseCount('ncr_status_history', 0);
    }

    private function draftReport(): NcrReport
    {
        $reporter = $this->user([]);

        return NcrReport::create([
            'ncr_no' => 'NCR-2026-BOUNDARY-' . $reporter->id,
            'report_date' => '2026-08-20',
            'found_date' => '2026-08-20',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $this->department->id,
            'problem_type_id' => $this->problemType->id,
            'severity_level_id' => $this->severityLevel->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'problem_description' => 'Boundary test report',
            'current_status' => 'DRAFT',
            'current_owner_user_id' => $reporter->id,
            'current_owner_department_id' => $this->department->id,
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create([
            'department_id' => $this->department->id,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}
