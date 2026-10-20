<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RemoveLegacyNcrActionApprovePermission extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        DB::table($table)
            ->where('name', 'ncr.action.approve')
            ->where('guard_name', 'web')
            ->delete();

        $viewAll = Permission::findOrCreate('ncr.view.all', 'web');
        $superAdmin = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo($viewAll);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        DB::table($table)->insertOrIgnore([
            'name' => 'ncr.action.approve',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
