<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrDisposition extends Model { protected $guarded=['id']; protected $casts=['proposed_due_date'=>'date','proposed_at'=>'datetime','approved_at'=>'datetime','rejected_at'=>'datetime']; public function type(){return $this->belongsTo(DispositionType::class,'disposition_type_id');} public function responsibleDepartment(){return $this->belongsTo(Department::class,'responsible_department_id');} public function approvals(){return $this->hasMany(NcrApproval::class,'disposition_id');} }
