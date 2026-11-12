<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueNcrReportIdToNcrItems extends Migration
{
    private const INDEX_NAME = 'ncr_items_ncr_report_id_unique';

    public function up(): void
    {
        $duplicates = DB::table('ncr_items')
            ->select('ncr_report_id', DB::raw('COUNT(*) as item_count'))
            ->groupBy('ncr_report_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('ncr_report_id')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $reportIds = $duplicates->pluck('ncr_report_id')->implode(', ');
            throw new RuntimeException(
                'Cannot add '.self::INDEX_NAME.'; duplicate ncr_report_id values exist for reports: '.$reportIds
            );
        }

        Schema::table('ncr_items', function (Blueprint $table): void {
            $table->unique('ncr_report_id', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('ncr_items', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
}
