<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNcrCoreTables extends Migration
{
    public function up(): void
    {
        Schema::create('ncr_reports', function (Blueprint $table) {
            $table->id(); $table->string('ncr_no', 50)->unique();
            $table->date('report_date'); $table->date('found_date'); $table->time('found_time')->nullable();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reporter_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('related_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('problem_type_id')->nullable()->constrained('problem_types')->restrictOnDelete();
            $table->foreignId('severity_level_id')->nullable()->constrained('severity_levels')->restrictOnDelete();
            $table->string('company_name')->nullable(); $table->string('product_code')->nullable(); $table->string('product_name')->nullable();
            $table->string('generic_name')->nullable(); $table->string('lot_no')->nullable(); $table->string('po_no')->nullable();
            $table->string('customer_name')->nullable(); $table->string('batch_no')->nullable(); $table->date('production_date')->nullable();
            $table->string('supplier_name')->nullable(); $table->decimal('defect_quantity', 18, 4); $table->decimal('total_quantity', 18, 4);
            $table->string('unit_name', 50)->nullable(); $table->string('found_location')->nullable(); $table->string('machine_or_process')->nullable();
            $table->string('reference_no', 100)->nullable(); $table->text('problem_description');
            $table->string('current_status', 50)->default('DRAFT')->index();
            $table->foreignId('current_owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('current_owner_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->date('due_date')->nullable(); $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version_no')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->index(['reporter_id','current_status']); $table->index(['related_department_id','current_status']);
        });
        Schema::create('ncr_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete(); $table->unsignedInteger('line_no');
            $table->string('product_code')->nullable(); $table->string('product_name')->nullable(); $table->string('lot_no')->nullable();
            $table->string('po_no')->nullable(); $table->string('batch_no')->nullable(); $table->string('customer_name')->nullable();
            $table->decimal('defect_quantity',18,4); $table->decimal('total_quantity',18,4); $table->string('unit_name',50)->nullable();
            $table->text('problem_description')->nullable(); $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->unique(['ncr_report_id','line_no']);
        });
        Schema::create('ncr_attachments', function (Blueprint $table) {
            $table->id(); $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete(); $table->string('attachment_stage',50);
            $table->string('original_filename'); $table->string('stored_filename'); $table->string('storage_path',1000);
            $table->string('mime_type',100)->nullable(); $table->unsignedBigInteger('file_size')->nullable(); $table->text('description')->nullable();
            $table->timestamp('uploaded_at'); $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('ncr_comments', function (Blueprint $table) {
            $table->id(); $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete(); $table->string('comment_type',50);
            $table->text('comment_text'); $table->boolean('is_internal')->default(false); $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
        });
        Schema::create('ncr_status_history', function (Blueprint $table) {
            $table->id(); $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete(); $table->string('from_status',50)->nullable();
            $table->string('to_status',50); $table->string('action_code',100); $table->text('action_note')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->timestamp('changed_at'); $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->index(['ncr_report_id','changed_at']);
        });
    }

    public function down(): void
    {
        foreach (['ncr_status_history','ncr_comments','ncr_attachments','ncr_items','ncr_reports'] as $table) Schema::dropIfExists($table);
    }
}
