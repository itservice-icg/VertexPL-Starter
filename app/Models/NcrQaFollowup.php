<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrQaFollowup extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['followup_date' => 'date', 'next_followup_date' => 'date', 'followed_up_at' => 'datetime'];
    public function report() { return $this->belongsTo(NcrReport::class, 'ncr_report_id'); }
    public function follower() { return $this->belongsTo(User::class, 'followed_up_by'); }
}
