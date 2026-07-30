<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">User Management</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">แก้ไขผู้ใช้งาน</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $user->name }} · {{ $user->email }}</p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <form
            method="POST"
            action="{{ route('users.update', $user) }}"
            @unless(auth()->user()->is($user))
                data-confirm
                data-confirm-when-inactive
                data-confirm-title="ปิดใช้งานบัญชี?"
                data-confirm-text="ผู้ใช้งานจะไม่สามารถเข้าสู่ระบบได้จนกว่าจะเปิดใช้งานอีกครั้ง"
                data-confirm-button="ปิดใช้งาน"
            @endunless
        >
            @csrf
            @method('PUT')
            @include('users._form')
        </form>
    </div>
</x-app-layout>
