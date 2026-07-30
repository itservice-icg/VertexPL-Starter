<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(
        string $module,
        string $action,
        string $description,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = []
    ): ?AuditLog {
        if (! config('audit.enabled', true)) {
            return null;
        }

        $request = request();

        return AuditLog::create([
            'actor_id' => optional(Auth::user())->getAuthIdentifier(),
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }
}
