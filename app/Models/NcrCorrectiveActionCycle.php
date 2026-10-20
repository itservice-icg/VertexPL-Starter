<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrCorrectiveActionCycle extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opened_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function report() { return $this->belongsTo(NcrReport::class, 'ncr_report_id'); }
    public function snapshots() { return $this->hasMany(NcrCorrectiveActionPlanSnapshot::class, 'corrective_action_cycle_id'); }
}
