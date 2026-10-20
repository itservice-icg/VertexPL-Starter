<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateNcrReviewAndApprovalTables extends Migration {
 public function up():void{
  Schema::create('ncr_initial_reviews',function(Blueprint $t){$t->id();$t->foreignId('ncr_report_id')->constrained()->restrictOnDelete();$t->unsignedInteger('review_round')->default(1);$t->boolean('is_complete');$t->text('completeness_note')->nullable();$t->string('review_result',50);$t->timestamp('reviewed_at');$t->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();$t->timestamp('created_at')->useCurrent();$t->unique(['ncr_report_id','review_round']);});
  Schema::create('ncr_dispositions',function(Blueprint $t){$t->id();$t->foreignId('ncr_report_id')->constrained()->restrictOnDelete();$t->foreignId('disposition_type_id')->constrained()->restrictOnDelete();$t->unsignedInteger('revision_no')->default(1);$t->text('disposition_detail');$t->foreignId('responsible_department_id')->constrained('departments')->restrictOnDelete();$t->date('proposed_due_date');$t->string('status',50)->default('PENDING');$t->timestamp('proposed_at');$t->foreignId('proposed_by')->constrained('users')->restrictOnDelete();$t->timestamp('approved_at')->nullable();$t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamp('rejected_at')->nullable();$t->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();$t->text('rejection_reason')->nullable();$t->timestamps();$t->unique(['ncr_report_id','revision_no']);});
  Schema::create('ncr_approvals',function(Blueprint $t){$t->id();$t->foreignId('ncr_report_id')->constrained()->restrictOnDelete();$t->foreignId('disposition_id')->constrained('ncr_dispositions')->restrictOnDelete();$t->string('approval_stage',50);$t->unsignedInteger('approval_level')->default(1);$t->foreignId('approver_id')->constrained('users')->restrictOnDelete();$t->string('decision',50);$t->text('decision_note')->nullable();$t->timestamp('decided_at');$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('ncr_approvals');Schema::dropIfExists('ncr_dispositions');Schema::dropIfExists('ncr_initial_reviews');}
}
