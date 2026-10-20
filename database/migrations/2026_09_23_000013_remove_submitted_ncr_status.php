<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveSubmittedNcrStatus extends Migration
{
    public function up(): void
    {
        DB::table('ncr_reports')
            ->where('current_status', 'SUBMITTED')
            ->update(['current_status' => 'INITIAL_REVIEW']);

        DB::table('ncr_statuses')->where('code', 'SUBMITTED')->delete();
    }

    public function down(): void
    {
        DB::table('ncr_statuses')->updateOrInsert(
            ['code' => 'SUBMITTED'],
            [
                'name_th' => 'ส่งตรวจแล้ว',
                'sort_order' => 2,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('ncr_reports')
            ->where('current_status', 'INITIAL_REVIEW')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ncr_status_history as submitted_history')
                    ->whereColumn('submitted_history.ncr_report_id', 'ncr_reports.id')
                    ->where('submitted_history.to_status', 'SUBMITTED')
                    ->where('submitted_history.action_code', 'SUBMIT')
                    ->whereNotExists(function ($laterHistory) {
                        $laterHistory->select(DB::raw(1))
                            ->from('ncr_status_history as later_history')
                            ->whereColumn('later_history.ncr_report_id', 'submitted_history.ncr_report_id')
                            ->whereColumn('later_history.id', '>', 'submitted_history.id');
                    });
            })
            ->update(['current_status' => 'SUBMITTED']);
    }
}
