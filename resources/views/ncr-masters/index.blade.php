<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-medium text-indigo-600">NCR Master Data</p><h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $definition['title'] }}</h1></div>
            <a href="{{ route('ncr-masters.create', $type) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><i class="ph ph-plus h-4 w-4 text-base leading-none" aria-hidden="true"></i> เพิ่มข้อมูล</a>
        </div>
    </x-slot>

    <div class="w-full space-y-3">
        <nav aria-label="ชนิดข้อมูลหลัก NCR" class="flex flex-wrap gap-2">
            @foreach(config('ncr.masters') as $masterType => $master)
                <a href="{{ route('ncr-masters.index', $masterType) }}" @if($type === $masterType) aria-current="page" @endif class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-medium transition {{ $type === $masterType ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                    <x-ncr-master-icon :type="$masterType" />
                    {{ $master['title'] }}
                </a>
            @endforeach
        </nav>
        <form method="GET" class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <label for="master-search" class="sr-only">ค้นหารหัสหรือชื่อ</label>
            <input id="master-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ค้นหารหัสหรือชื่อ" class="min-h-[42px] min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label for="master-status" class="sr-only">สถานะ</label>
            <select id="master-status" name="status" class="min-h-[42px] rounded-lg border-slate-300 py-2 text-sm"><option value="">ทุกสถานะ</option><option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>ใช้งาน</option><option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>ปิดใช้งาน</option></select>
            <button class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><i class="ph ph-magnifying-glass h-4 w-4 text-base leading-none" aria-hidden="true"></i>ค้นหา</button>
        </form>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">รายการ{{ $definition['title'] }}</caption>
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600"><tr><th scope="col" class="whitespace-nowrap px-4 py-3 text-left sm:px-5">รหัส</th><th scope="col" class="min-w-56 px-4 py-3 text-left sm:px-5">ชื่อ</th><th scope="col" class="whitespace-nowrap px-4 py-3 text-left sm:text-center sm:px-5">สถานะ</th><th scope="col" class="whitespace-nowrap px-4 py-3 text-right sm:px-5"><span class="sr-only">การจัดการ</span></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($records as $record)<tr class="transition-colors hover:bg-slate-50/80"><td class="whitespace-nowrap px-4 py-3.5 font-mono text-xs font-medium text-slate-600 sm:px-5">{{ $record->code }}</td><td class="px-4 py-3.5 font-medium text-slate-900 sm:px-5">{{ $record->name_th }}</td><td class="whitespace-nowrap px-4 py-3.5 sm:px-5 sm:text-center"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $record->is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }}"><span class="h-1.5 w-1.5 rounded-full {{ $record->is_active ? 'bg-emerald-600' : 'bg-slate-500' }}" aria-hidden="true"></span>{{ $record->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</span></td><td class="px-3 py-2.5 sm:px-5"><div class="flex justify-end gap-1">
                <a href="{{ route('ncr-masters.edit', [$type,$record]) }}" aria-label="แก้ไข {{ $record->name_th }}" title="แก้ไข" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-indigo-600">
                    <i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i>
                </a>
                <form method="POST" action="{{ route('ncr-masters.destroy', [$type,$record]) }}" data-confirm data-confirm-always><x-auth-validation-errors :errors="$errors" />@csrf @method('DELETE')
                    <button type="submit" aria-label="ลบ {{ $record->name_th }}" title="ลบ" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-red-600">
                        <i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i>
                    </button>
                </form>
            </div></td></tr>
            @empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-500"><i class="ph ph-magnifying-glass mb-2 block text-2xl" aria-hidden="true"></i><span class="font-medium">ไม่พบข้อมูล</span><span class="mt-1 block text-xs">ลองเปลี่ยนคำค้นหาหรือสถานะที่เลือก</span></td></tr>@endforelse
            </tbody></table></div><div class="border-t border-slate-200 px-4 py-3">{{ $records->links() }}</div>
        </div>
    </div>
</x-app-layout>
