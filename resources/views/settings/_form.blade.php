@php $editing = isset($setting); @endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <label for="key" class="mb-1.5 block text-sm font-semibold text-slate-700">Key <span class="text-red-500">*</span></label>
            <input id="key" name="key" type="text" required value="{{ old('key', $setting->key ?? '') }}" placeholder="เช่น uploads.max_size_mb" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('key') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">ใช้ตัวพิมพ์เล็ก ตัวเลข จุด และ underscore</p>
        </div>
        <div>
            <label for="label" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อแสดงผล <span class="text-red-500">*</span></label>
            <input id="label" name="label" type="text" required value="{{ old('label', $setting->label ?? '') }}" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('label') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="group" class="mb-1.5 block text-sm font-semibold text-slate-700">กลุ่ม <span class="text-red-500">*</span></label>
            <input id="group" name="group" type="text" required value="{{ old('group', $setting->group ?? 'general') }}" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('group') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="type" class="mb-1.5 block text-sm font-semibold text-slate-700">ชนิดข้อมูล <span class="text-red-500">*</span></label>
            <select id="type" name="type" required class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach (['string' => 'ข้อความ', 'integer' => 'จำนวนเต็ม', 'boolean' => 'เปิด/ปิด', 'json' => 'JSON'] as $value => $label)
                    <option value="{{ $value }}" {{ old('type', $setting->type ?? 'string') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('type') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-2">
            <label for="value" class="mb-1.5 block text-sm font-semibold text-slate-700">ค่า</label>
            <textarea id="value" name="value" rows="5" class="block w-full rounded-lg border-slate-300 font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('value', $setting->value ?? '') }}</textarea>
            @error('value') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">ชนิดเปิด/ปิดใช้ค่า 1 หรือ 0 และชนิด JSON ต้องเป็น JSON ที่ถูกต้อง</p>
        </div>
        <div class="md:col-span-2">
            <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">คำอธิบาย</label>
            <textarea id="description" name="description" rows="3" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $setting->description ?? '') }}</textarea>
            @error('description') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ route('settings.index') }}" class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">ยกเลิก</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">{{ $editing ? 'บันทึกการตั้งค่า' : 'เพิ่มการตั้งค่า' }}</button>
</div>
