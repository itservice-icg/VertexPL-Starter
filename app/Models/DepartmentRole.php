<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentRole extends Model
{
    protected $fillable = [
        'code',
        'name_th',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_role', 'code');
    }
}
