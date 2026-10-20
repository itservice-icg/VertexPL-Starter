<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNcrQaFollowupsAndClosuresTables extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ncr_qa_followups')) {
            Schema::create('ncr_qa_followups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('followup_round')->default(1);
                $table->date('followup_date');
                $table->string('effectiveness_result', 50);
                $table->boolean('recurrence_found')->nullable();
                $table->text('followup_detail');
                $table->text('return_reason')->nullable();
                $table->date('next_followup_date')->nullable();
                $table->timestamp('followed_up_at');
                $table->foreignId('followed_up_by')->constrained('users')->restrictOnDelete();
                $table->unique(['ncr_report_id', 'followup_round'], 'ncr_qa_report_round_uq');
                $table->index(['ncr_report_id', 'effectiveness_result'], 'ncr_qa_report_result_idx');
            });
        }

        if (! Schema::hasTable('ncr_closures')) {
            Schema::create('ncr_closures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ncr_report_id')->unique('ncr_closure_report_uq')->constrained()->restrictOnDelete();
                $table->string('closure_result', 50);
                $table->text('closure_note')->nullable();
                $table->timestamp('closed_at');
                $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
                $table->text('reopen_reason')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ncr_closures');
        Schema::dropIfExists('ncr_qa_followups');
    }
}
