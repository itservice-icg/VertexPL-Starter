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
                    ? \App\Support\ThaiDateFormatter::formatDateTime($date, config('app.display_timezone'), true)
                    : \App\Support\ThaiDateFormatter::formatDate($date);
            } catch (\Throwable $exception) {
                // Keep invalid legacy audit values unchanged.
            }
        }

        return $values;
    };

    $oldValuesForDisplay = $formatAuditDateValues($log->old_values ?? []);
    $newValuesForDisplay = $formatAuditDateValues($log->new_values ?? []);

    $flattenAuditValues = function (array $values, string $parentKey = '') use (&$flattenAuditValues): array {
        $rows = [];

        foreach ($values as $key => $value) {
            $keyLabel = is_int($key) ? 'รายการที่ '.($key + 1) : str_replace('_', ' ', (string) $key);
            $field = $parentKey === '' ? $keyLabel : $parentKey.' / '.$keyLabel;

            if (is_array($value) && $value !== []) {
                $rows = array_merge($rows, $flattenAuditValues($value, $field));
                continue;
            }

            $rows[] = ['field' => $field, 'value' => $value];
        }

        return $rows;
    };

    $formatAuditValue = static function ($value): string {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return '[]';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
@endphp

<dl class="space-y-3 text-xs">
    <div>
        <dt class="font-semibold text-slate-500">รายการที่เกี่ยวข้อง</dt>
        <dd class="mt-1 text-slate-700">
            {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '-' }}
        </dd>
    </div>
    <div>
        <dt class="font-semibold text-slate-500">เบราว์เซอร์และอุปกรณ์</dt>
        <dd class="mt-1 break-words text-slate-600">{{ $log->user_agent ?: '-' }}</dd>
    </div>
    @if ($log->old_values)
        <div>
            <dt class="font-semibold text-red-700">ข้อมูลก่อน</dt>
            <dd class="mt-1 rounded-lg bg-red-50 p-2">
                <dl class="space-y-2">
                    @foreach ($flattenAuditValues($oldValuesForDisplay) as $change)
                        <div class="border-b border-red-200 py-2">
                            <dt class="font-semibold text-red-800">{{ $change['field'] }}</dt>
                            <dd class="mt-0.5 break-words font-mono text-[11px] text-red-800">{{ $formatAuditValue($change['value']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </dd>
        </div>
    @endif
    @if ($log->new_values)
        <div>
            <dt class="font-semibold text-emerald-700">ข้อมูลหลัง</dt>
            <dd class="mt-1 rounded-lg bg-emerald-50 p-2">
                <dl class="space-y-2">
                    @foreach ($flattenAuditValues($newValuesForDisplay) as $change)
                        <div class="border-b border-emerald-200 py-2">
                            <dt class="font-semibold text-emerald-800">{{ $change['field'] }}</dt>
                            <dd class="mt-0.5 break-words font-mono text-[11px] text-emerald-800">{{ $formatAuditValue($change['value']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </dd>
        </div>
    @endif
</dl>
