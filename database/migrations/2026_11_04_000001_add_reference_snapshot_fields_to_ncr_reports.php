<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferenceSnapshotFieldsToNcrReports extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->string('reference_product_code')->nullable();
            $table->string('reference_product_name')->nullable();
            $table->string('reference_customer_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropColumn([
                'reference_product_code',
                'reference_product_name',
                'reference_customer_name',
            ]);
        });
    }
}
