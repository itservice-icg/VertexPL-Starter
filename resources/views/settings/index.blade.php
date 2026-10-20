<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">จัดการระบบ</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">System Settings</h1>
                <p class="mt-1 text-sm text-slate-500">จัดการค่ากลางที่โมดูลต่าง ๆ ใช้ร่วมกัน</p>
            </div>
            <a href="{{ route('settings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">เพิ่มการตั้งค่า</a>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        <form method="GET" action="{{ route('settings.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-[1fr_16rem_auto]">
                <div>
                    <label for="search" class="sr-only">ค้นหาการตั้งค่า</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="ค้นหา key หรือชื่อการตั้งค่า" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="group" class="sr-only">กลุ่ม</label>
                    <select id="group" name="group" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุกกลุ่ม</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group }}" {{ ($filters['group'] ?? '') === $group ? 'selected' : '' }}>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><i class="ph ph-magnifying-glass h-4 w-4 text-base leading-none" aria-hidden="true"></i>ค้นหา</button>
            </div>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="text-sm font-semibold text-slate-800">พบ {{ number_format($settings->total()) }} รายการ</p>
            </div>

            @if ($settings->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">การตั้งค่า</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">กลุ่ม/ชนิด</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">ค่า</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($settings as $setting)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-slate-900">{{ $setting->label }}</p>
                                        <code class="text-xs text-indigo-600">{{ $setting->key }}</code>
                                        @if ($setting->description)<p class="mt-1 max-w-md text-xs text-slate-500">{{ $setting->description }}</p>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $setting->group }}</span>
                                        <span class="ml-1 text-xs">{{ $setting->type }}</span>
                                    </td>
                                    <td class="max-w-xs truncate px-5 py-4 text-sm text-slate-700">
                                        @if ($setting->type === 'boolean')
                                            {{ $setting->value === '1' ? 'เปิด' : 'ปิด' }}
                                        @else
                                            {{ $setting->value ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <div class="inline-flex gap-2">
                                            <a href="{{ route('settings.edit', $setting) }}" aria-label="แก้ไข {{ $setting->key }}" title="แก้ไข" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-700"><i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i></a>
                                            <form method="POST" action="{{ route('settings.destroy', $setting) }}" data-confirm data-confirm-always data-confirm-title="ลบการตั้งค่า?" data-confirm-text="การตั้งค่า {{ $setting->key }} จะถูกลบ" data-confirm-button="ลบ">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" aria-label="ลบ {{ $setting->key }}" title="ลบ" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"><i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-4 py-4">{{ $settings->links() }}</div>
            @else
                <div class="px-6 py-16 text-center">
                    <p class="text-sm font-semibold text-slate-700">ยังไม่มีการตั้งค่า</p>
                    <p class="mt-1 text-sm text-slate-500">เพิ่มค่ากลางเพื่อให้โมดูลต่าง ๆ นำไปใช้งาน</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
