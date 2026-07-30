@php
    $moduleLabels = [
        'auth' => 'Authentication',
        'users' => 'User Management',
        'roles' => 'Role & Permission',
        'profile' => 'Profile',
    ];
    $moduleClasses = [
        'auth' => 'bg-sky-50 text-sky-700',
        'users' => 'bg-indigo-50 text-indigo-700',
        'roles' => 'bg-violet-50 text-violet-700',
        'profile' => 'bg-emerald-50 text-emerald-700',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">ตรวจสอบระบบ</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Audit Log</h1>
            <p class="mt-1 text-sm text-slate-500">ประวัติการเข้าสู่ระบบและการเปลี่ยนแปลงข้อมูลสำคัญ</p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2 xl:col-span-2">
                    <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">ค้นหา</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                            </svg>
                        </span>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="ผู้ใช้ อีเมล Action หรือรายละเอียด" class="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="module" class="mb-1.5 block text-sm font-semibold text-slate-700">Module</label>
                    <select id="module" name="module" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุก Module</option>
                        @foreach ($moduleLabels as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['module'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="action" class="mb-1.5 block text-sm font-semibold text-slate-700">Action</label>
                    <select id="action" name="action" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุก Action</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" {{ ($filters['action'] ?? '') === $action ? 'selected' : '' }}>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="mb-1.5 block text-sm font-semibold text-slate-700">ตั้งแต่วันที่</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="date_to" class="mb-1.5 block text-sm font-semibold text-slate-700">ถึงวันที่</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            @error('date_to') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                @if (request()->hasAny(['search', 'module', 'action', 'date_from', 'date_to']))
                    <a href="{{ route('audit-logs.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ล้างตัวกรอง</a>
                @endif
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-900">ค้นหา</button>
            </div>
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
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Module / Action</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">รายละเอียด</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">ข้อมูล</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($logs as $log)
                                <tr class="align-top transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        <p class="font-medium">{{ $log->created_at->format('d/m/Y') }}</p>
                                        <p class="text-xs text-slate-400">{{ $log->created_at->format('H:i:s') }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-slate-800">{{ optional($log->actor)->name ?: 'Guest / System' }}</p>
                                        <p class="text-xs text-slate-500">{{ optional($log->actor)->email ?: '-' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $moduleClasses[$log->module] ?? 'bg-slate-100 text-slate-700' }} inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                            {{ $moduleLabels[$log->module] ?? $log->module }}
                                        </span>
                                        <p class="mt-1.5 font-mono text-xs text-slate-500">{{ $log->action }}</p>
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
                                    <p class="mt-2 font-mono text-xs text-slate-500">{{ $log->action }}</p>
                                </div>
                                <time class="text-right text-xs text-slate-400">
                                    {{ $log->created_at->format('d/m/Y') }}<br>{{ $log->created_at->format('H:i:s') }}
                                </time>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-800">{{ $log->description }}</p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>{{ optional($log->actor)->name ?: 'Guest / System' }}</span>
                                <span class="font-mono">{{ $log->ip_address ?: '-' }}</span>
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
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11h6M9 15h4M7 3h10l2 2v16H5V5l2-2Z" />
                        </svg>
                    </span>
                    <p class="mt-4 text-sm font-semibold text-slate-700">ไม่พบ Audit Log</p>
                    <p class="mt-1 text-sm text-slate-500">ยังไม่มีข้อมูลที่ตรงกับตัวกรอง</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
