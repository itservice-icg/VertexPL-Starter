<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
            ->assertDontSee('ผู้ใช้งาน')
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
}
