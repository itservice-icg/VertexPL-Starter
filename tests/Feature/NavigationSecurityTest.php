<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_dashboard_permission()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_sidebar_only_shows_items_allowed_by_permissions()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'dashboard.view']));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('NCR')
            ->assertDontSee('ผู้ใช้งาน')
            ->assertDontSee('หน่วยงาน')
            ->assertDontSee('ตั้งค่าระบบ');
    }

    public function test_authorized_user_can_open_protected_management_page()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'users.view']));

        $this->actingAs($user)
            ->get('/users')
            ->assertOk()
            ->assertSee('ผู้ใช้งาน');
    }

    public function test_unauthorized_user_can_not_open_management_page_directly()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_user_with_ncr_permission_can_see_and_open_ncr()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'ncr.view']));

        $this->actingAs($user)
            ->get(route('ncr.index'))
            ->assertOk()
            ->assertSee('NCR')
            ->assertSee('Non-Conformance Report');
    }

    public function test_my_tasks_only_marks_my_tasks_as_active()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'ncr.view']));

        $response = $this->actingAs($user)->get(route('ncr.tasks'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            'href="'.route('ncr.index').'" class="text-slate-300 hover:bg-slate-800 hover:text-white flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"',
            $html
        );
        $this->assertStringContainsString(
            'href="'.route('ncr.tasks').'" class="bg-indigo-500 text-white flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition"',
            $html
        );
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function test_user_with_department_permission_can_see_and_open_departments()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'departments.view']));

        $this->actingAs($user)
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('หน่วยงาน');
    }

    public function test_super_admin_bypasses_permission_checks()
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'Super Admin']));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }
}
