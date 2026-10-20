<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">รายการ NCR และงานที่คุณมีสิทธิ์เข้าถึง</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('ncr.view')
                    <a href="{{ route('ncr.tasks') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">My Tasks</a>
                @endcan
                @can('ncr.create')
                    <a href="{{ route('ncr.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">+ สร้าง NCR</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="ncr-page ncr-dashboard w-full space-y-2 p-4">
        @can('ncr.view')
            @php
                $waitingApproval = ($counts['PENDING_MANAGER_APPROVAL'] ?? 0) + ($counts['PENDING_DESTRUCTION_APPROVAL'] ?? 0);
                $openCount = (int) $open;
                $overdueCount = (int) $overdue;
            @endphp
            <section class="ncr-metric-grid" aria-label="ภาพรวมรายการ">
                <article class="ncr-metric-card ncr-metric--indigo"><p class="ncr-data-label">รายการที่เปิดอยู่</p><p class="ncr-metric-card__value">{{ $openCount }}</p></article>
                <article class="ncr-metric-card ncr-metric--red"><p class="ncr-data-label">เกินกำหนด</p><p class="ncr-metric-card__value {{ $overdueCount > 0 ? 'ncr-danger-text' : '' }}">{{ $overdueCount }}</p></article>
                <article class="ncr-metric-card ncr-metric--amber"><p class="ncr-data-label">รออนุมัติ Disposition / SCRAP</p><p class="ncr-metric-card__value">{{ $waitingApproval }}</p></article>
                <article class="ncr-metric-card ncr-metric--emerald"><p class="ncr-data-label">ปิดแล้ว</p><p class="ncr-metric-card__value">{{ $counts['CLOSED'] ?? 0 }}</p></article>
            </section>

            <section class="ncr-stage-card" aria-labelledby="dashboard-recent-title">
                <div class="ncr-stage-card__header"><div><h2 id="dashboard-recent-title" class="ncr-section-title">รายการอัปเดตล่าสุด</h2><p class="mt-1 text-xs text-slate-500">{{ auth()->user()->can('ncr.view.all') ? 'รายการจากทุกหน่วยงาน สูงสุด 12 รายการ' : (auth()->user()->department_id ? 'รายการที่เกี่ยวข้องกับหน่วยงานของคุณ สูงสุด 12 รายการ' : 'รายการที่คุณมีสิทธิ์เข้าถึง สูงสุด 12 รายการ') }}</p></div><a href="{{ route('ncr.index') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">ดู NCR ทั้งหมด →</a></div>
                <div class="divide-y divide-slate-200">
                        @forelse($recent as $report)
                        <a href="{{ route('ncr.show', $report) }}" class="grid min-h-[56px] grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-2 text-sm hover:bg-indigo-50/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 lg:grid-cols-[minmax(11rem,auto)_minmax(0,1fr)_auto_auto]">
                            <span class="min-w-0 lg:row-start-1">
                                <span class="font-bold text-indigo-600">{{ $report->ncr_no }}</span>
                                <span class="mt-0.5 block max-w-48 truncate text-xs text-slate-500" title="ผู้รายงาน: {{ $report->reporter?->name ?: '-' }}">ผู้รายงาน: {{ $report->reporter?->name ?: '-' }}</span>
                                @php($responsibleDepartment = $report->dispositions->first()?->responsibleDepartment ?: $report->currentOwnerDepartment ?: $report->reporterDepartment)
                                <span class="mt-0.5 block max-w-48 truncate text-xs text-slate-500" title="หน่วยงานรับผิดชอบ: {{ $responsibleDepartment?->name_th ?: '-' }}">หน่วยงานรับผิดชอบ: {{ $responsibleDepartment?->name_th ?: '-' }}</span>
                            </span>
                            @php($firstItem = $report->items->first())
                            <span class="col-span-2 row-start-2 truncate text-slate-700 lg:col-span-1 lg:col-start-2 lg:row-start-1" title="{{ $firstItem?->product_name }}">{{ $firstItem?->product_name ?: 'ไม่ระบุชื่อสินค้า' }}{{ $report->items->count() > 1 ? ' (+'.($report->items->count() - 1).')' : '' }}</span>
                            <span class="ncr-status-chip col-start-2 row-start-1 max-w-48 truncate lg:col-start-3" title="{{ $report->current_status }}">{{ config('ncr.status_labels.'.$report->current_status, $report->current_status) }}</span>
                            <time class="col-span-2 row-start-3 justify-self-end text-xs text-slate-500 lg:col-span-1 lg:col-start-4 lg:row-start-1" datetime="{{ $report->updated_at?->toIso8601String() }}" aria-label="อัปเดตล่าสุด">{{ optional($report->updated_at)->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</time>
                        </a>
                        @empty
                        <p class="p-4 text-sm text-slate-500">ยังไม่มีรายการ NCR</p>
                        @endforelse
                </div>
            </section>

        @endcan
    </div>
</x-app-layout>
