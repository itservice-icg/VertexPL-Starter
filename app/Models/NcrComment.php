<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrComment extends Model { protected $guarded=['id']; protected $casts=['is_internal'=>'boolean']; public function report(){return $this->belongsTo(NcrReport::class,'ncr_report_id');} }
