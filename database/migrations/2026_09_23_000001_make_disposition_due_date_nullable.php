<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_dispositions', function (Blueprint $table): void {
            $table->date('proposed_due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('ncr_dispositions')->whereNull('proposed_due_date')->exists()) {
            throw new RuntimeException('Cannot make disposition due dates required while records without a due date exist.');
        }

        Schema::table('ncr_dispositions', function (Blueprint $table): void {
            $table->date('proposed_due_date')->nullable(false)->change();
        });
    }
};
