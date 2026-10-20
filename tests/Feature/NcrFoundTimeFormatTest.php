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

class NcrFoundTimeFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_draft_renders_database_time_in_html_time_format(): void
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
        $severity = SeverityLevel::create([
            'code' => 'HIGH',
            'name_th' => 'High',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->givePermissionTo(Permission::findOrCreate('ncr.view'));
        $user->givePermissionTo(Permission::findOrCreate('ncr.update'));

        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-TIME-00001',
            'report_date' => '2026-09-15',
            'found_date' => '2026-09-15',
            'found_time' => '11:28:00',
            'reporter_id' => $user->id,
            'reporter_department_id' => $department->id,
            'problem_type_id' => $problemType->id,
            'severity_level_id' => $severity->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'problem_description' => 'Time format regression fixture.',
            'current_status' => 'DRAFT',
            'current_owner_user_id' => $user->id,
            'current_owner_department_id' => $department->id,
            'version_no' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('ncr.edit', $report));
        $response->assertOk();
        preg_match('/name="found_time".*?value="([^"]*)"/s', $response->getContent(), $matches);
        $this->assertSame('11:28', $matches[1] ?? null);
    }
}