<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">VertexPL</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $title }}</h1>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.3 3.7 2.8 17a2 2 0 0 0 1.74 3h14.92a2 2 0 0 0 1.74-3L13.7 3.7a2 2 0 0 0-3.4 0Z" />
                </svg>
            </span>
            <h2 class="mt-4 text-lg font-bold text-slate-900">{{ $title }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">{{ $description }}</p>
            <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-indigo-500">พร้อมสำหรับพัฒนา CRUD ในขั้นถัดไป</p>
        </div>
    </div>
</x-app-layout>
