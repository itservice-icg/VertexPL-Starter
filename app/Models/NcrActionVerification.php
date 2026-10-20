<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrActionVerification extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(NcrReport::class, 'ncr_report_id');
    }

    public function correctiveAction()
    {
        return $this->belongsTo(NcrCorrectiveAction::class, 'corrective_action_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
