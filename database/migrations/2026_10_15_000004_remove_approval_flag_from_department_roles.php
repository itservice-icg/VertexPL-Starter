<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoveApprovalFlagFromDepartmentRoles extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('department_roles') && Schema::hasColumn('department_roles', 'can_approve_corrective_action')) {
            Schema::table('department_roles', function (Blueprint $table) {
                $table->dropColumn('can_approve_corrective_action');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('department_roles') && ! Schema::hasColumn('department_roles', 'can_approve_corrective_action')) {
            Schema::table('department_roles', function (Blueprint $table) {
                $table->boolean('can_approve_corrective_action')->default(false);
            });

            DB::table('department_roles')->where('code', 'DEPARTMENT_HEAD')
                ->update(['can_approve_corrective_action' => true]);
        }
    }
}
