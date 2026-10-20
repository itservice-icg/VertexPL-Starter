<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrReport;
use App\Models\User;
use App\Services\NcrVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_does_not_bypass_ncr_gate_permissions(): void
    {
        $permission = Permission::findOrCreate('ncr.close', 'web');
        $unauthorized = User::factory()->create();
        $authorized = User::factory()->create();
        $authorized->givePermissionTo($permission);

        $this->withProductionEnvironment(function () use ($unauthorized, $authorized): void {
            $this->assertFalse(Gate::forUser($unauthorized)->allows('ncr.close'));
            $this->assertTrue(Gate::forUser($authorized)->allows('ncr.close'));
        });
    }

    public function test_production_visibility_does_not_bypass_ncr_scope(): void
    {
        $reporterDepartment = Department::create([
            'code' => 'MFG-AUTH',
            'name_th' => 'หน่วยงานผู้รายงาน',
            'is_active' => true,
        ]);
        $viewerDepartment = Department::create([
            'code' => 'QA-AUTH',
            'name_th' => 'หน่วยงานผู้ดู',
            'is_active' => true,
        ]);
        $reporter = User::factory()->create(['department_id' => $reporterDepartment->id]);
        $viewer = User::factory()->create(['department_id' => $viewerDepartment->id]);
        $viewer->givePermissionTo(Permission::findOrCreate('ncr.view', 'web'));

        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-AUTH-00001',
            'report_date' => '2026-09-14',
            'found_date' => '2026-09-14',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $reporterDepartment->id,
            'defect_quantity' => 1,
            'total_quantity' => 1,
            'problem_description' => 'Authorization regression fixture',
            'current_status' => 'DRAFT',
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        $this->withProductionEnvironment(function () use ($report, $reporter, $viewer): void {
            $visibility = app(NcrVisibilityService::class);

            $this->assertFalse($visibility->canView($report, $viewer));
            $this->assertSame([], $visibility->scope(NcrReport::query(), $viewer)->pluck('id')->all());
            $this->assertTrue($visibility->canView($report, $reporter));
        });
    }

    private function withProductionEnvironment(callable $callback): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            $callback();
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }
}
