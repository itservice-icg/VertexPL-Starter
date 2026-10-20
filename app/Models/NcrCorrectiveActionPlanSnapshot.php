<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrCorrectiveActionPlanSnapshot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'plan_payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function cycle() { return $this->belongsTo(NcrCorrectiveActionCycle::class, 'corrective_action_cycle_id'); }
}
