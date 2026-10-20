<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreserveFmDqa06Fields extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->string('legacy_form_scope_code', 10)->nullable()->after('ncr_no');
            $table->string('defect_unit_name', 50)->nullable()->after('defect_quantity');
            $table->string('total_unit_name', 50)->nullable()->after('total_quantity');
        });

        Schema::table('ncr_items', function (Blueprint $table) {
            $table->string('defect_unit_name', 50)->nullable()->after('defect_quantity');
            $table->string('total_unit_name', 50)->nullable()->after('total_quantity');
        });

        Schema::table('ncr_corrective_actions', function (Blueprint $table) {
            $table->timestamp('responsible_acknowledged_at')->nullable()->after('owner_user_id');
        });

        Schema::create('ncr_form_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('form_part');
            $table->string('signoff_stage', 50);
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('signer_name_snapshot')->nullable();
            $table->string('signoff_result', 50)->nullable();
            $table->text('signoff_note')->nullable();
            $table->timestamp('signed_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['ncr_report_id', 'form_part'], 'ncr_form_signoff_report_part_idx');
            $table->index(['ncr_report_id', 'signoff_stage'], 'ncr_form_signoff_report_stage_idx');
        });

        DB::table('ncr_reports')->whereNull('defect_unit_name')->update(['defect_unit_name' => DB::raw('unit_name')]);
        DB::table('ncr_reports')->whereNull('total_unit_name')->update(['total_unit_name' => DB::raw('unit_name')]);
        DB::table('ncr_items')->whereNull('defect_unit_name')->update(['defect_unit_name' => DB::raw('unit_name')]);
        DB::table('ncr_items')->whereNull('total_unit_name')->update(['total_unit_name' => DB::raw('unit_name')]);

        DB::table('disposition_types')->insertOrIgnore([
            'code' => 'CUSTOMER_COORDINATION',
            'name_th' => 'ฝ่ายขาย / ประสานงานลูกค้า',
            'description' => 'ตัวเลือกจากแบบฟอร์ม FM-DQA-06 Rev.03',
            'require_approval' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ncr_form_signoffs');

        Schema::table('ncr_corrective_actions', function (Blueprint $table) {
            $table->dropColumn('responsible_acknowledged_at');
        });

        Schema::table('ncr_items', function (Blueprint $table) {
            $table->dropColumn(['defect_unit_name', 'total_unit_name']);
        });

        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropColumn(['legacy_form_scope_code', 'defect_unit_name', 'total_unit_name']);
        });

        // Master data is intentionally retained on rollback to avoid deleting a value
        // that may already be referenced by production NCR records.
    }
}
