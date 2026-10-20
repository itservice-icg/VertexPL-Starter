<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrStatusHistory extends Model { public $timestamps=false; protected $table='ncr_status_history'; protected $guarded=['id']; protected $casts=['changed_at'=>'datetime']; public function report(){return $this->belongsTo(NcrReport::class,'ncr_report_id');} public function changedBy(){return $this->belongsTo(User::class,'changed_by');} }
