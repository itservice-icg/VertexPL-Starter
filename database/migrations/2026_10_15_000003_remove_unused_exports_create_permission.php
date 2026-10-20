<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RemoveUnusedExportsCreatePermission extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        DB::table($table)
            ->where('name', 'exports.create')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        DB::table($table)->insertOrIgnore([
            'name' => 'exports.create',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
