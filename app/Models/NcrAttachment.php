<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrAttachment extends Model { protected $guarded=['id']; protected $casts=['uploaded_at'=>'datetime','is_active'=>'boolean']; public function report(){return $this->belongsTo(NcrReport::class,'ncr_report_id');} }
