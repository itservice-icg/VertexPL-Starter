<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitialDispositionFieldsToNcrReports extends Migration
{
    public function up(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->foreignId('initial_disposition_type_id')->nullable()->after('problem_description')->constrained('disposition_types')->restrictOnDelete();
            $table->text('initial_disposition_detail')->nullable()->after('initial_disposition_type_id');
            $table->foreignId('initial_responsible_department_id')->nullable()->after('initial_disposition_detail')->constrained('departments')->restrictOnDelete();
            $table->date('initial_proposed_due_date')->nullable()->after('initial_responsible_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('initial_disposition_type_id');
            $table->dropConstrainedForeignId('initial_responsible_department_id');
            $table->dropColumn(['initial_disposition_detail', 'initial_proposed_due_date']);
        });
    }
}
