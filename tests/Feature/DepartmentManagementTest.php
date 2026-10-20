<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_user_without_permission_is_forbidden(): void
    {
        $this->get(route('departments.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('departments.index'))->assertForbidden();
    }

    public function test_authorized_user_can_search_and_filter_departments(): void
    {
        $user = $this->userWithPermissions(['departments.view']);
        Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);
        Department::create(['code' => 'OLD', 'name_th' => 'หน่วยงานเดิม', 'is_active' => false]);

        $this->actingAs($user)
            ->get(route('departments.index', ['search' => 'QA', 'status' => 'active']))
            ->assertOk()
            ->assertSee('ประกันคุณภาพ')
            ->assertDontSee('หน่วยงานเดิม');
    }

    public function test_authorized_user_can_create_update_and_soft_delete_unused_department(): void
    {
        $user = $this->userWithPermissions([
            'departments.create',
            'departments.update',
            'departments.delete',
        ]);

        $this->actingAs($user)->post(route('departments.store'), [
            'code' => 'QA',
            'name_th' => 'ประกันคุณภาพ',
            'name_en' => 'Quality Assurance',
            'is_active' => '1',
        ])->assertRedirect();

        $department = Department::where('code', 'QA')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'departments.created']);

        $this->actingAs($user)->put(route('departments.update', $department), [
            'code' => 'QA',
            'name_th' => 'ฝ่ายประกันคุณภาพ',
            'name_en' => 'Quality Assurance',
            'is_active' => '0',
        ])->assertRedirect(route('departments.index'));

        $this->assertFalse($department->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'departments.updated']);

        $this->actingAs($user)->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'));

        $this->assertSoftDeleted('departments', ['id' => $department->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'departments.deleted']);
    }

    public function test_department_code_is_unique_and_validated(): void
    {
        $user = $this->userWithPermissions(['departments.create']);
        Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);

        $this->actingAs($user)->post(route('departments.store'), [
            'code' => 'QA',
            'name_th' => 'ซ้ำ',
            'is_active' => '1',
        ])->assertSessionHasErrors('code');

        $this->actingAs($user)->post(route('departments.store'), [
            'code' => 'Invalid Code!',
            'name_th' => 'ไม่ถูกต้อง',
            'is_active' => '1',
        ])->assertSessionHasErrors('code');
    }

    public function test_department_with_users_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['departments.delete']);
        $department = Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);
        User::factory()->create(['department_id' => $department->id]);

        $this->actingAs($user)->delete(route('departments.destroy', $department))
            ->assertSessionHas('error');

        $this->assertNotNull($department->fresh());
    }

    public function test_each_department_action_requires_its_permission(): void
    {
        $user = User::factory()->create();
        $department = Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);

        $this->actingAs($user)->get(route('departments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('departments.create'))->assertForbidden();
        $this->actingAs($user)->post(route('departments.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('departments.edit', $department))->assertForbidden();
        $this->actingAs($user)->put(route('departments.update', $department), [])->assertForbidden();
        $this->actingAs($user)->delete(route('departments.destroy', $department))->assertForbidden();
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}
