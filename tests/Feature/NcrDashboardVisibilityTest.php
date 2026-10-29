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
            ->assertSee('รายการจากทุกหน่วยงาน สูงสุด 10 รายการ')
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

    public function test_dashboard_metrics_link_to_filtered_reports_in_the_same_scope(): void
    {
        $userDepartment = $this->department('USER-PRIORITY', 'ฝ่ายผู้ใช้');
        $otherDepartment = $this->department('OTHER-PRIORITY', 'อีกหน่วยงาน');
        $user = User::factory()->create(['department_id' => $userDepartment->id]);
        $user->givePermissionTo(
            Permission::findOrCreate('dashboard.view', 'web'),
            Permission::findOrCreate('ncr.view', 'web')
        );

        $overdue = $this->report('NCR-2026-USER-OVERDUE', $userDepartment, $user);
        $overdue->update(['due_date' => now()->subDay()->toDateString()]);

        $notYetDue = $this->report('NCR-2026-USER-NOT-DUE', $userDepartment, $user);
        $notYetDue->update(['due_date' => now()->addDay()->toDateString()]);

        $closedOverdue = $this->report('NCR-2026-USER-CLOSED', $userDepartment, $user);
        $closedOverdue->update(['current_status' => 'CLOSED', 'due_date' => now()->subDay()->toDateString()]);

        $draftOverdue = $this->report('NCR-2026-USER-DRAFT', $userDepartment, $user);
        $draftOverdue->update(['current_status' => 'DRAFT', 'due_date' => now()->subDay()->toDateString()]);

        $managerApproval = $this->report('NCR-2026-USER-MANAGER', $userDepartment, $user);
        $managerApproval->update(['current_status' => 'PENDING_MANAGER_APPROVAL']);

        $destructionApproval = $this->report('NCR-2026-USER-SCRAP', $userDepartment, $user);
        $destructionApproval->update(['current_status' => 'PENDING_DESTRUCTION_APPROVAL']);

        $outsideScope = $this->report('NCR-2026-OTHER-OVERDUE', $otherDepartment);
        $outsideScope->update([
            'current_owner_user_id' => $user->id,
            'current_owner_department_id' => $otherDepartment->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $dashboard = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(str_replace('&', '&amp;', route('ncr.index', ['overdue' => 1, 'dashboard_scope' => 1])), false)
            ->assertSee(str_replace('&', '&amp;', route('ncr.index', ['status' => 'PENDING_MANAGER_APPROVAL', 'dashboard_scope' => 1])), false)
            ->assertSee(str_replace('&', '&amp;', route('ncr.index', ['status' => 'PENDING_DESTRUCTION_APPROVAL', 'dashboard_scope' => 1])), false);
        $this->assertSame(1, $dashboard->viewData('overdue'));

        $overdueList = $this->get(route('ncr.index', ['overdue' => 1, 'dashboard_scope' => 1]))
            ->assertOk()
            ->assertSee('NCR-2026-USER-OVERDUE')
            ->assertDontSee('NCR-2026-USER-NOT-DUE')
            ->assertDontSee('NCR-2026-USER-CLOSED')
            ->assertDontSee('NCR-2026-USER-DRAFT')
            ->assertDontSee('NCR-2026-OTHER-OVERDUE');
        $this->assertSame(1, $overdueList->viewData('reports')->total());
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
