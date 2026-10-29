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
        <button type="button" class="ml-auto inline-flex items-center justify-center rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-white lg:hidden" @click="sidebarOpen = false">
            <span class="sr-only">ปิดเมนู</span>
            <i class="ph ph-x h-6 w-6 text-2xl leading-none" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="app-sidebar-scroll flex-1 space-y-1 overflow-y-auto px-3 py-5">
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">เมนูหลัก</p>

        @can('dashboard.view')
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <i class="ph ph-house h-5 w-5 shrink-0 text-xl leading-none" aria-hidden="true"></i>
                Dashboard
            </a>
        @endcan

        @can('ncr.view')
            @php($isNcrMenuActive = request()->routeIs('ncr.*') && ! request()->routeIs('ncr.tasks'))

            <a href="{{ route('ncr.index') }}" class="{{ $isNcrMenuActive ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition" @if($isNcrMenuActive) aria-current="page" @endif>
                <i class="ph ph-clipboard-text h-5 w-5 shrink-0 text-xl leading-none" aria-hidden="true"></i>
                รายการ NCR
            </a>
            
            @php($isNcrTasksActive = request()->routeIs('ncr.tasks'))
            <a href="{{ route('ncr.tasks') }}" class="{{ $isNcrTasksActive ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition" @if($isNcrTasksActive) aria-current="page" @endif>
                <i class="ph ph-list-checks h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                งาน NCR ของฉัน
            </a>
            @php($isNotificationsActive = request()->routeIs('notifications.*'))
            <a href="{{ route('notifications.index') }}" class="{{ $isNotificationsActive ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition" @if($isNotificationsActive) aria-current="page" @endif>
                <i class="ph ph-bell h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                การแจ้งเตือน
            </a>
            @can('ncr.master.manage')
                @php($isNcrMasterMenuActive = request()->routeIs('ncr-masters.*'))
                <details class="ncr-master-navigation" data-ncr-master-navigation>
                    <summary class="{{ $isNcrMasterMenuActive ? 'bg-slate-800 text-white' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }} flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-semibold transition">
                        <span class="flex min-w-0 items-center gap-3">
                            <i class="ph ph-notebook h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                            <span>ข้อมูลหลัก NCR</span>
                        </span>
                        <i class="ph ph-caret-down ncr-master-navigation__chevron h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                    </summary>
                    <div class="mt-1 space-y-1">
                        @foreach(config('ncr.masters') as $masterType => $master)
                            @php($isNcrMasterItemActive = request()->routeIs('ncr-masters.*') && request()->route('type') === $masterType)
                            <a href="{{ route('ncr-masters.index', $masterType) }}" class="{{ $isNcrMasterItemActive ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2 pl-14 text-sm font-medium transition" @if($isNcrMasterItemActive) aria-current="page" @endif>
                                <x-ncr-master-icon :type="$masterType" />
                                {{ $master['title'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
                <script>
                    (() => {
                        const navigation = document.currentScript.previousElementSibling;
                        const storageKey = 'vertexpl-ncr-master-navigation-open';

                        try {
                            navigation.open = window.localStorage.getItem(storageKey) === 'true';
                        } catch (_) {
                            navigation.open = false;
                        }

                        navigation.addEventListener('toggle', () => {
                            try {
                                window.localStorage.setItem(storageKey, String(navigation.open));
                            } catch (_) {
                                // Keep the native menu usable when browser storage is unavailable.
                            }
                        });
                    })();
                </script>
            @endcan
        @endcan

        @if (auth()->user()->can('users.view') || auth()->user()->can('departments.view') || auth()->user()->can('roles.view') || auth()->user()->can('department-roles.manage'))
            @php($isUserAdminMenuActive = request()->routeIs('users.*', 'departments.*', 'roles.*', 'department-roles.*'))
            <details class="user-admin-navigation" data-user-admin-navigation data-open-by-default="{{ $isUserAdminMenuActive ? 'true' : 'false' }}">
                <summary class="{{ $isUserAdminMenuActive ? 'bg-slate-800 text-white' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }} flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition">
                    <span class="flex min-w-0 items-center gap-3">
                        <i class="ph ph-users-three h-5 w-5 shrink-0 text-xl leading-none" aria-hidden="true"></i>
                        <span>ผู้ใช้งาน / หน่วยงาน / สิทธิ์</span>
                    </span>
                    <i class="ph ph-caret-down user-admin-navigation__chevron h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                </summary>
                <div class="mt-1 space-y-1">
                    @can('users.view')
                        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition" @if(request()->routeIs('users.*')) aria-current="page" @endif>
                            <i class="ph ph-user-list h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                            ผู้ใช้งาน
                        </a>
                    @endcan
                    @can('department-roles.manage')
                        <a href="{{ route('department-roles.index') }}" class="{{ request()->routeIs('department-roles.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition" @if(request()->routeIs('department-roles.*')) aria-current="page" @endif>
                            <i class="ph ph-identification-card h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                            บทบาทในหน่วยงาน
                        </a>
                    @endcan
                    @can('departments.view')
                        <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition" @if(request()->routeIs('departments.*')) aria-current="page" @endif>
                            <i class="ph ph-buildings h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                            หน่วยงาน
                        </a>
                    @endcan
                    @can('roles.view')
                        <a href="{{ route('roles.index') }}" class="{{ request()->routeIs('roles.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 pl-11 text-sm font-medium transition" @if(request()->routeIs('roles.*')) aria-current="page" @endif>
                            <i class="ph ph-shield-check h-4 w-4 shrink-0 text-base leading-none" aria-hidden="true"></i>
                            สิทธิ์
                        </a>
                    @endcan
                </div>
            </details>
            <script>
                (() => {
                    const navigation = document.currentScript.previousElementSibling;
                    const storageKey = 'vertexpl-user-admin-navigation-open';

                    try {
                        const savedState = window.localStorage.getItem(storageKey);
                        navigation.open = savedState === null
                            ? navigation.dataset.openByDefault === 'true'
                            : savedState === 'true';
                    } catch (_) {
                        navigation.open = false;
                    }

                    navigation.addEventListener('toggle', () => {
                        try {
                            window.localStorage.setItem(storageKey, String(navigation.open));
                        } catch (_) {
                            // Keep the native menu usable when browser storage is unavailable.
                        }
                    });
                })();
            </script>
        @endif

        @if (auth()->user()->can('system.audit.view') || auth()->user()->can('system.settings'))
            <p class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-wider text-slate-500">ระบบ</p>
        @endif

        @can('system.audit.view')
            <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <i class="ph ph-clipboard-text h-5 w-5 shrink-0 text-xl leading-none" aria-hidden="true"></i>
                บันทึกการตรวจสอบ
            </a>
        @endcan

        @can('system.settings')
            <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition">
                <i class="ph ph-gear h-5 w-5 shrink-0 text-xl leading-none" aria-hidden="true"></i>
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
    <button type="button" class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">เปิดเมนู</span>
        <i class="ph ph-list h-6 w-6 text-2xl leading-none" aria-hidden="true"></i>
    </button>

    <div class="ml-auto flex items-center gap-3">
        <x-theme-toggle />
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
