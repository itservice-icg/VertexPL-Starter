<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">จัดการระบบ</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Role & Permission</h1>
                <p class="mt-1 text-sm text-slate-500">กำหนดบทบาทและขอบเขตการเข้าถึงของผู้ใช้งาน</p>
            </div>
            @if (auth()->user()->can('roles.create') && auth()->user()->can('permissions.view'))
                <a href="{{ route('roles.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14" />
                    </svg>
                    เพิ่ม Role
                </a>
            @endif
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <form method="GET" action="{{ route('roles.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                        </svg>
                    </span>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="ค้นหาชื่อ Role" aria-label="ค้นหาชื่อ Role" class="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                @if (request()->filled('search'))
                    <a href="{{ route('roles.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ล้าง</a>
                @endif
                <button type="submit" class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-900">ค้นหา</button>
            </div>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="text-sm font-semibold text-slate-800">พบ {{ number_format($roles->total()) }} Role</p>
            </div>

            @if ($roles->count())
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">ชื่อ Role</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">ผู้ใช้งาน</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Permission</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($roles as $role)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="{{ $role->name === 'Super Admin' ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-700' }} flex h-10 w-10 items-center justify-center rounded-lg">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10 2 2 4-4" />
                                                </svg>
                                            </span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">{{ $role->name }}</p>
                                                @if ($role->name === 'Super Admin')
                                                    <span class="text-xs font-medium text-amber-600">Protected Role</span>
                                                @else
                                                    <span class="text-xs text-slate-500">Web guard</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex min-w-[2.25rem] justify-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $role->users_count }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex min-w-[2.25rem] justify-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $role->permissions_count }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            @if (auth()->user()->can('roles.update') && auth()->user()->can('permissions.view'))
                                                <a href="{{ route('roles.edit', $role) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">แก้ไข</a>
                                            @endif
                                            @can('roles.delete')
                                                @if ($role->name !== 'Super Admin')
                                                    <form method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm data-confirm-always data-confirm-title="ลบ Role?" data-confirm-text="{{ $role->users_count ? 'Role นี้ยังมีผู้ใช้งานและระบบจะไม่อนุญาตให้ลบ' : 'Role '.$role->name.' จะถูกลบออกจากระบบ' }}" data-confirm-button="ลบ Role">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">ลบ</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($roles as $role)
                        <article class="p-4">
                            <div class="flex items-start gap-3">
                                <span class="{{ $role->name === 'Super Admin' ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-700' }} flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-semibold text-slate-900">{{ $role->name }}</p>
                                        @if ($role->name === 'Super Admin')
                                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">Protected</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex gap-4 text-xs text-slate-500">
                                        <span>ผู้ใช้ {{ $role->users_count }}</span>
                                        <span>สิทธิ์ {{ $role->permissions_count }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if (auth()->user()->can('roles.update') && auth()->user()->can('permissions.view'))
                                            <a href="{{ route('roles.edit', $role) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">แก้ไข</a>
                                        @endif
                                        @can('roles.delete')
                                            @if ($role->name !== 'Super Admin')
                                                <form method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm data-confirm-always data-confirm-title="ลบ Role?" data-confirm-text="{{ $role->users_count ? 'Role นี้ยังมีผู้ใช้งานและระบบจะไม่อนุญาตให้ลบ' : 'Role '.$role->name.' จะถูกลบออกจากระบบ' }}" data-confirm-button="ลบ Role">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600">ลบ</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 px-4 py-4">{{ $roles->links() }}</div>
            @else
                <div class="px-6 py-16 text-center">
                    <p class="text-sm font-semibold text-slate-700">ไม่พบ Role</p>
                    <p class="mt-1 text-sm text-slate-500">ลองเปลี่ยนคำค้นหาแล้วค้นหาอีกครั้ง</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
