<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">รายการ NCR และงานที่คุณมีสิทธิ์เข้าถึง</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('ncr.view')
                    <a href="{{ route('ncr.tasks') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ auth()->user()->can('ncr.create') ? 'border border-slate-300 bg-slate-100 text-slate-700 hover:bg-slate-200' : 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-700' }} focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">งานของฉัน</a>
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
                $managerApprovalCount = (int) ($counts['PENDING_MANAGER_APPROVAL'] ?? 0);
                $destructionApprovalCount = (int) ($counts['PENDING_DESTRUCTION_APPROVAL'] ?? 0);
                $waitingApproval = $managerApprovalCount + $destructionApprovalCount;
                $openCount = (int) $open;
                $overdueCount = (int) $overdue;
            @endphp
            <section class="ncr-metric-grid" aria-label="ภาพรวมรายการ">
                <article class="ncr-metric-card ncr-metric--red">
                    <p class="ncr-data-label">เกินกำหนด</p>
                    <p class="ncr-metric-card__value {{ $overdueCount > 0 ? 'ncr-danger-text' : '' }}">{{ $overdueCount }}</p>
                    @if($overdueCount > 0)
                        <p class="ncr-metric-card__hint"><span class="ncr-overdue-badge">ต้องติดตาม</span></p>
                        <a href="{{ route('ncr.index', ['overdue' => 1, 'dashboard_scope' => 1]) }}" class="ncr-action-link ncr-action-link--secondary mt-2 w-full text-left sm:w-auto">ดูรายการเกินกำหนด</a>
                    @else
                        <p class="ncr-metric-card__hint">ไม่มีรายการเกินกำหนด</p>
                    @endif
                </article>
                <article class="ncr-metric-card ncr-metric--amber">
                    <p class="ncr-data-label">รออนุมัติ</p>
                    <p class="ncr-metric-card__value">{{ $waitingApproval }}</p>
                    <p class="ncr-metric-card__hint">แนวทางจัดการ (Disposition) หรือการทำลาย (SCRAP)</p>
                    @if($waitingApproval > 0)
                        <div class="mt-2 flex flex-col items-start gap-1">
                            @if($managerApprovalCount > 0)
                                <a href="{{ route('ncr.index', ['status' => 'PENDING_MANAGER_APPROVAL', 'dashboard_scope' => 1]) }}" class="ncr-action-link ncr-action-link--secondary w-full text-left sm:w-auto">ดู Disposition ที่รออนุมัติ · {{ $managerApprovalCount }}</a>
                            @endif
                            @if($destructionApprovalCount > 0)
                                <a href="{{ route('ncr.index', ['status' => 'PENDING_DESTRUCTION_APPROVAL', 'dashboard_scope' => 1]) }}" class="ncr-action-link ncr-action-link--secondary w-full text-left sm:w-auto">ดูคำขอทำลายที่รออนุมัติ · {{ $destructionApprovalCount }}</a>
                            @endif
                        </div>
                    @else
                        <p class="ncr-metric-card__hint">ไม่มีรายการรออนุมัติ</p>
                    @endif
                </article>
                <article class="ncr-metric-card ncr-metric--indigo"><p class="ncr-data-label">รายการที่เปิดอยู่</p><p class="ncr-metric-card__value">{{ $openCount }}</p></article>
                <article class="ncr-metric-card ncr-metric--emerald"><p class="ncr-data-label">ปิดแล้ว</p><p class="ncr-metric-card__value">{{ $counts['CLOSED'] ?? 0 }}</p></article>
            </section>

            <section class="ncr-stage-card ncr-dashboard-recent-card" aria-labelledby="dashboard-recent-title">
                <div class="ncr-stage-card__header"><div><h2 id="dashboard-recent-title" class="ncr-section-title">รายการอัปเดตล่าสุด</h2><p class="mt-1 text-xs text-slate-500">{{ auth()->user()->can('ncr.view.all') ? 'รายการจากทุกหน่วยงาน สูงสุด 10 รายการ' : (auth()->user()->department_id ? 'รายการที่เกี่ยวข้องกับหน่วยงานของคุณ สูงสุด 10 รายการ' : 'รายการที่คุณมีสิทธิ์เข้าถึง สูงสุด 10 รายการ') }}</p></div><a href="{{ route('ncr.index') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">ดู NCR ทั้งหมด →</a></div>
                @if($recent->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="ncr-dashboard-recent-table w-full min-w-[1120px] table-fixed border-collapse text-left text-sm">
                            <colgroup>
                                <col class="w-[15%]">
                                <col class="w-[27%]">
                                <col class="w-[15%]">
                                <col class="w-[17%]">
                                <col class="w-[13%]">
                                <col class="w-[13%]">
                            </colgroup>
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500">
                                    <th scope="col" class="px-4 py-3">เลขที่ NCR</th>
                                    <th scope="col" class="px-4 py-3">เรื่อง / รายละเอียด NCR</th>
                                    <th scope="col" class="px-4 py-3">ผู้รายงาน</th>
                                    <th scope="col" class="px-4 py-3">หน่วยงานรับผิดชอบ</th>
                                    <th scope="col" class="px-4 py-3">สถานะ</th>
                                    <th scope="col" class="px-4 py-3">วันที่ / เวลา</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($recent as $report)
                                    @php
                                        $firstItem = $report->items->first();
                                        $responsibleDepartment = $report->dispositions->first()?->responsibleDepartment ?: $report->currentOwnerDepartment ?: $report->reporterDepartment;
                                        $isOverdue = $report->current_status !== 'CLOSED' && $report->due_date && $report->due_date->lt(today());
                                    @endphp
                                    <tr class="ncr-dashboard-recent-row text-slate-700">
                                        <td class="px-4 py-3 align-top"><a href="{{ route('ncr.show', $report) }}" class="ncr-dashboard-recent-link font-bold text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="เปิด NCR {{ $report->ncr_no }}">{{ $report->ncr_no }}</a></td>
                                        <td class="px-4 py-3 align-top"><a href="{{ route('ncr.show', $report) }}" class="ncr-dashboard-recent-link block truncate text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500" title="{{ $firstItem?->product_name ?: $report->problem_description }}">{{ $firstItem?->product_name ?: \Illuminate\Support\Str::limit($report->problem_description, 120) }}{{ $report->items->count() > 1 ? ' (+'.($report->items->count() - 1).')' : '' }}</a></td>
                                        <td class="px-4 py-3 align-top"><span class="block truncate" title="{{ $report->reporter?->name ?: '-' }}">{{ $report->reporter?->name ?: '-' }}</span></td>
                                        <td class="px-4 py-3 align-top"><span class="block truncate" title="{{ $responsibleDepartment?->name_th ?: '-' }}">{{ $responsibleDepartment?->name_th ?: '-' }}</span></td>
                                        <td class="px-4 py-3 align-top"><span class="ncr-status-chip max-w-full truncate" title="{{ $report->current_status }}">{{ config('ncr.status_labels.'.$report->current_status, $report->current_status) }}</span>@if($report->current_status !== 'CLOSED' && $report->due_date)<span class="mt-1 block text-xs {{ $isOverdue ? 'ncr-danger-text' : 'text-slate-500' }}">{{ $isOverdue ? 'เกินกำหนด · ' : 'กำหนด ' }}{{ \App\Support\ThaiDateFormatter::formatDate($report->due_date, config('app.display_timezone')) }}</span>@endif</td>
                                        <td class="px-4 py-3 align-top"><time class="text-xs text-slate-500" datetime="{{ $report->updated_at?->toIso8601String() }}" aria-label="อัปเดตล่าสุด">{{ \App\Support\ThaiDateFormatter::formatDateTime($report->updated_at, config('app.display_timezone')) }}</time></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                        <div class="p-4 sm:p-5">
                            <p class="font-semibold text-slate-700">ไม่พบรายการ NCR ในขอบเขตที่คุณเข้าถึงได้</p>
                            <p class="mt-1 text-sm text-slate-500">รายการจะแสดงตามสิทธิ์ที่คุณได้รับ</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('ncr.index') }}" class="ncr-action-link ncr-action-link--secondary">ดูรายการ NCR</a>
                                @can('ncr.create')<a href="{{ route('ncr.create') }}" class="ncr-action-link">สร้าง NCR</a>@endcan
                            </div>
                        </div>
                @endif
            </section>

        @endcan
    </div>
</x-app-layout>
