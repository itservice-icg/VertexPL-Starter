<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NcrDashboardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dashboard_shows_recent_reports_from_all_departments(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $adminDepartment = $this->department('ADMIN-DASH', 'หน่วยงานผู้ดูแล');
        $firstDepartment = $this->department('MFG-DASH', 'ฝ่ายผลิต');
        $secondDepartment = $this->department('QA-DASH', 'ฝ่ายประกันคุณภาพ');
        $admin = User::factory()->create(['department_id' => $adminDepartment->id]);
        $admin->assignRole(Role::findByName('Administrator', 'web'));

        $this->report('NCR-2026-MFG-00001', $firstDepartment);
        $this->report('NCR-2026-QA-00001', $secondDepartment);

        $response = $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('รายการจากทุกหน่วยงาน สูงสุด 12 รายการ')
            ->assertSee('NCR-2026-MFG-00001')
            ->assertSee('NCR-2026-QA-00001');

        $this->assertSame(2, $response->viewData('open'));
    }

    public function test_regular_user_dashboard_counts_and_lists_only_its_department_reports(): void
    {
        $userDepartment = $this->department('USER-DASH', 'ฝ่ายผู้ใช้');
        $otherDepartment = $this->department('OTHER-DASH', 'อีกหน่วยงาน');
        $user = User::factory()->create(['department_id' => $userDepartment->id]);
        $user->givePermissionTo(
            Permission::findOrCreate('dashboard.view', 'web'),
            Permission::findOrCreate('ncr.view', 'web')
        );

        $this->report('NCR-2026-USER-00001', $userDepartment, $user);
        $this->report('NCR-2026-OTHER-00001', $otherDepartment);

        $response = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('NCR-2026-USER-00001')
            ->assertDontSee('NCR-2026-OTHER-00001');

        $this->assertSame(1, $response->viewData('open'));
        $this->assertSame(1, $response->viewData('counts')['ACTION_IN_PROGRESS']);
        $this->assertCount(1, $response->viewData('recent'));
    }

    private function department(string $code, string $name): Department
    {
        return Department::create(['code' => $code, 'name_th' => $name, 'is_active' => true]);
    }

    private function report(string $number, Department $department, ?User $reporter = null): NcrReport
    {
        $reporter ??= User::factory()->create(['department_id' => $department->id]);

        return NcrReport::create([
            'ncr_no' => $number,
            'report_date' => '2026-10-15',
            'found_date' => '2026-10-15',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $department->id,
            'defect_quantity' => 1,
            'total_quantity' => 1,
            'problem_description' => 'Dashboard department visibility fixture',
            'current_status' => 'ACTION_IN_PROGRESS',
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);
    }
}
