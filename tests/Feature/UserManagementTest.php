<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_can_search_and_filter_by_status_and_role()
    {
        $admin = $this->userWithPermissions(['users.view']);
        $viewer = Role::create(['name' => 'Viewer']);
        $otherRole = Role::create(['name' => 'Administrator']);

        $matching = User::factory()->create([
            'name' => 'Somchai Active',
            'email' => 'somchai@example.com',
            'is_active' => true,
        ]);
        $matching->assignRole($viewer);

        $wrongStatus = User::factory()->create([
            'name' => 'Somchai Inactive',
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);
        $wrongStatus->assignRole($viewer);

        $wrongRole = User::factory()->create([
            'name' => 'Somchai Other',
            'email' => 'other@example.com',
            'is_active' => true,
        ]);
        $wrongRole->assignRole($otherRole);

        $this->actingAs($admin)
            ->get(route('users.index', [
                'search' => 'Somchai',
                'status' => 'active',
                'role' => $viewer->id,
            ]))
            ->assertOk()
            ->assertSee('somchai@example.com')
            ->assertDontSee('inactive@example.com')
            ->assertDontSee('other@example.com');
    }

    public function test_user_list_is_paginated()
    {
        $admin = $this->userWithPermissions(['users.view']);
        User::factory()->count(12)->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertViewHas('users', fn ($users) => $users->perPage() === 10 && $users->total() === 13);
    }

    public function test_authorized_user_can_create_a_user_with_role()
    {
        $admin = $this->userWithPermissions(['users.create']);
        $role = Role::create(['name' => 'Viewer']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'role_id' => $role->id,
            'is_active' => '1',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPassword1!', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('Viewer'));
    }

    public function test_authorized_user_can_assign_and_filter_users_by_department()
    {
        $admin = $this->userWithPermissions(['users.create', 'users.view']);
        $role = Role::create(['name' => 'Viewer']);
        $department = Department::create([
            'code' => 'QA',
            'name_th' => 'ประกันคุณภาพ',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Department User',
            'email' => 'department@example.com',
            'department_id' => $department->id,
            'role_id' => $role->id,
            'is_active' => '1',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'department@example.com')->firstOrFail();
        $this->assertTrue($created->department->is($department));

        $this->actingAs($admin)
            ->get(route('users.index', ['department' => $department->id]))
            ->assertOk()
            ->assertSee('department@example.com');
    }

    public function test_inactive_department_cannot_be_newly_assigned()
    {
        $admin = $this->userWithPermissions(['users.create']);
        $role = Role::create(['name' => 'Viewer']);
        $department = Department::create([
            'code' => 'OLD',
            'name_th' => 'หน่วยงานเดิม',
            'is_active' => false,
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Invalid Department User',
            'email' => 'invalid-department@example.com',
            'department_id' => $department->id,
            'role_id' => $role->id,
            'is_active' => '1',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])->assertSessionHasErrors('department_id');
    }

    public function test_email_must_be_unique_and_password_must_follow_policy()
    {
        $admin = $this->userWithPermissions(['users.create']);
        $role = Role::create(['name' => 'Viewer']);
        User::factory()->create(['email' => 'existing@example.com']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Invalid User',
                'email' => 'existing@example.com',
                'role_id' => $role->id,
                'is_active' => '1',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_authorized_user_can_update_user_and_change_role()
    {
        $admin = $this->userWithPermissions(['users.update']);
        $oldRole = Role::create(['name' => 'Viewer']);
        $newRole = Role::create(['name' => 'Administrator']);
        $target = User::factory()->create();
        $target->assignRole($oldRole);

        $this->actingAs($admin)
            ->put(route('users.update', $target), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'role_id' => $newRole->id,
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('updated@example.com', $target->email);
        $this->assertFalse($target->is_active);
        $this->assertTrue($target->hasRole('Administrator'));
    }

    public function test_user_can_not_deactivate_their_own_account()
    {
        $admin = $this->userWithPermissions(['users.update']);
        $role = Role::create(['name' => 'Administrator']);
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role_id' => $role->id,
                'is_active' => '0',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_user_can_not_delete_their_own_account()
    {
        $admin = $this->userWithPermissions(['users.delete']);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertFalse($admin->fresh()->trashed());
    }

    public function test_deleting_another_user_uses_soft_delete()
    {
        $admin = $this->userWithPermissions(['users.delete']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_each_action_requires_its_permission()
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $role = Role::create(['name' => 'Viewer']);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'role_id' => $role->id,
            'is_active' => '1',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])->assertForbidden();
        $this->actingAs($user)->get(route('users.edit', $target))->assertForbidden();
        $this->actingAs($user)->put(route('users.update', $target), [])->assertForbidden();
        $this->actingAs($user)->delete(route('users.destroy', $target))->assertForbidden();
    }

    public function test_user_create_and_edit_actions_are_available_as_dialogs_on_the_list()
    {
        $admin = $this->userWithPermissions(['users.view', 'users.create', 'users.update']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('id="create-user"', false)
            ->assertSee('data-ncr-dialog-open="edit-user-'.$admin->id.'"', false)
            ->assertSee('id="edit-user-'.$admin->id.'"', false)
            ->assertSee(route('users.update', $admin), false);
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
