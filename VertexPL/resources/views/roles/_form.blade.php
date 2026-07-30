@php
    $editing = isset($role);
    $allPermissionIds = $permissionGroups
        ->flatMap(fn ($group) => $group['permissions']->pluck('id'))
        ->values()
        ->all();
@endphp

<div x-data='permissionMatrix(@json(array_map("intval", $selectedPermissionIds)))' class="grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-1">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">ข้อมูล Role</h2>
            <p class="mt-1 text-sm text-slate-500">ชื่อที่ใช้ระบุบทบาทของผู้ใช้งาน</p>

            <div class="mt-6">
                <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อ Role <span class="text-red-500">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $role->name ?? '') }}" required {{ $editing && $role->name === 'Super Admin' ? 'readonly' : '' }} class="{{ $editing && $role->name === 'Super Admin' ? 'bg-slate-100 text-slate-500' : '' }} block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                @if ($editing && $role->name === 'Super Admin')
                    <p class="mt-1.5 text-xs text-amber-600">Role นี้เป็น Role หลักของระบบและไม่สามารถเปลี่ยนชื่อได้</p>
                @endif
            </div>

            <div class="mt-6 rounded-lg bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Permission ที่เลือก</p>
                <p class="mt-1 text-3xl font-bold text-indigo-700"><span x-text="selected.length">{{ count($selectedPermissionIds) }}</span></p>
                <p class="text-xs text-indigo-600">จาก {{ count($allPermissionIds) }} สิทธิ์</p>
            </div>
        </section>
    </div>

    <div class="xl:col-span-2">
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Permission Matrix</h2>
                    <p class="mt-1 text-sm text-slate-500">เลือกสิทธิ์ที่ Role นี้สามารถใช้งานได้</p>
                </div>
                <button type="button" @click='selectAll(@json($allPermissionIds))' class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50">
                    เลือก/ยกเลิกทั้งหมด
                </button>
            </div>

            @error('permissions')
                <div class="mx-5 mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 sm:mx-6">{{ $message }}</div>
            @enderror

            <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                @foreach ($permissionGroups as $group)
                    @php $groupIds = $group['permissions']->pluck('id')->values()->all(); @endphp
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ $group['label'] }}</h3>
                                <p class="text-xs text-slate-500">{{ count($groupIds) }} Permission</p>
                            </div>
                            <button type="button" @click='toggleGroup(@json($groupIds))' class="text-xs font-semibold text-indigo-600 hover:text-indigo-800" x-text='groupSelected(@json($groupIds)) ? "ยกเลิกกลุ่ม" : "เลือกทั้งกลุ่ม"'>
                                เลือกทั้งกลุ่ม
                            </button>
                        </div>
                        <div class="mt-3 space-y-2.5">
                            @foreach ($group['permissions'] as $permission)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg p-2 transition hover:bg-slate-50">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" x-model.number="selected" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-slate-700">{{ $permission->name }}</span>
                                        <span class="block text-xs text-slate-400">{{ ucfirst(last(explode('.', $permission->name))) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>

<div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ route('roles.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ยกเลิก</a>
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        {{ $editing ? 'บันทึก Role และ Permission' : 'เพิ่ม Role' }}
    </button>
</div>
