<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">VertexPL</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $title }}</h1>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                <i class="ph ph-warning h-7 w-7 text-2xl leading-none" aria-hidden="true"></i>
            </span>
            <h2 class="mt-4 text-lg font-bold text-slate-900">{{ $title }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">{{ $description }}</p>
            <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-indigo-500">พร้อมสำหรับพัฒนา CRUD ในขั้นถัดไป</p>
        </div>
    </div>
</x-app-layout>
