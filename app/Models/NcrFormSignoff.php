<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrFormSignoff extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(NcrReport::class, 'ncr_report_id');
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
