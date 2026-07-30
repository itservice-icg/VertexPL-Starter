<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Set AUDIT_LOG_ENABLED=false to stop writing all application audit logs.
    | Existing audit records are preserved and remain available for viewing.
    |
    */
    'enabled' => env('AUDIT_LOG_ENABLED', true),
];
