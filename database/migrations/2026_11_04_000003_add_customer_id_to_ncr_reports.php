<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerIdToNcrReports extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('company_name')->constrained('customers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
}
