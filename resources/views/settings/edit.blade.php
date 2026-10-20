<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">System Settings</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">แก้ไขการตั้งค่า</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $setting->key }}</p>
        </div>
    </x-slot>
    <div class="p-4 sm:p-6">
        <form method="POST" action="{{ route('settings.update', $setting) }}">
            @csrf
            @method('PUT')
            @include('settings._form')
        </form>
    </div>
</x-app-layout>
