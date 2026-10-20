@php
    $formatAuditDateValues = function (array $values) use (&$formatAuditDateValues): array {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $formatAuditDateValues($value);
                continue;
            }

            if (in_array($key, ['due_date', 'proposed_due_date', 'initial_proposed_due_date', 'next_followup_date'], true)
                && ($value === null || $value === '')) {
                $values[$key] = 'ยังไม่กำหนด';
                continue;
            }

            if (! is_string($value)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ][0-9:.+-]+Z?)?$/', $value)
                || ! preg_match('/(?:_date|_at)$/', (string) $key)) {
                continue;
            }

            try {
                $date = \Carbon\Carbon::parse($value);
                $values[$key] = str_ends_with((string) $key, '_at')
                    ? $date->timezone(config('app.display_timezone'))->format('d/m/Y H:i:s')
                    : $date->format('d/m/Y');
            } catch (\Throwable $exception) {
                // Keep invalid legacy audit values unchanged.
            }
        }

        return $values;
    };

    $oldValuesForDisplay = $formatAuditDateValues($log->old_values ?? []);
    $newValuesForDisplay = $formatAuditDateValues($log->new_values ?? []);
@endphp

<dl class="space-y-3 text-xs">
    <div>
        <dt class="font-semibold uppercase tracking-wider text-slate-400">Subject</dt>
        <dd class="mt-1 text-slate-700">
            {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '-' }}
        </dd>
    </div>
    <div>
        <dt class="font-semibold uppercase tracking-wider text-slate-400">User Agent</dt>
        <dd class="mt-1 break-words text-slate-600">{{ $log->user_agent ?: '-' }}</dd>
    </div>
    @if ($log->old_values)
        <div>
            <dt class="font-semibold uppercase tracking-wider text-red-400">Before</dt>
            <dd class="mt-1 overflow-x-auto rounded-lg bg-red-50 p-2">
                <pre class="whitespace-pre-wrap break-words font-mono text-[11px] text-red-800">{{ json_encode($oldValuesForDisplay, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </dd>
        </div>
    @endif
    @if ($log->new_values)
        <div>
            <dt class="font-semibold uppercase tracking-wider text-emerald-500">After</dt>
            <dd class="mt-1 overflow-x-auto rounded-lg bg-emerald-50 p-2">
                <pre class="whitespace-pre-wrap break-words font-mono text-[11px] text-emerald-800">{{ json_encode($newValuesForDisplay, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </dd>
        </div>
    @endif
</dl>
