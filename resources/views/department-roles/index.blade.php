<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">บทบาทในหน่วยงาน</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">จัดการรายการตำแหน่งในหน่วยงานที่ผู้ใช้สามารถเลือกได้</p>
        </div>
    </x-slot>

    <div class="space-y-5 p-4 sm:p-6">
        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status" aria-live="polite">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any() && ! in_array(old('_form_context'), ['create_department_role', 'edit_department_role'], true))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="department-role-list-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 id="department-role-list-heading" class="text-base font-bold text-slate-800">รายการบทบาท</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format($departmentRoles->total()) }} รายการ</p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <form method="GET" action="{{ route('department-roles.index') }}" class="flex w-full gap-2 sm:w-auto">
                        <label for="department-role-search" class="sr-only">ค้นหารหัสหรือชื่อบทบาท</label>
                        <input id="department-role-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="ค้นหารหัสหรือชื่อบทบาท" class="min-h-12 min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2.5 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:w-64 sm:flex-none sm:text-sm">
                        <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><i class="ph ph-magnifying-glass h-4 w-4 text-base leading-none" aria-hidden="true"></i>ค้นหา</button>
                        @if (request()->filled('search'))
                            <a href="{{ route('department-roles.index') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">ล้าง</a>
                        @endif
                    </form>
                    <button type="button" data-ncr-dialog-open="create-department-role" aria-haspopup="dialog" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">เพิ่มบทบาท</button>
                </div>
            </div>

            @if ($departmentRoles->count())
                <div class="hidden overflow-x-auto xl:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold text-slate-500">รหัส</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold text-slate-500">ชื่อบทบาท</th>
                                <th scope="col" class="px-5 py-3 text-center text-xs font-semibold text-slate-500">ผู้ใช้</th>
                                <th scope="col" class="px-5 py-3 text-right text-xs font-semibold text-slate-500">การทำงาน</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($departmentRoles as $departmentRole)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <code class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $departmentRole->code }}</code>
                                    </td>
                                    <td class="min-w-56 px-5 py-4 text-slate-700">{{ $departmentRole->name_th }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center text-slate-600">{{ number_format($departmentRole->users_count) }} คน</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <button type="button" data-ncr-dialog-open="edit-department-role-{{ $departmentRole->id }}" aria-haspopup="dialog" aria-label="แก้ไขบทบาท {{ $departmentRole->name_th }}" title="แก้ไข" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"><i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                            @if ($departmentRole->code === 'DEPARTMENT_HEAD')
                                                <span class="inline-flex min-h-11 items-center rounded-lg px-3 text-xs font-semibold text-slate-500" title="บทบาทระบบที่ใช้ระบุหัวหน้าหน่วยงานผู้อนุมัติแผน">บทบาทระบบ</span>
                                            @else
                                                <form method="POST" action="{{ route('department-roles.destroy', $departmentRole) }}" data-confirm data-confirm-always data-confirm-title="ลบบทบาทนี้?" data-confirm-text="{{ $departmentRole->users_count ? 'บทบาทนี้ยังมีผู้ใช้ ระบบจะไม่อนุญาตให้ลบ' : 'ลบบทบาท '.$departmentRole->name_th.' ออกจากรายการหรือไม่' }}" data-confirm-button="ลบบทบาท">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" aria-label="ลบบทบาท {{ $departmentRole->name_th }}" title="ลบ" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"><i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-200 xl:hidden">
                    @foreach ($departmentRoles as $departmentRole)
                        <article class="p-4 sm:p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <code class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $departmentRole->code }}</code>
                                <span class="inline-flex min-h-8 items-center rounded-full bg-slate-100 px-3 text-xs font-semibold text-slate-600">ผู้ใช้ {{ number_format($departmentRole->users_count) }} คน</span>
                            </div>

                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ $departmentRole->name_th }}</p>

                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-1 gap-2 sm:flex-none">
                                    <button type="button" data-ncr-dialog-open="edit-department-role-{{ $departmentRole->id }}" aria-haspopup="dialog" aria-label="แก้ไขบทบาท {{ $departmentRole->name_th }}" title="แก้ไข" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"><i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                    @if ($departmentRole->code === 'DEPARTMENT_HEAD')
                                        <span class="inline-flex min-h-12 items-center rounded-lg px-3 text-sm font-semibold text-slate-500" title="บทบาทระบบที่ใช้ระบุหัวหน้าหน่วยงานผู้อนุมัติแผน">บทบาทระบบ</span>
                                    @else
                                        <form method="POST" action="{{ route('department-roles.destroy', $departmentRole) }}" data-confirm data-confirm-always data-confirm-title="ลบบทบาทนี้?" data-confirm-text="{{ $departmentRole->users_count ? 'บทบาทนี้ยังมีผู้ใช้ ระบบจะไม่อนุญาตให้ลบ' : 'ลบบทบาท '.$departmentRole->name_th.' ออกจากรายการหรือไม่' }}" data-confirm-button="ลบบทบาท">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="ลบบทบาท {{ $departmentRole->name_th }}" title="ลบ" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"><i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 p-4 sm:px-5">
                    <x-page-navigation :paginator="$departmentRoles" />
                </div>
            @else
                <div class="px-5 py-12 text-center sm:py-16">
                    <p class="text-sm font-semibold text-slate-700">ไม่พบบทบาทในหน่วยงาน</p>
                    <p class="mt-1 text-sm text-slate-500">ลองเปลี่ยนคำค้นหา หรือเพิ่มบทบาทใหม่ด้านบน</p>
                </div>
            @endif
        </section>

        <dialog id="create-department-role" class="ncr-item-dialog department-role-dialog" aria-labelledby="create-department-role-heading" closedby="any" @if($errors->any() && old('_form_context') === 'create_department_role') data-open-on-load @endif>
            <div class="ncr-item-dialog__header">
                <div>
                    <h2 id="create-department-role-heading" class="text-lg font-bold">เพิ่มบทบาทในหน่วยงาน</h2>
                    <p class="mt-1 text-sm text-slate-500">รหัสใช้ภายในระบบและแก้ไขภายหลังไม่ได้</p>
                </div>
                <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างเพิ่มบทบาท">×</button></form>
            </div>
            <form method="POST" action="{{ route('department-roles.store') }}" class="department-role-dialog__form">
                @csrf
                <input type="hidden" name="_form_context" value="create_department_role">
                <div class="department-role-dialog__content">
                    @if($errors->any() && old('_form_context') === 'create_department_role')
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                            <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <fieldset class="grid gap-4 sm:grid-cols-2">
                        <legend class="sr-only">ข้อมูลบทบาทใหม่</legend>
                        <div>
                            <label for="new-department-role-code" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสบทบาท <span aria-hidden="true">*</span></label>
                            <input id="new-department-role-code" name="code" type="text" value="{{ old('code') }}" required maxlength="30" pattern="[A-Z][A-Z0-9_]*" autocapitalize="off" autocorrect="off" spellcheck="false" aria-describedby="new-department-role-code-help" placeholder="เช่น DEPUTY_HEAD" class="block min-h-12 w-full rounded-lg border-slate-300 px-3 py-2.5 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p id="new-department-role-code-help" class="mt-1 text-xs text-slate-500">ใช้ A-Z, 0-9 และ _ เท่านั้น</p>
                        </div>
                        <div>
                            <label for="new-department-role-name" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อบทบาท <span aria-hidden="true">*</span></label>
                            <input id="new-department-role-name" name="name_th" type="text" value="{{ old('name_th') }}" required maxlength="100" placeholder="เช่น รองหัวหน้าหน่วยงาน" class="block min-h-12 w-full rounded-lg border-slate-300 px-3 py-2.5 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                    </fieldset>
                </div>
                <div class="ncr-corrective-dialog__footer">
                    <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                    <button type="submit" class="ncr-dialog-submit">เพิ่มบทบาท</button>
                </div>
            </form>
        </dialog>

        @foreach ($departmentRoles as $departmentRole)
            <dialog id="edit-department-role-{{ $departmentRole->id }}" class="ncr-item-dialog department-role-dialog" aria-labelledby="edit-department-role-heading-{{ $departmentRole->id }}" closedby="any" @if($errors->any() && old('_form_context') === 'edit_department_role' && (string) old('role_id') === (string) $departmentRole->id) data-open-on-load @endif>
                <div class="ncr-item-dialog__header">
                    <div>
                        <h2 id="edit-department-role-heading-{{ $departmentRole->id }}" class="text-lg font-bold">แก้ไขบทบาทในหน่วยงาน</h2>
                        <p class="mt-1 text-sm text-slate-500">รหัสบทบาท <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $departmentRole->code }}</code></p>
                    </div>
                    <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างแก้ไขบทบาท">×</button></form>
                </div>
                <form method="POST" action="{{ route('department-roles.update', $departmentRole) }}" class="department-role-dialog__form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form_context" value="edit_department_role">
                    <input type="hidden" name="role_id" value="{{ $departmentRole->id }}">
                    <div class="department-role-dialog__content">
                        @if($errors->any() && old('_form_context') === 'edit_department_role' && (string) old('role_id') === (string) $departmentRole->id)
                            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                                <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                                <ul class="mt-1 list-inside list-disc">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <label for="edit-department-role-name-{{ $departmentRole->id }}" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อบทบาท <span aria-hidden="true">*</span></label>
                        <input id="edit-department-role-name-{{ $departmentRole->id }}" name="name_th" type="text" value="{{ old('_form_context') === 'edit_department_role' && (string) old('role_id') === (string) $departmentRole->id ? old('name_th', $departmentRole->name_th) : $departmentRole->name_th }}" required maxlength="100" class="block min-h-12 w-full rounded-lg border-slate-300 px-3 py-2.5 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="ncr-corrective-dialog__footer">
                        <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                        <button type="submit" class="ncr-dialog-submit">บันทึก</button>
                    </div>
                </form>
            </dialog>
        @endforeach
    </div>
</x-app-layout>
