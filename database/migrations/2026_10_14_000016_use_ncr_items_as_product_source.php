<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UseNcrItemsAsProductSource extends Migration
{
    private const REPORT_ITEM_FIELDS = [
        'product_code', 'product_name', 'lot_no', 'po_no', 'batch_no', 'customer_name',
        'defect_quantity', 'defect_unit_name', 'total_quantity', 'total_unit_name', 'unit_name',
    ];

    public function up(): void
    {
        DB::table('ncr_reports')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ncr_items')
                    ->whereColumn('ncr_items.ncr_report_id', 'ncr_reports.id');
            })
            ->chunkById(100, function ($reports): void {
                foreach ($reports as $report) {
                    $item = ['ncr_report_id' => $report->id, 'line_no' => 1];
                    foreach (self::REPORT_ITEM_FIELDS as $field) {
                        $item[$field] = $report->{$field} ?? null;
                    }

                    $item['created_by'] = $report->created_by ?: $report->reporter_id;
                    $item['created_at'] = $report->created_at;
                    $item['updated_at'] = $report->updated_at;
                    DB::table('ncr_items')->insert($item);
                }
            }, 'ncr_reports.id', 'id');

        Schema::table('ncr_reports', function (Blueprint $table): void {
            $table->dropColumn(self::REPORT_ITEM_FIELDS);
        });
    }

    public function down(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table): void {
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('lot_no')->nullable();
            $table->string('po_no')->nullable();
            $table->string('batch_no')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('defect_quantity', 18, 4)->nullable();
            $table->string('defect_unit_name', 50)->nullable();
            $table->decimal('total_quantity', 18, 4)->nullable();
            $table->string('total_unit_name', 50)->nullable();
            $table->string('unit_name', 50)->nullable();
        });

        DB::table('ncr_reports')->orderBy('id')->chunkById(100, function ($reports): void {
            foreach ($reports as $report) {
                $item = DB::table('ncr_items')->where('ncr_report_id', $report->id)->orderBy('line_no')->first();
                if (! $item) {
                    continue;
                }

                $snapshot = [];
                foreach (self::REPORT_ITEM_FIELDS as $field) {
                    $snapshot[$field] = $item->{$field} ?? null;
                }
                DB::table('ncr_reports')->where('id', $report->id)->update($snapshot);
            }
        }, 'id', 'id');
    }
}
