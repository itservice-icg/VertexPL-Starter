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
                <pre class="whitespace-pre-wrap break-words font-mono text-[11px] text-red-800">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </dd>
        </div>
    @endif
    @if ($log->new_values)
        <div>
            <dt class="font-semibold uppercase tracking-wider text-emerald-500">After</dt>
            <dd class="mt-1 overflow-x-auto rounded-lg bg-emerald-50 p-2">
                <pre class="whitespace-pre-wrap break-words font-mono text-[11px] text-emerald-800">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </dd>
        </div>
    @endif
</dl>
