<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveCancelledNcrStatus extends Migration
{
    public function up(): void
    {
        DB::table('ncr_statuses')->where('code', 'CANCELLED')->delete();
    }

    public function down(): void
    {
        DB::table('ncr_statuses')->updateOrInsert(
            ['code' => 'CANCELLED'],
            [
                'name_th' => 'ยกเลิก',
                'sort_order' => 15,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
