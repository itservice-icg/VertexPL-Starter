<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">NCR Master Data</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">หน่วยงาน</h1>
                <p class="mt-1 text-sm text-slate-500">จัดการหน่วยงานสำหรับ Ownership และ Assignment</p>
            </div>
            @can('departments.create')
                <button type="button" data-ncr-dialog-open="create-department" data-ncr-dialog-reset-form aria-haspopup="dialog" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">เพิ่มหน่วยงาน</button>
            @endcan
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status" aria-live="polite">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('departments.index') }}" aria-label="ตัวกรองหน่วยงาน" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
            <div class="relative min-w-0 flex-1">
                <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">ค้นหา</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 pt-6 text-slate-400" aria-hidden="true"><i class="ph ph-magnifying-glass h-5 w-5 text-xl leading-none"></i></span>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="รหัสหรือชื่อหน่วยงาน" class="block min-h-[42px] w-full rounded-lg border-slate-300 py-2 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="min-w-0 sm:w-48 sm:shrink-0">
                <label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะ</label>
                <select id="status" name="status" class="block min-h-[42px] w-full rounded-lg border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">ทุกสถานะ</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="flex gap-2 sm:shrink-0">
                @if(request()->filled('search') || request()->filled('status'))
                    <a href="{{ route('departments.index') }}" class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">ล้าง</a>
                @endif
                <button type="submit" class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><i class="ph ph-magnifying-glass h-4 w-4 text-base leading-none" aria-hidden="true"></i>ค้นหา</button>
            </div>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @if($departments->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">รหัส</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">ชื่อหน่วยงาน</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">ผู้ใช้งาน</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">สถานะ</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">จัดการ</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($departments as $department)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900">{{ $department->code }}</td>
                                    <td class="px-5 py-4"><p class="text-sm font-medium text-slate-800">{{ $department->name_th }}</p><p class="text-xs text-slate-500">{{ $department->name_en ?: '-' }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ number_format($department->users_count) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4"><span class="{{ $department->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }} rounded-full px-2.5 py-1 text-xs font-semibold">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right"><div class="inline-flex gap-2">
                                        @can('departments.update')<button type="button" data-ncr-dialog-open="edit-department-{{ $department->id }}" aria-haspopup="dialog" aria-label="แก้ไข {{ $department->name_th }}" title="แก้ไข" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>@endcan
                                        @can('departments.delete')
                                            <form method="POST" action="{{ route('departments.destroy', $department) }}" data-confirm data-confirm-always data-confirm-title="ลบหน่วยงาน?" data-confirm-text="ลบได้เฉพาะหน่วยงานที่ไม่มีผู้ใช้งาน" data-confirm-button="ลบหน่วยงาน">
                                                @csrf @method('DELETE')
                                                <button type="submit" aria-label="ลบ {{ $department->name_th }}" title="ลบ" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600"><i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                            </form>
                                        @endcan
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-4 py-4">{{ $departments->links() }}</div>
            @else
                <div class="px-6 py-16 text-center"><p class="text-sm font-semibold text-slate-700">ไม่พบหน่วยงาน</p><p class="mt-1 text-sm text-slate-500">เพิ่มหน่วยงานหรือเปลี่ยนตัวกรองแล้วลองอีกครั้ง</p></div>
            @endif
        </div>

        @can('departments.create')
            <dialog id="create-department" class="ncr-item-dialog department-dialog" aria-labelledby="create-department-heading" closedby="any" @if($errors->any() && old('_form_context') === 'create_department') data-open-on-load @endif>
                <div class="ncr-item-dialog__header">
                    <div>
                        <h2 id="create-department-heading" class="text-lg font-bold">เพิ่มหน่วยงาน</h2>
                        <p class="mt-1 text-sm text-slate-500">สร้างหน่วยงานสำหรับ Ownership และ Assignment ใน NCR</p>
                    </div>
                    <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างเพิ่มหน่วยงาน">×</button></form>
                </div>
                <form method="POST" action="{{ route('departments.store') }}" class="department-dialog__form">
                    @csrf
                    <input type="hidden" name="_form_context" value="create_department">
                    <div class="department-dialog__content">
                        @if($errors->any() && old('_form_context') === 'create_department')
                            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-dialog-form-error role="alert" aria-live="assertive">
                                <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                                <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @include('departments._form', ['inDialog' => true, 'idPrefix' => 'create-department', 'formContext' => 'create_department'])
                    </div>
                    <div class="ncr-corrective-dialog__footer">
                        <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                        <button type="submit" class="ncr-dialog-submit">เพิ่มหน่วยงาน</button>
                    </div>
                </form>
            </dialog>
        @endcan

        @can('departments.update')
            @foreach($departments as $department)
                <dialog id="edit-department-{{ $department->id }}" class="ncr-item-dialog department-dialog" aria-labelledby="edit-department-heading-{{ $department->id }}" closedby="any" @if($errors->any() && old('_form_context') === 'edit_department' && (string) old('_department_id') === (string) $department->id) data-open-on-load @endif>
                    <div class="ncr-item-dialog__header">
                        <div>
                            <h2 id="edit-department-heading-{{ $department->id }}" class="text-lg font-bold">แก้ไขหน่วยงาน</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $department->code }} · {{ $department->name_th }}</p>
                        </div>
                        <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างแก้ไขหน่วยงาน">×</button></form>
                    </div>
                    <form method="POST" action="{{ route('departments.update', $department) }}" class="department-dialog__form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form_context" value="edit_department">
                        <input type="hidden" name="_department_id" value="{{ $department->id }}">
                        <div class="department-dialog__content">
                            @if($errors->any() && old('_form_context') === 'edit_department' && (string) old('_department_id') === (string) $department->id)
                                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                                    <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                                    <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                                </div>
                            @endif
                            @include('departments._form', ['department' => $department, 'inDialog' => true, 'idPrefix' => 'edit-department-'.$department->id, 'formContext' => 'edit_department'])
                        </div>
                        <div class="ncr-corrective-dialog__footer">
                            <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                            <button type="submit" class="ncr-dialog-submit">บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </dialog>
            @endforeach
        @endcan
    </div>
</x-app-layout>
