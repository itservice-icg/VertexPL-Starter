<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">NCR Management</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">รายการ NCR</h1>
                <p class="mt-1 text-sm text-slate-500">Non-Conformance Reports · ค้นหาและติดตามรายงานผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด</p>
            </div>
            @can('ncr.create')<a href="{{ route('ncr.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><i class="ph ph-plus h-4 w-4 text-base leading-none" aria-hidden="true"></i> สร้าง NCR</a>@endcan
        </div>
    </x-slot>

    <div class="ncr-page ncr-register w-full space-y-4 p-4 sm:p-6">

        <form method="GET" class="ncr-register-filters" aria-label="ตัวกรอง NCR">
            <div class="sm:col-span-2 xl:col-span-1"><label for="ncr-search" class="ncr-data-label">ค้นหา</label><input type="search" id="ncr-search" name="search" value="{{ $filters['search']??'' }}" placeholder="เลข NCR / สินค้า / ปัญหา" class="mt-1 block min-h-[42px] w-full rounded-lg border border-slate-300 px-3 py-2"></div>
            <div><label for="ncr-status" class="ncr-data-label">สถานะ</label><select id="ncr-status" name="status" class="mt-1 block min-h-[42px] w-full rounded-lg border-slate-300"><option value="">ทุกสถานะ</option>@foreach($statuses as $status)<option value="{{ $status }}" {{ ($filters['status']??'')===$status?'selected':'' }}>{{ config('ncr.status_labels.'.$status, $status) }}</option>@endforeach</select></div>
            <div><label for="ncr-department" class="ncr-data-label">หน่วยงานที่พบปัญหา</label><select id="ncr-department" name="department_id" class="mt-1 block min-h-[42px] w-full rounded-lg border-slate-300"><option value="">ทุกหน่วยงาน</option>@foreach($departments as $d)<option value="{{ $d->id }}" {{ (string)($filters['department_id']??'')===(string)$d->id?'selected':'' }}>{{ $d->name_th }}</option>@endforeach</select></div>
            <div><label for="ncr-date-from" class="ncr-data-label">ตั้งแต่วันที่</label><input id="ncr-date-from" type="date" name="date_from" value="{{ $filters['date_from']??'' }}" class="mt-1 block min-h-[42px] w-full rounded-lg border-slate-300"></div>
            <button class="ncr-action-link self-end">ค้นหา</button>
        </form>

        <section class="ncr-table-shell" aria-labelledby="ncr-list-heading">
            <div class="ncr-table-shell__header"><h2 id="ncr-list-heading" class="text-sm font-semibold text-slate-700">รายงานทั้งหมด</h2><span class="ncr-status-chip">{{ $reports->total() }} รายการ</span></div>
            <div class="ncr-table-shell__body"><table class="ncr-mobile-cards divide-y text-sm" style="width:100%; table-layout:fixed">
                <colgroup><col style="width:9%"><col style="width:12%"><col style="width:9%"><col style="width:12%"><col style="width:26%"><col style="width:12%"><col style="width:10%"><col style="width:10%"></colgroup>
                <thead><tr><th class="px-2 py-3 text-left">เลข NCR</th><th class="px-4 py-3 text-left">ข้อเสนอแนะ</th><th class="px-2 py-3 text-left">วันที่</th><th class="px-4 py-3 text-left">ผู้สร้าง</th><th class="px-4 py-3 text-left">สินค้า / ปัญหา</th><th class="px-4 py-3 text-left">หน่วยงานที่พบปัญหา</th><th class="px-4 py-3 text-left">สถานะ</th><th class="px-1 py-3 text-left">เอกสาร</th></tr></thead>
                <tbody class="divide-y">
                @forelse($reports as $r)
                    @php($firstItem = $r->items->first())
                    @php($latestDisposition = $r->latestDisposition)
                    <tr>
                        <td class="px-2 py-4" data-label="เลข NCR"><a href="{{ route('ncr.show',$r) }}" class="font-extrabold text-indigo-600 hover:text-indigo-700">{{ $r->ncr_no }}</a></td>
                        <td class="px-4 py-4" data-label="ข้อเสนอแนะ">
                            @if($latestDisposition)
                                @if($latestDisposition->type)
                                    <div class="font-semibold">{{ $latestDisposition->type->code }} / {{ $latestDisposition->type->name_th }}</div>
                                @else
                                    <div class="font-semibold">ไม่พบประเภทข้อเสนอ</div>
                                @endif
                                <div class="max-w-xs truncate text-xs text-slate-500" title="{{ $latestDisposition->disposition_detail }}">{{ $latestDisposition->disposition_detail }}</div>
                            @else
                                <span class="text-slate-500">ยังไม่มีข้อเสนอแนะ</span>
                            @endif
                        </td>
                        <td class="px-0 py-4" data-label="วันที่">{{ $r->report_date->format('d') }}/<wbr>{{ $r->report_date->format('m') }}/<wbr>{{ $r->report_date->format('Y') }}</td>
                        <td class="px-4 py-4" data-label="ผู้สร้าง"><div class="font-medium text-slate-800">{{ $r->reporter->name }}</div></td>
                        <td class="px-4 py-4" data-label="สินค้า / ปัญหา"><div>{{ $firstItem?->product_name ?: '-' }}{{ $r->items->count() > 1 ? ' (+'.($r->items->count() - 1).')' : '' }}</div><div class="max-w-md truncate text-slate-500">{{ $r->problem_description }}</div></td>
                        <td class="px-4 py-4" data-label="หน่วยงานที่พบปัญหา">{{ optional($r->relatedDepartment)->name_th?:'-' }}</td>
                        <td class="px-4 py-4" data-label="สถานะ"><span class="ncr-status-chip" title="{{ $r->current_status }}">{{ config('ncr.status_labels.'.$r->current_status, $r->current_status) }}</span></td>
                        <td class="px-1 py-4" data-label="เอกสาร">@if($r->current_status === 'CLOSED')
<div class="ncr-pdf-actions" style="flex-wrap:wrap">
    <a href="{{ route('ncr.pdf', ['ncr' => $r, 'preview' => 1]) }}" target="_blank" rel="noopener" title="View PDF" aria-label="View PDF {{ $r->ncr_no }} (เปิดในแท็บใหม่)">
        <i class="ph ph-eye h-4 w-4 text-base leading-none" aria-hidden="true"></i>
    </a>
    <a href="{{ route('ncr.pdf', $r) }}" title="โหลด PDF" aria-label="โหลด PDF {{ $r->ncr_no }}">
        <i class="ph ph-download-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i>
    </a>
</div>
@else<span class="text-xs font-medium text-slate-500">ดาวน์โหลดได้เมื่อปิด NCR แล้ว</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-10 text-center text-slate-500">ไม่พบ NCR</td></tr>
                @endforelse
                </tbody>
            </table></div><div class="border-t border-slate-100 p-4"><x-page-navigation :paginator="$reports" /></div>
        </section>
    </div>
</x-app-layout>
