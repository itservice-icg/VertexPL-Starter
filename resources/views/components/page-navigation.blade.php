@props(['paginator'])

<nav aria-label="เปลี่ยนหน้ารายการ" class="flex flex-wrap items-center justify-between gap-2 text-sm">
    <p class="text-xs text-slate-500">แสดง {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} จาก {{ $paginator->total() }} รายการ · หน้า {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</p>
    <div class="flex items-center gap-2">
        @if($paginator->previousPageUrl())
            <a rel="prev" href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-indigo-600 hover:bg-indigo-50 focus:ring-2 focus:ring-indigo-500">← ก่อนหน้า</a>
        @else
            <span aria-disabled="true" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500">← ก่อนหน้า</span>
        @endif
        @if($paginator->nextPageUrl())
            <a rel="next" href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-indigo-600 hover:bg-indigo-50 focus:ring-2 focus:ring-indigo-500">ถัดไป →</a>
        @else
            <span aria-disabled="true" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500">ถัดไป →</span>
        @endif
    </div>
</nav>
