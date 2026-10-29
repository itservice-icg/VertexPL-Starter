<x-app-layout>
    <div class="w-full space-y-4 px-4 py-5 sm:px-6">
        <div><p class="text-sm font-medium text-indigo-600">NCR Master Data</p><h1 class="text-2xl font-bold text-slate-900">{{ isset($record) ? 'แก้ไข' : 'เพิ่ม' }}{{ $definition['title'] }}</h1></div>
        <form method="POST" action="{{ isset($record) ? route('ncr-masters.update', [$type,$record]) : route('ncr-masters.store', $type) }}" class="space-y-5 rounded-xl bg-white p-6 shadow">
            @csrf @isset($record) @method('PUT') @endisset
            <x-auth-validation-errors :errors="$errors" />
            <div>
                <x-label for="code" value="รหัส"/>
                <x-input id="code" name="code" class="mt-1 block w-full" value="{{ old('code', $record->code ?? '') }}" maxlength="{{ $definition['code_max'] ?? 100 }}" pattern="[A-Za-z0-9_-]+" title="ใช้ A-Z ตัวเลข - หรือ _ เช่น P-001" required />
                <p class="mt-1 text-xs text-slate-500">ใช้ A-Z ตัวเลข ขีดกลาง (-) หรือขีดล่าง (_) เช่น P-001; ตัวพิมพ์เล็กจะถูกปรับเป็นตัวพิมพ์ใหญ่</p>
            </div>
            <div><x-label for="name_th" :value="$definition['name_label'] ?? 'ชื่อภาษาไทย'"/><x-input id="name_th" name="name_th" class="mt-1 block w-full" value="{{ old('name_th', $record->name_th ?? '') }}" required /></div>
            @foreach($definition['fields'] as $field => $label)
                @if(str_starts_with($field, 'require_'))
                    @php($fieldValue = old($field, isset($record) ? $record->getAttribute($field) : false))
                    <label class="flex items-center gap-3"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" {{ $fieldValue ? 'checked' : '' }} class="rounded border-slate-300"><span>{{ $label }}</span></label>
                @else
                    <div><x-label :for="$field" :value="$label"/><x-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $record->{$field} ?? '')" /></div>
                @endif
            @endforeach
            @php($isActive = (bool) old('is_active', isset($record) ? $record->is_active : true))
            <div><x-label for="is_active" value="สถานะ"/><select id="is_active" name="is_active" class="mt-1 block w-full rounded-lg border-slate-300"><option value="1" {{ $isActive ? 'selected' : '' }}>ใช้งาน</option><option value="0" {{ ! $isActive ? 'selected' : '' }}>ปิดใช้งาน</option></select></div>
            <div class="flex justify-end gap-3"><a href="{{ route('ncr-masters.index',$type) }}" class="rounded-lg border px-4 py-2">ยกเลิก</a><button class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">บันทึก</button></div>
        </form>
    </div>
</x-app-layout>
