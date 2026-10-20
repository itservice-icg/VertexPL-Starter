<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrActionAttachment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['uploaded_at' => 'datetime', 'is_active' => 'boolean'];

    public function action() { return $this->belongsTo(NcrCorrectiveAction::class, 'corrective_action_id'); }
}
