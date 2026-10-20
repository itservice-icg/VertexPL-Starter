<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrInitialReview extends Model { public $timestamps=false; protected $guarded=['id']; protected $casts=['is_complete'=>'boolean','reviewed_at'=>'datetime']; public function report(){return $this->belongsTo(NcrReport::class,'ncr_report_id');} public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');} }
