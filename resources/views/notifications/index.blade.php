<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div><p class="text-sm font-medium text-indigo-600">VertexPL</p><h1 class="text-2xl font-bold text-slate-900">Notification Center</h1></div>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="min-h-[40px] rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:ring-2 focus:ring-indigo-500">อ่านทั้งหมด</button></form>
        </div>
    </x-slot>
    <div class="space-y-2">
        <section aria-label="รายการแจ้งเตือน" class="overflow-hidden rounded-xl border border-slate-200 bg-white divide-y divide-slate-200">
            @forelse($notifications as $notification)
                <article class="px-3 py-2 {{ $notification->is_read ? '' : 'bg-indigo-50' }}">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                            <h2 class="text-sm font-semibold">
                                @if($notification->report)
                                    <a class="text-indigo-600 hover:text-indigo-700 focus:ring-2 focus:ring-indigo-500" href="{{ route('ncr.show', $notification->report) }}">แจ้งเตือน {{ $notification->report->ncr_no }}</a>
                                @else
                                    {{ $notification->title }}
                                @endif
                            </h2>
                            @if($notification->report)
                                <span class="ncr-status-chip" title="สถานะปัจจุบัน: {{ $notification->report->current_status }}"><span class="sr-only">สถานะปัจจุบัน: </span>{{ config('ncr.status_labels.'.$notification->report->current_status, $notification->report->current_status) }}</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ optional($notification->created_at)->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</time>
                            @if(!$notification->is_read)
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button class="min-h-[32px] rounded px-2 font-semibold text-indigo-600 hover:bg-indigo-100 focus:ring-2 focus:ring-indigo-500" aria-label="ทำเครื่องหมายว่าอ่านแล้ว: {{ $notification->report?->ncr_no ?: $notification->title }}">อ่านแล้ว</button></form>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-500">เหตุการณ์เมื่อแจ้งเตือน: {{ $notification->message }}</p>
                </article>
            @empty
                <p class="p-4 text-center text-sm text-slate-500">ไม่มีการแจ้งเตือน</p>
            @endforelse
        </section>
        {{ $notifications->links() }}
    </div>
</x-app-layout>