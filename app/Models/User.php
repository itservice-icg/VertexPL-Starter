<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'department_id',
        'department_role',
        'password',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function departmentRole()
    {
        return $this->belongsTo(DepartmentRole::class, 'department_role', 'code');
    }

    public function canApproveCorrectiveActionIn(?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false;
        }

        $matchesResponsibleDepartment = (int) $this->department_id === $departmentId;
        $isDepartmentApprover = $matchesResponsibleDepartment
            && in_array($this->department_role, ['DEPARTMENT_HEAD', 'ADMIN'], true);
        $isSystemAdministrator = $this->hasAnyRole(['Administrator', 'Super Admin']);

        return $isDepartmentApprover || $isSystemAdministrator;
    }
}
