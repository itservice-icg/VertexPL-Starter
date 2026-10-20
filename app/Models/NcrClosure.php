<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrClosure extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['closed_at' => 'datetime', 'reopened_at' => 'datetime'];
    public function report() { return $this->belongsTo(NcrReport::class, 'ncr_report_id'); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
}
