<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNcrActionVerificationsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ncr_action_verifications')) {
            return;
        }

        Schema::create('ncr_action_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete();
            $table->foreignId('corrective_action_id')->nullable()->constrained('ncr_corrective_actions')->restrictOnDelete();
            $table->unsignedInteger('verification_round')->default(1);
            $table->string('result', 50);
            $table->text('verification_detail');
            $table->text('return_reason')->nullable();
            $table->timestamp('verified_at');
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();

            $table->unique(['ncr_report_id', 'verification_round'], 'ncr_verification_report_round_uq');
            $table->index(['ncr_report_id', 'result'], 'ncr_verification_report_result_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncr_action_verifications');
    }
}
