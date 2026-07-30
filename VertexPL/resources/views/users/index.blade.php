<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">จัดการระบบ</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">ผู้ใช้งาน</h1>
                <p class="mt-1 text-sm text-slate-500">ค้นหา กรอง และจัดการบัญชีผู้ใช้งานภายในองค์กร</p>
            </div>
            @can('users.create')
                <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14" />
                    </svg>
                    เพิ่มผู้ใช้งาน
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <form method="GET" action="{{ route('users.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="md:col-span-2 xl:col-span-2">
                    <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">ค้นหาผู้ใช้งาน</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                            </svg>
                        </span>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="ชื่อหรืออีเมล" class="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะ</label>
                    <select id="status" name="status" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุกสถานะ</option>
                        <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="role" class="mb-1.5 block text-sm font-semibold text-slate-700">Role</label>
                    <select id="role" name="role" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุก Role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ (string) ($filters['role'] ?? '') === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                @if (request()->hasAny(['search', 'status', 'role']))
                    <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ล้างตัวกรอง</a>
                @endif
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-900">ค้นหา</button>
            </div>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="text-sm font-semibold text-slate-800">พบ {{ number_format($users->total()) }} รายการ</p>
            </div>

            @if ($users->count())
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">ผู้ใช้งาน</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">สถานะ</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Login ล่าสุด</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($users as $user)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ $user->name }}
                                                    @if (auth()->user()->is($user))
                                                        <span class="ml-1 text-xs font-medium text-indigo-600">(คุณ)</span>
                                                    @endif
                                                </p>
                                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $user->getRoleNames()->join(', ') ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="{{ $user->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }} inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ optional($user->last_login_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            @can('users.update')
                                                <a href="{{ route('users.edit', $user) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">แก้ไข</a>
                                            @endcan
                                            @can('users.delete')
                                                @unless (auth()->user()->is($user))
                                                    <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm data-confirm-always data-confirm-title="ลบผู้ใช้งาน?" data-confirm-text="บัญชี {{ $user->name }} จะถูกลบแบบ Soft Delete" data-confirm-button="ลบผู้ใช้งาน">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">ลบ</button>
                                                    </form>
                                                @endunless
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($users as $user)
                        <article class="p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                        <span class="{{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }} shrink-0 rounded-full px-2 py-1 text-xs font-semibold">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Role: {{ $user->getRoleNames()->join(', ') ?: '-' }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @can('users.update')
                                            <a href="{{ route('users.edit', $user) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">แก้ไข</a>
                                        @endcan
                                        @can('users.delete')
                                            @unless (auth()->user()->is($user))
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm data-confirm-always data-confirm-title="ลบผู้ใช้งาน?" data-confirm-text="บัญชี {{ $user->name }} จะถูกลบแบบ Soft Delete" data-confirm-button="ลบผู้ใช้งาน">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600">ลบ</button>
                                                </form>
                                            @endunless
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 px-4 py-4">
                    {{ $users->links() }}
                </div>
            @else
                <div class="px-6 py-16 text-center">
                    <p class="text-sm font-semibold text-slate-700">ไม่พบผู้ใช้งาน</p>
                    <p class="mt-1 text-sm text-slate-500">ลองเปลี่ยนคำค้นหาหรือตัวกรองแล้วค้นหาอีกครั้ง</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
