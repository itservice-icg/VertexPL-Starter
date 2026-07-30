<div
    x-show="sidebarOpen"
    x-transition.opacity
    class="fixed inset-0 z-30 bg-slate-900/60 lg:hidden"
    @click="sidebarOpen = false"
    aria-hidden="true"
></div>

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 text-slate-100 transition-transform duration-200 ease-in-out lg:translate-x-0"
>
    <div class="flex h-16 items-center border-b border-slate-800 px-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 font-bold text-white">V</span>
            <span>
                <span class="block text-base font-bold leading-tight">VertexPL</span>
                <span class="block text-xs text-slate-400">Internal Platform</span>
            </span>
        </a>
        <button type="button" class="ml-auto rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-white lg:hidden" @click="sidebarOpen = false">
            <span class="sr-only">ปิดเมนู</span>
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">เมนูหลัก</p>

        @can('dashboard.view')
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12 12 3l9 9M5 10v10h14V10M9 20v-6h6v6" />
                </svg>
                Dashboard
            </a>
        @endcan

        @can('users.view')
            <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                ผู้ใช้งาน
            </a>
        @endcan

        @can('roles.view')
            <a href="{{ route('roles.index') }}" class="{{ request()->routeIs('roles.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM9 12l2 2 4-4" />
                </svg>
                บทบาทและสิทธิ์
            </a>
        @endcan

        @can('imports.view')
            <a href="{{ route('imports.index') }}" class="{{ request()->routeIs('imports.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                </svg>
                Import / Export
            </a>
        @endcan

        @if (auth()->user()->can('system.audit.view') || auth()->user()->can('system.settings'))
            <p class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-wider text-slate-500">ระบบ</p>
        @endif

        @can('system.audit.view')
            <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11h6M9 15h4M7 3h10l2 2v16H5V5l2-2Z" />
                </svg>
                Audit Log
            </a>
        @endcan

        @can('system.settings')
            <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-4v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3v-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06L7.04 4.3l.06.06A1.65 1.65 0 0 0 8.92 4a1.65 1.65 0 0 0 1-1.51V2h4v.49A1.65 1.65 0 0 0 15 4a1.65 1.65 0 0 0 1.82-.33l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.4 9c.12.37.47.62.86.62H21v4h-.74c-.39 0-.74.25-.86.62Z" />
                </svg>
                ตั้งค่าระบบ
            </a>
        @endcan
    </nav>

    <div class="border-t border-slate-800 p-4">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg p-1 transition hover:bg-slate-800">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-700 text-sm font-semibold">
                {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ Auth::user()->getRoleNames()->join(', ') ?: 'No role' }}</p>
            </div>
        </a>
    </div>
</aside>

<header class="fixed left-0 right-0 top-0 z-20 flex h-16 items-center border-b border-slate-200 bg-white px-4 shadow-sm lg:left-64 sm:px-6 lg:px-8">
    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">เปิดเมนู</span>
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <div class="ml-auto flex items-center gap-3">
        <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-slate-700">{{ Auth::user()->name }}</p>
            <p class="text-xs text-slate-500">{{ Auth::user()->email }}</p>
        </div>

        <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }} hidden rounded-lg border px-3 py-2 text-sm font-medium transition sm:inline-flex">
            โปรไฟล์
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600">
                ออกจากระบบ
            </button>
        </form>
    </div>
</header>
