<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('disposition_types')
            ->where('code', 'RETURN_TO_SUPPLIER')
            ->update([
                'name_th' => 'RETURN / ส่งคืน / แลกเปลี่ยน / ลดหนี้ / ชดเชย',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('disposition_types')
            ->where('code', 'RETURN_TO_SUPPLIER')
            ->update([
                'name_th' => 'ส่งคืนผู้ผลิต',
                'updated_at' => now(),
            ]);
    }
};
