@php
    $moduleLabels = [
        'auth' => 'การเข้าสู่ระบบ',
        'users' => 'จัดการผู้ใช้',
        'roles' => 'บทบาทและสิทธิ์',
        'profile' => 'โปรไฟล์',
    ];
    $moduleClasses = [
        'auth' => 'bg-sky-50 text-sky-700',
        'users' => 'bg-indigo-50 text-indigo-700',
        'roles' => 'bg-violet-50 text-violet-700',
        'profile' => 'bg-emerald-50 text-emerald-700',
    ];
    $hasActiveFilters = request()->filled('search')
        || request()->filled('module')
        || request()->filled('action')
        || request()->filled('date_from')
        || request()->filled('date_to');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">บันทึกการตรวจสอบ</h1>
            <p class="mt-1 text-sm text-slate-500">ประวัติการเข้าสู่ระบบและการเปลี่ยนแปลงข้อมูลสำคัญ</p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0 sm:flex-1">
                    <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">ค้นหา</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="ph ph-magnifying-glass h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                        </span>
                        <input id="search" name="search" type="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="ชื่อผู้ใช้ อีเมล การดำเนินการ หรือรายละเอียด" class="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    @error('search') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    @if ($logs->count() && $hasActiveFilters)
                        <a href="{{ route('audit-logs.index') }}" class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ล้างตัวกรอง</a>
                    @endif
                    <button type="submit" class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><i class="ph ph-magnifying-glass h-4 w-4 text-base leading-none" aria-hidden="true"></i>ค้นหา</button>
                </div>
            </div>

            <details class="mt-4 rounded-lg border border-slate-200 px-4 py-3" @if (request()->filled('module') || request()->filled('action') || request()->filled('date_from') || request()->filled('date_to') || $errors->hasAny(['module', 'action', 'date_from', 'date_to'])) open @endif>
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm font-semibold text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <span>ตัวกรองเพิ่มเติม</span>
                    <span class="text-xs font-normal text-slate-500">หมวด การดำเนินการ และช่วงวันที่</span>
                </summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="module" class="mb-1.5 block text-sm font-semibold text-slate-700">หมวด</label>
                        <select id="module" name="module" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">ทุกหมวด</option>
                            @foreach ($moduleLabels as $value => $label)
                            <option value="{{ $value }}" {{ old('module', $filters['module'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    @error('module') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="action" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสการดำเนินการ</label>
                    <input id="action" name="action" type="search" value="{{ old('action', $filters['action'] ?? '') }}" placeholder="เช่น users.updated" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-slate-500">กรองด้วยรหัสที่ตรงกัน เช่น users.updated</p>
                    @error('action') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="date_from" class="mb-1.5 block text-sm font-semibold text-slate-700">ตั้งแต่วันที่</label>
                    <input id="date_from" name="date_from" type="date" value="{{ old('date_from', $filters['date_from'] ?? '') }}" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('date_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="date_to" class="mb-1.5 block text-sm font-semibold text-slate-700">ถึงวันที่</label>
                    <input id="date_to" name="date_to" type="date" value="{{ old('date_to', $filters['date_to'] ?? '') }}" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('date_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </details>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <p class="text-sm font-semibold text-slate-800">พบ {{ number_format($logs->total()) }} รายการ</p>
                <p class="text-xs text-slate-500">เก็บรายละเอียดโดยไม่บันทึกรหัสผ่าน</p>
            </div>

            @if ($logs->count())
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">วันเวลา</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">ผู้ดำเนินการ</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">หมวด / การดำเนินการ</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">รายละเอียด</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">หมายเลข IP</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($logs as $log)
                                <tr class="align-top transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        <p class="font-medium">{{ \App\Support\ThaiDateFormatter::formatDate($log->created_at, config('app.display_timezone')) }}</p>
                                        <p class="text-xs text-slate-400">{{ $log->created_at->timezone(config('app.display_timezone'))->format('H:i:s') }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-slate-800">{{ optional($log->actor)->name ?: 'ไม่ระบุผู้ดำเนินการ' }}</p>
                                        <p class="text-xs text-slate-500">{{ optional($log->actor)->email ?: '-' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $moduleClasses[$log->module] ?? 'bg-slate-100 text-slate-700' }} inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                            {{ $moduleLabels[$log->module] ?? $log->module }}
                                        </span>
                                        <p class="mt-1.5 font-mono text-xs text-slate-500">รหัส: {{ $log->action }}</p>
                                    </td>
                                    <td class="max-w-sm px-5 py-4 text-sm text-slate-600">{{ $log->description }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500">{{ $log->ip_address ?: '-' }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <details class="relative inline-block text-left">
                                            <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">ดูรายละเอียด</summary>
                                            <div class="mt-2 w-80 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-xl">
                                                @include('audit-logs.partials.detail', ['log' => $log])
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 lg:hidden">
                    @foreach ($logs as $log)
                        <article class="p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <span class="{{ $moduleClasses[$log->module] ?? 'bg-slate-100 text-slate-700' }} inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                        {{ $moduleLabels[$log->module] ?? $log->module }}
                                    </span>
                                    <p class="mt-2 font-mono text-xs text-slate-500">รหัสการดำเนินการ: {{ $log->action }}</p>
                                </div>
                                <time class="text-right text-xs text-slate-400">
                                    {{ \App\Support\ThaiDateFormatter::formatDate($log->created_at, config('app.display_timezone')) }}<br>{{ $log->created_at->timezone(config('app.display_timezone'))->format('H:i:s') }}
                                </time>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-800">{{ $log->description }}</p>
                            <div class="mt-2 flex flex-col gap-1 text-xs text-slate-500">
                                <span>{{ optional($log->actor)->name ?: 'ไม่ระบุผู้ดำเนินการ' }}</span>
                                <span>อีเมล: {{ optional($log->actor)->email ?: '-' }}</span>
                                <span>IP: <span class="font-mono">{{ $log->ip_address ?: '-' }}</span></span>
                            </div>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-indigo-600">ดูรายละเอียด</summary>
                                <div class="mt-3 rounded-lg bg-slate-50 p-3">
                                    @include('audit-logs.partials.detail', ['log' => $log])
                                </div>
                            </details>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 px-4 py-4">{{ $logs->links() }}</div>
            @else
                <div class="px-6 py-16 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <i class="ph ph-clipboard-text h-6 w-6 text-2xl leading-none" aria-hidden="true"></i>
                    </span>
                    @if ($hasActiveFilters)
                        <p class="mt-4 text-sm font-semibold text-slate-700">ไม่พบรายการที่ตรงกับตัวกรอง</p>
                        <p class="mt-1 text-sm text-slate-500">ลองปรับเงื่อนไข หรือกลับไปดูรายการทั้งหมด</p>
                        <a href="{{ route('audit-logs.index') }}" class="mt-4 inline-flex min-h-[42px] items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">ล้างตัวกรอง</a>
                    @else
                        <p class="mt-4 text-sm font-semibold text-slate-700">ยังไม่มีบันทึกการตรวจสอบ</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
