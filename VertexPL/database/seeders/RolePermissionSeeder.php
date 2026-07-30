<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.manage',
            'imports.view',
            'imports.create',
            'exports.create',
            'system.settings',
            'system.audit.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        $administrator = Role::firstOrCreate([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        $viewer = Role::firstOrCreate([
            'name' => 'Viewer',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions(Permission::all());
        $administrator->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'roles.view',
            'imports.view',
            'imports.create',
            'exports.create',
        ]);
        $viewer->syncPermissions(['dashboard.view']);

        $email = env('VERTEXPL_ADMIN_EMAIL');
        $password = env('VERTEXPL_ADMIN_PASSWORD');

        if (!$email || !$password) {
            if ($this->command) {
                $this->command->warn(
                    'ข้ามการสร้างบัญชีเริ่มต้น: กรุณากำหนด VERTEXPL_ADMIN_EMAIL และ VERTEXPL_ADMIN_PASSWORD'
                );
            }
            return;
        }

        Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'password' => ['required', Password::min(14)->letters()->mixedCase()->numbers()->symbols()],
            ]
        )->validate();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'VertexPL Administrator',
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );

        $user->syncRoles([$superAdmin]);
    }
}
