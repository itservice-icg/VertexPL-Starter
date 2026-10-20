<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrNotification extends Model
{
    public $timestamps = false;
    protected $table = 'notifications';
    protected $guarded = ['id'];
    protected $casts = ['is_read' => 'boolean', 'read_at' => 'datetime', 'sent_at' => 'datetime', 'created_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function report() { return $this->belongsTo(NcrReport::class, 'ncr_report_id'); }
}
