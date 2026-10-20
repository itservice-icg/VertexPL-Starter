<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemovePendingQaClosureStatus extends Migration
{
    public function up(): void
    {
        DB::table('ncr_reports')
            ->where('current_status', 'PENDING_QA_CLOSURE')
            ->update(['current_status' => 'QA_VERIFIED']);

        DB::table('ncr_statuses')->where('code', 'PENDING_QA_CLOSURE')->delete();
    }

    public function down(): void
    {
        DB::table('ncr_statuses')->updateOrInsert(
            ['code' => 'PENDING_QA_CLOSURE'],
            [
                'name_th' => 'รอ QA ปิดงาน',
                'sort_order' => 12,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
