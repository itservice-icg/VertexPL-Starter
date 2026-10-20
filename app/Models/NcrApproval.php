<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrApproval extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['decided_at' => 'datetime'];
    public function disposition() { return $this->belongsTo(NcrDisposition::class); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
}
