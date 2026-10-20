<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrActionProgressHistory extends Model
{
    public $timestamps = false;

    protected $table = 'ncr_action_progress_history';

    protected $guarded = ['id'];

    protected $casts = ['changed_at' => 'datetime'];

    public function action() { return $this->belongsTo(NcrCorrectiveAction::class, 'corrective_action_id'); }
}
