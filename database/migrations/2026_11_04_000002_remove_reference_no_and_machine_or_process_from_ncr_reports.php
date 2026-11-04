<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveReferenceNoAndMachineOrProcessFromNcrReports extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropColumn(['reference_no', 'machine_or_process']);
        });
    }

    public function down(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->string('reference_no', 100)->nullable();
            $table->string('machine_or_process')->nullable();
        });
    }
}
