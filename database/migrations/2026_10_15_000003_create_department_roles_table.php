<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateDepartmentRolesTable extends Migration
{
    public function up(): void
    {
        Schema::create('department_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_th', 100)->unique();
            $table->boolean('can_approve_corrective_action')->default(false);
            $table->timestamps();
        });

        DB::table('department_roles')->insert([
            [
                'code' => 'MEMBER',
                'name_th' => 'สมาชิก',
                'can_approve_corrective_action' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'DEPARTMENT_HEAD',
                'name_th' => 'หัวหน้าหน่วยงาน',
                'can_approve_corrective_action' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_role')
                ->references('code')
                ->on('department_roles')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        $permission = Permission::findOrCreate('department-roles.manage', 'web');
        $superAdmin = Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_role']);
        });

        $permission = Permission::query()->where('name', 'department-roles.manage')->where('guard_name', 'web')->first();
        if ($permission) {
            Role::query()->where('guard_name', 'web')->get()->each(function (Role $role) use ($permission) {
                $role->revokePermissionTo($permission);
            });
            $permission->delete();
        }

        Schema::dropIfExists('department_roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
