<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">NCR Master Data</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">แก้ไขหน่วยงาน</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $department->code }} · {{ $department->name_th }}</p>
        </div>
    </x-slot>
    <div class="p-4 sm:p-6">
        <form method="POST" action="{{ route('departments.update', $department) }}">
            @csrf
            @method('PUT')
            @include('departments._form')
        </form>
    </div>
</x-app-layout>
