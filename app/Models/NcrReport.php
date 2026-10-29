<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NcrReport extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['report_date'=>'date','found_date'=>'date','production_date'=>'date','due_date'=>'date','initial_proposed_due_date'=>'date','closed_at'=>'datetime'];
    public function reporter() { return $this->belongsTo(User::class, 'reporter_id'); }
    public function currentOwner() { return $this->belongsTo(User::class, 'current_owner_user_id'); }
    public function currentOwnerDepartment() { return $this->belongsTo(Department::class, 'current_owner_department_id'); }
    public function reporterDepartment() { return $this->belongsTo(Department::class, 'reporter_department_id'); }
    public function relatedDepartment() { return $this->belongsTo(Department::class, 'related_department_id'); }
    public function problemType() { return $this->belongsTo(ProblemType::class); }
    public function severityLevel() { return $this->belongsTo(SeverityLevel::class); }
    public function supplierReference() { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function formScope() { return $this->belongsTo(NcrFormScope::class, 'legacy_form_scope_code', 'code')->withTrashed(); }
    public function items() { return $this->hasMany(NcrItem::class); }
    public function attachments() { return $this->hasMany(NcrAttachment::class); }
    public function comments() { return $this->hasMany(NcrComment::class); }
    public function statusHistory() { return $this->hasMany(NcrStatusHistory::class)->orderByDesc('changed_at'); }
    public function initialReviews() { return $this->hasMany(NcrInitialReview::class); }
    public function dispositions() { return $this->hasMany(NcrDisposition::class)->orderByDesc('revision_no'); }
    public function latestDisposition() { return $this->hasOne(NcrDisposition::class)->latestOfMany('revision_no'); }
    public function approvals() { return $this->hasMany(NcrApproval::class); }
    public function correctiveActions() { return $this->hasMany(NcrCorrectiveAction::class)->orderBy('action_no'); }
    public function correctiveActionCycles() { return $this->hasMany(NcrCorrectiveActionCycle::class); }
    public function currentCorrectiveActionCycle() { return $this->belongsTo(NcrCorrectiveActionCycle::class, 'current_corrective_action_cycle_id'); }
    public function actionVerifications() { return $this->hasMany(NcrActionVerification::class)->orderByDesc('verification_round'); }
    public function qaFollowups() { return $this->hasMany(NcrQaFollowup::class)->orderByDesc('followup_round'); }
    public function closure() { return $this->hasOne(NcrClosure::class); }
    public function formSignoffs() { return $this->hasMany(NcrFormSignoff::class)->orderBy('signed_at'); }
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->lt(today())
            && $this->current_status !== 'CLOSED';
    }
}
