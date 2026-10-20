<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DispositionType;
use App\Models\NcrDisposition;
use App\Models\NcrReport;
use App\Models\ProblemType;
use App\Models\SeverityLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrIndexDispositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ncr_register_displays_the_latest_disposition_revision(): void
    {
        $department = Department::create([
            'code' => 'ENG',
            'name_th' => 'ฝ่ายวิศวกรรม',
            'is_active' => true,
        ]);
        $problemType = ProblemType::create([
            'code' => 'PROCESS',
            'name_th' => 'กระบวนการ',
            'is_active' => true,
        ]);
        $severity = SeverityLevel::create([
            'code' => 'LOW',
            'name_th' => 'ต่ำ',
            'is_active' => true,
        ]);
        $rework = DispositionType::create([
            'code' => 'REWORK',
            'name_th' => 'แก้ไขงาน',
            'is_active' => true,
        ]);
        $scrap = DispositionType::create([
            'code' => 'SCRAP',
            'name_th' => 'เสนอทำลาย',
            'is_active' => true,
        ]);
        $reporter = User::factory()->create(['department_id' => $department->id]);
        $viewer = User::factory()->create(['department_id' => $department->id]);
        $viewer->givePermissionTo([
            Permission::findOrCreate('ncr.view'),
            Permission::findOrCreate('ncr.view.all'),
        ]);

        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-INDEX-DISPOSITION',
            'report_date' => '2026-10-19',
            'found_date' => '2026-10-19',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $department->id,
            'related_department_id' => $department->id,
            'problem_type_id' => $problemType->id,
            'severity_level_id' => $severity->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'problem_description' => 'Disposition list presentation test',
            'current_status' => 'CLOSED',
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        foreach ([
            ['type' => $rework, 'revision' => 1, 'detail' => 'Old proposal should not be displayed.'],
            ['type' => $scrap, 'revision' => 2, 'detail' => 'Latest proposal is destruction.'],
        ] as $revision) {
            NcrDisposition::create([
                'ncr_report_id' => $report->id,
                'disposition_type_id' => $revision['type']->id,
                'revision_no' => $revision['revision'],
                'disposition_detail' => $revision['detail'],
                'responsible_department_id' => $department->id,
                'proposed_due_date' => '2026-10-26',
                'status' => $revision['revision'] === 2 ? 'APPROVED' : 'REJECTED',
                'proposed_at' => now(),
                'proposed_by' => $reporter->id,
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('ncr.index', ['status' => 'CLOSED']));

        $response->assertOk();
        $response->assertSee('ข้อเสนอแนะ');
        $response->assertSee('SCRAP / เสนอทำลาย');
        $response->assertSee('Latest proposal is destruction.');
        $response->assertDontSee('REWORK / แก้ไขงาน');
        $response->assertDontSee('Old proposal should not be displayed.');
    }
}
