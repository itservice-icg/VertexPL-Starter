<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNcrCorrectiveActionCycles extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ncr_corrective_action_cycles')) {
            Schema::create('ncr_corrective_action_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_report_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cycle_no');
            $table->string('origin', 50);
            $table->string('status', 50);
            $table->foreignId('opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('report_version_at_submit')->nullable();
            $table->timestamps();
            $table->unique(['ncr_report_id', 'cycle_no']);
            $table->index(['ncr_report_id', 'status']);
            });
        }

        if (! Schema::hasTable('ncr_corrective_action_plan_snapshots')) {
            Schema::create('ncr_corrective_action_plan_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrective_action_cycle_id')->constrained('ncr_corrective_action_cycles')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('plan_hash', 64);
            $table->json('plan_payload');
            $table->unsignedInteger('report_version_at_submit');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['corrective_action_cycle_id', 'revision_no']);
            });
        }

        if (! Schema::hasColumn('ncr_reports', 'current_corrective_action_cycle_id')) {
            Schema::table('ncr_reports', function (Blueprint $table) {
                $table->foreignId('current_corrective_action_cycle_id')->nullable()->constrained('ncr_corrective_action_cycles')->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('ncr_corrective_actions', 'corrective_action_cycle_id')) {
            Schema::table('ncr_corrective_actions', function (Blueprint $table) {
                $table->foreignId('corrective_action_cycle_id')->nullable()->constrained('ncr_corrective_action_cycles')->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('ncr_approvals', 'corrective_action_cycle_id')) {
            Schema::table('ncr_approvals', function (Blueprint $table) {
                $table->foreignId('corrective_action_cycle_id')->nullable()->constrained('ncr_corrective_action_cycles')->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('ncr_approvals', 'corrective_action_plan_snapshot_id')) {
            Schema::table('ncr_approvals', function (Blueprint $table) {
                $table->foreignId('corrective_action_plan_snapshot_id')->nullable()->constrained('ncr_corrective_action_plan_snapshots')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('ncr_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrective_action_plan_snapshot_id');
            $table->dropConstrainedForeignId('corrective_action_cycle_id');
        });
        Schema::table('ncr_corrective_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrective_action_cycle_id');
        });
        Schema::table('ncr_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_corrective_action_cycle_id');
        });
        Schema::dropIfExists('ncr_corrective_action_plan_snapshots');
        Schema::dropIfExists('ncr_corrective_action_cycles');
    }
}
