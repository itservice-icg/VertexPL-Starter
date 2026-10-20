<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateNcrCorrectiveActionTables extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ncr_corrective_actions')) {
            Schema::create('ncr_corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('action_no');
            $table->string('action_type', 50);
            $table->foreignId('root_cause_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('root_cause_detail')->nullable();
            $table->text('immediate_correction')->nullable();
            $table->text('corrective_action_detail');
            $table->text('preventive_action_detail')->nullable();
            $table->foreignId('responsible_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('action_status', 50)->default('OPEN');
            $table->boolean('is_required')->default(true);
            $table->text('completion_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['ncr_report_id', 'action_no']);
            $table->index(['ncr_report_id', 'action_status']);
            $table->index(['due_date', 'action_status']);
            });
        }

        if (! Schema::hasTable('ncr_action_assignees')) {
            Schema::create('ncr_action_assignees', function (Blueprint $table) {
            $table->foreignId('corrective_action_id')->constrained('ncr_corrective_actions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_role', 50)->nullable();
            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->primary(['corrective_action_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('ncr_action_attachments')) {
            Schema::create('ncr_action_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrective_action_id')->constrained('ncr_corrective_actions')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('storage_path', 1000);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('uploaded_at');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('ncr_action_progress_history')) {
            Schema::create('ncr_action_progress_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrective_action_id')->constrained('ncr_corrective_actions')->cascadeOnDelete();
            $table->unsignedTinyInteger('from_progress_percent')->nullable();
            $table->unsignedTinyInteger('to_progress_percent');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('progress_note')->nullable();
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->index(['corrective_action_id', 'changed_at'], 'ncr_action_progress_changed_at_idx');
            });
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            $indexExists = count(DB::select(
                'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
                ['ncr_action_progress_history', 'ncr_action_progress_changed_at_idx']
            )) > 0;

            if (! $indexExists) {
                Schema::table('ncr_action_progress_history', function (Blueprint $table) {
                    $table->index(['corrective_action_id', 'changed_at'], 'ncr_action_progress_changed_at_idx');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ncr_action_progress_history');
        Schema::dropIfExists('ncr_action_attachments');
        Schema::dropIfExists('ncr_action_assignees');
        Schema::dropIfExists('ncr_corrective_actions');
    }
}
