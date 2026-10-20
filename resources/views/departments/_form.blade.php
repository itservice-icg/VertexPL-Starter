@php
    $editing = isset($department);
    $inDialog = $inDialog ?? false;
    $idPrefix = $idPrefix ?? 'department';
    $formContext = $formContext ?? ($editing ? 'edit_department' : 'create_department');
    $matchesFormInput = ! $inDialog || (old('_form_context') === $formContext && (! $editing || (string) old('_department_id') === (string) $department->id));
@endphp

@unless($inDialog)
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div>
            <h2 class="text-base font-bold text-slate-900">ข้อมูลหน่วยงาน</h2>
            <p class="mt-1 text-sm text-slate-500">รหัสหน่วยงานต้องไม่ซ้ำและใช้ตัวอักษร ตัวเลข ขีดกลาง หรือขีดล่าง</p>
        </div>
@endunless

<div class="{{ $inDialog ? 'grid gap-5 sm:grid-cols-2' : 'mt-6 grid gap-5 sm:grid-cols-2' }}">
    <div>
        <label for="{{ $idPrefix }}-code" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสหน่วยงาน <span class="text-red-500">*</span></label>
        <input id="{{ $idPrefix }}-code" name="code" type="text" maxlength="50" required value="{{ $matchesFormInput ? old('code', $department->code ?? '') : ($department->code ?? '') }}" class="block w-full rounded-lg border-slate-300 text-sm uppercase focus:border-indigo-500 focus:ring-indigo-500">
        @if($matchesFormInput) @error('code') <p class="mt-1.5 text-xs text-red-600" @if($inDialog) data-dialog-form-error @endif>{{ $message }}</p> @enderror @endif
    </div>
    <div>
        <label for="{{ $idPrefix }}-is-active" class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะ <span class="text-red-500">*</span></label>
        <select id="{{ $idPrefix }}-is-active" name="is_active" required class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="1" {{ (string) ($matchesFormInput ? old('is_active', $department->is_active ?? 1) : ($department->is_active ?? 1)) === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (string) ($matchesFormInput ? old('is_active', $department->is_active ?? 1) : ($department->is_active ?? 1)) === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
        @if($matchesFormInput) @error('is_active') <p class="mt-1.5 text-xs text-red-600" @if($inDialog) data-dialog-form-error @endif>{{ $message }}</p> @enderror @endif
    </div>
    <div>
        <label for="{{ $idPrefix }}-name-th" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
        <input id="{{ $idPrefix }}-name-th" name="name_th" type="text" maxlength="255" required value="{{ $matchesFormInput ? old('name_th', $department->name_th ?? '') : ($department->name_th ?? '') }}" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        @if($matchesFormInput) @error('name_th') <p class="mt-1.5 text-xs text-red-600" @if($inDialog) data-dialog-form-error @endif>{{ $message }}</p> @enderror @endif
    </div>
    <div>
        <label for="{{ $idPrefix }}-name-en" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อภาษาอังกฤษ</label>
        <input id="{{ $idPrefix }}-name-en" name="name_en" type="text" maxlength="255" value="{{ $matchesFormInput ? old('name_en', $department->name_en ?? '') : ($department->name_en ?? '') }}" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        @if($matchesFormInput) @error('name_en') <p class="mt-1.5 text-xs text-red-600" @if($inDialog) data-dialog-form-error @endif>{{ $message }}</p> @enderror @endif
    </div>
</div>

@unless($inDialog)
    </div>
    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ route('departments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ยกเลิก</a>
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            {{ $editing ? 'บันทึกการแก้ไข' : 'เพิ่มหน่วยงาน' }}
        </button>
    </div>
@endunless
