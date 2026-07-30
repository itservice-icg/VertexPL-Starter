<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">Role & Permission</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">เพิ่ม Role</h1>
            <p class="mt-1 text-sm text-slate-500">กำหนดชื่อและสิทธิ์สำหรับบทบาทใหม่</p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            @include('roles._form')
        </form>
    </div>
</x-app-layout>
