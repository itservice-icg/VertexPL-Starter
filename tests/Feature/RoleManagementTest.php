<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_list_can_search_and_shows_user_and_permission_counts()
    {
        $admin = $this->userWithPermissions(['roles.view']);
        $role = Role::create(['name' => 'Warehouse Manager']);
        $permission = Permission::findOrCreate('dashboard.view');
        $role->givePermissionTo($permission);
        User::factory()->create()->assignRole($role);
        Role::create(['name' => 'Unrelated']);

        $this->actingAs($admin)
            ->get(route('roles.index', ['search' => 'Warehouse']))
            ->assertOk()
            ->assertSee('Warehouse Manager')
            ->assertDontSee('Unrelated')
            ->assertViewHas('roles', function ($roles) use ($role) {
                $result = $roles->firstWhere('id', $role->id);

                return $result
                    && $result->users_count === 1
                    && $result->permissions_count === 1;
            });
    }

    public function test_role_list_is_paginated()
    {
        $admin = $this->userWithPermissions(['roles.view']);

        foreach (range(1, 12) as $index) {
            Role::create(['name' => "Role {$index}"]);
        }

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => $roles->perPage() === 10 && $roles->total() === 12);
    }

    public function test_authorized_user_can_create_role_with_permissions()
    {
        $admin = $this->userWithPermissions([
            'roles.create',
            'permissions.view',
            'permissions.manage',
        ]);
        $permissions = collect([
            Permission::findOrCreate('dashboard.view'),
            Permission::findOrCreate('users.view'),
        ]);

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Operator',
                'permissions' => $permissions->pluck('id')->all(),
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::findByName('Operator');
        $this->assertTrue($role->hasAllPermissions($permissions));
    }

    public function test_permission_matrix_can_be_rendered_for_create_and_edit()
    {
        $admin = $this->userWithPermissions([
            'roles.create',
            'roles.update',
            'permissions.view',
        ]);
        $permission = Permission::findOrCreate('users.view');
        $role = Role::create(['name' => 'Viewer']);
        $role->givePermissionTo($permission);

        $this->actingAs($admin)
            ->get(route('roles.create'))
            ->assertOk()
            ->assertSee('Permission Matrix')
            ->assertSee('users.view')
            ->assertDontSee('exports.create');

        $this->actingAs($admin)
            ->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee('Permission Matrix')
            ->assertSee('users.view')
            ->assertDontSee('exports.create');
    }

    public function test_unused_export_permission_cannot_be_assigned_through_a_direct_request(): void
    {
        $admin = $this->userWithPermissions([
            'roles.create',
            'permissions.manage',
        ]);
        $exportPermission = Permission::findOrCreate('exports.create');

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Export Operator',
                'permissions' => [$exportPermission->id],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Export Operator']);
    }

    public function test_permission_matrix_shows_all_fifteen_ncr_permissions_in_workflow_order(): void
    {
        $admin = $this->userWithPermissions([
            'roles.create',
            'permissions.view',
        ]);
        foreach ([
            'dashboard.view',
            'users.view',
            'departments.view',
            'roles.view',
            'permissions.manage',
            'imports.view',
            'system.settings',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        $allLabels = config('permission_labels');
        $labels = array_filter(
            $allLabels,
            function ($label, $name) {
                return strpos($name, 'ncr.') === 0;
            },
            ARRAY_FILTER_USE_BOTH
        );
        $this->assertCount(15, $labels);

        foreach (array_keys($allLabels) as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        $response = $this->actingAs($admin)->get(route('roles.create'))->assertOk();
        foreach (['Dashboard', 'NCR', 'ผู้ใช้งาน', 'หน่วยงาน', 'บทบาทและสิทธิ์', 'Import / Export', 'ระบบ'] as $groupLabel) {
            $response->assertSee($groupLabel);
        }
        $response->assertViewHas('permissionGroups', function ($groups) {
            return $groups->where('label', 'บทบาทและสิทธิ์')->count() === 1;
        });

        foreach ($allLabels as $permissionName => $thaiLabel) {
            $response->assertSee($permissionName)->assertSee($thaiLabel);
        }

        $response->assertSeeInOrder(array_keys($labels));
    }

    public function test_role_name_must_be_unique()
    {
        $admin = $this->userWithPermissions([
            'roles.create',
            'permissions.manage',
        ]);
        Role::create(['name' => 'Viewer']);

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Viewer',
                'permissions' => [],
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_authorized_user_can_update_role_permissions()
    {
        $admin = $this->userWithPermissions([
            'roles.update',
            'permissions.view',
            'permissions.manage',
        ]);
        $role = Role::create(['name' => 'Operator']);
        $oldPermission = Permission::findOrCreate('dashboard.view');
        $newPermission = Permission::findOrCreate('users.view');
        $role->givePermissionTo($oldPermission);

        $this->actingAs($admin)
            ->put(route('roles.update', $role), [
                'name' => 'Senior Operator',
                'permissions' => [$newPermission->id],
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Senior Operator', $role->name);
        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertFalse($role->hasPermissionTo('dashboard.view'));
    }

    public function test_user_can_remove_permissions_from_their_own_role()
    {
        $admin = $this->userWithPermissions([
            'roles.update',
            'permissions.manage',
        ]);
        $role = Role::create(['name' => 'Administrator']);
        $viewPermission = Permission::findOrCreate('roles.view');
        $updatePermission = Permission::findOrCreate('roles.update');
        $role->syncPermissions([$viewPermission, $updatePermission]);
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->put(route('roles.update', $role), [
                'name' => 'Administrator',
                'permissions' => [$viewPermission->id],
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertFalse($role->fresh()->hasPermissionTo('roles.update'));
    }

    public function test_super_admin_can_not_be_renamed_or_deleted()
    {
        $admin = $this->userWithPermissions([
            'roles.update',
            'roles.delete',
            'permissions.manage',
        ]);
        $superAdmin = Role::create(['name' => 'Super Admin']);

        $this->actingAs($admin)
            ->put(route('roles.update', $superAdmin), [
                'name' => 'Renamed Admin',
                'permissions' => [],
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $superAdmin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $superAdmin->id, 'name' => 'Super Admin']);
    }

    public function test_role_with_users_can_not_be_deleted()
    {
        $admin = $this->userWithPermissions(['roles.delete']);
        $role = Role::create(['name' => 'Viewer']);
        User::factory()->create()->assignRole($role);

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_empty_role_can_be_deleted()
    {
        $admin = $this->userWithPermissions(['roles.delete']);
        $role = Role::create(['name' => 'Temporary']);

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_actions_require_role_and_permission_permissions()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Viewer']);

        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('roles.create'))->assertForbidden();
        $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Blocked',
            'permissions' => [],
        ])->assertForbidden();
        $this->actingAs($user)->get(route('roles.edit', $role))->assertForbidden();
        $this->actingAs($user)->put(route('roles.update', $role), [])->assertForbidden();
        $this->actingAs($user)->delete(route('roles.destroy', $role))->assertForbidden();
    }

    public function test_permissions_manage_is_required_to_save_role_permissions()
    {
        $user = $this->userWithPermissions(['roles.create', 'permissions.view']);

        $this->actingAs($user)
            ->post(route('roles.store'), [
                'name' => 'Blocked',
                'permissions' => [],
            ])
            ->assertForbidden();
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
