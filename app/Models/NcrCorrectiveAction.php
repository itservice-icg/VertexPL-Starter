<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property-read \Illuminate\Database\Eloquent\Collection<NcrActionAttachment> $attachments */
class NcrCorrectiveAction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_date' => 'date',
        'responsible_acknowledged_at' => 'datetime',
        'is_required' => 'boolean',
    ];

    public function report() { return $this->belongsTo(NcrReport::class, 'ncr_report_id'); }
    public function correctiveActionCycle() { return $this->belongsTo(NcrCorrectiveActionCycle::class, 'corrective_action_cycle_id'); }
    public function supersedes() { return $this->belongsTo(self::class, 'supersedes_corrective_action_id'); }
    public function rootCauseType() { return $this->belongsTo(RootCauseType::class, 'root_cause_type_id'); }
    public function responsibleDepartment() { return $this->belongsTo(Department::class, 'responsible_department_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function attachments() { return $this->hasMany(NcrActionAttachment::class, 'corrective_action_id'); }
    public function progressHistory() { return $this->hasMany(NcrActionProgressHistory::class, 'corrective_action_id')->orderByDesc('changed_at'); }
    public function verifications() { return $this->hasMany(NcrActionVerification::class, 'corrective_action_id')->orderByDesc('verification_round'); }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->lt(today())
            && ! in_array($this->action_status, ['COMPLETED', 'VERIFIED', 'CANCELLED'], true);
    }
}
