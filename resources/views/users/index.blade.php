<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">จัดการระบบ</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">ผู้ใช้งาน</h1>
                <p class="mt-1 text-sm text-slate-500">ค้นหา กรอง และจัดการบัญชีผู้ใช้งานภายในองค์กร</p>
            </div>
            @can('users.create')
                <button type="button" data-ncr-dialog-open="create-user" aria-haspopup="dialog" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white">
                    <i class="ph ph-user-plus h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                    เพิ่มผู้ใช้งาน
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        <form method="GET" action="{{ route('users.index') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-12">
                <div class="md:col-span-2 xl:col-span-4">
                    <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">ค้นหาผู้ใช้งาน</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="ph ph-magnifying-glass h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                        </span>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="ชื่อหรืออีเมล" class="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="xl:col-span-2">
                    <label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะ</label>
                    <select id="status" name="status" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุกสถานะ</option>
                        <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label for="role" class="mb-1.5 block text-sm font-semibold text-slate-700">Role</label>
                    <select id="role" name="role" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุก Role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ (string) ($filters['role'] ?? '') === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label for="department" class="mb-1.5 block text-sm font-semibold text-slate-700">หน่วยงาน</label>
                    <select id="department" name="department" class="block w-full rounded-lg border-slate-300 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทุกหน่วยงาน</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" {{ (string) ($filters['department'] ?? '') === (string) $department->id ? 'selected' : '' }}>{{ $department->name_th }}</option>
                        @endforeach
                    </select>
                </div>
            <div class="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-2">
                @if (request()->hasAny(['search', 'status', 'role', 'department']))
                    <a href="{{ route('users.index') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ล้าง</a>
                @endif
                <button type="submit" class="inline-flex min-h-11 min-w-28 flex-1 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:flex-none"><i class="ph ph-magnifying-glass h-4 w-4 text-base leading-none" aria-hidden="true"></i>ค้นหา</button>
            </div>
        </form>

        <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="text-sm font-semibold text-slate-800">พบ {{ number_format($users->total()) }} รายการ</p>
            </div>

            @if ($users->count())
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">ผู้ใช้งาน</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">หน่วยงาน</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">สถานะ</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Login ล่าสุด</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($users as $user)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ $user->name }}
                                                    @if (auth()->user()->is($user))
                                                        <span class="ml-1 text-xs font-medium text-indigo-600">(คุณ)</span>
                                                    @endif
                                                </p>
                                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $user->getRoleNames()->join(', ') ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        {{ optional($user->department)->name_th ?: '-' }}
                                        @if ($user->departmentRole)
                                            <span class="mt-1 block text-xs font-medium text-indigo-600">{{ $user->departmentRole->name_th }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="{{ $user->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }} inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ $user->last_login_at ? $user->last_login_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') : '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            @can('users.update')
                                                <button type="button" data-ncr-dialog-open="edit-user-{{ $user->id }}" aria-haspopup="dialog" aria-label="แก้ไข {{ $user->name }}" title="แก้ไข" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"><i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                            @endcan
                                            @can('users.delete')
                                                @unless (auth()->user()->is($user))
                                                    <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm data-confirm-always data-confirm-title="ลบผู้ใช้งาน?" data-confirm-text="บัญชี {{ $user->name }} จะถูกลบแบบ Soft Delete" data-confirm-button="ลบผู้ใช้งาน">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" aria-label="ลบ {{ $user->name }}" title="ลบ" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"><i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                                    </form>
                                                @endunless
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($users as $user)
                        <article class="p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                        <span class="{{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }} shrink-0 rounded-full px-2 py-1 text-xs font-semibold">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Role: {{ $user->getRoleNames()->join(', ') ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">หน่วยงาน: {{ optional($user->department)->name_th ?: '-' }}</p>
                                    @if ($user->departmentRole)
                                        <p class="mt-1 text-xs font-medium text-indigo-600">{{ $user->departmentRole->name_th }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @can('users.update')
                                            <button type="button" data-ncr-dialog-open="edit-user-{{ $user->id }}" aria-haspopup="dialog" aria-label="แก้ไข {{ $user->name }}" title="แก้ไข" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600"><i class="ph ph-pencil-simple h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                        @endcan
                                        @can('users.delete')
                                            @unless (auth()->user()->is($user))
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm data-confirm-always data-confirm-title="ลบผู้ใช้งาน?" data-confirm-text="บัญชี {{ $user->name }} จะถูกลบแบบ Soft Delete" data-confirm-button="ลบผู้ใช้งาน">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" aria-label="ลบ {{ $user->name }}" title="ลบ" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600"><i class="ph ph-trash h-4 w-4 text-base leading-none" aria-hidden="true"></i></button>
                                                </form>
                                            @endunless
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 px-4 py-4">
                    {{ $users->links() }}
                </div>
            @else
                <div class="px-6 py-16 text-center">
                    <p class="text-sm font-semibold text-slate-700">ไม่พบผู้ใช้งาน</p>
                    <p class="mt-1 text-sm text-slate-500">ลองเปลี่ยนคำค้นหาหรือตัวกรองแล้วค้นหาอีกครั้ง</p>
                </div>
            @endif
        </div>
    </div>

    @can('users.create')
        <dialog id="create-user" class="ncr-item-dialog user-dialog" aria-labelledby="create-user-heading" closedby="any" @if($errors->any() && old('_form_context') === 'create_user') data-open-on-load @endif>
            <div class="ncr-item-dialog__header">
                <div>
                    <h2 id="create-user-heading" class="text-lg font-bold">เพิ่มผู้ใช้งาน</h2>
                    <p class="mt-1 text-sm text-slate-500">สร้างบัญชีและกำหนดสิทธิ์เริ่มต้น</p>
                </div>
                <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างเพิ่มผู้ใช้งาน">×</button></form>
            </div>
            <form method="POST" action="{{ route('users.store') }}" class="user-dialog__form">
                @csrf
                <input type="hidden" name="_form_context" value="create_user">
                <div class="user-dialog__content">
                    @if($errors->any() && old('_form_context') === 'create_user')
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                            <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                            <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @include('users._form', ['user' => null, 'inDialog' => true, 'idPrefix' => 'create-user'])
                </div>
                <div class="ncr-corrective-dialog__footer">
                    <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                    <button type="submit" class="ncr-dialog-submit">เพิ่มผู้ใช้งาน</button>
                </div>
            </form>
        </dialog>
    @endcan

    @can('users.update')
        @foreach ($users as $user)
            <dialog id="edit-user-{{ $user->id }}" class="ncr-item-dialog user-dialog" aria-labelledby="edit-user-heading-{{ $user->id }}" closedby="any" @if($errors->any() && old('_form_context') === 'edit_user' && (string) old('user_id') === (string) $user->id) data-open-on-load @endif>
                <div class="ncr-item-dialog__header">
                    <div>
                        <h2 id="edit-user-heading-{{ $user->id }}" class="text-lg font-bold">แก้ไขผู้ใช้งาน</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $user->name }} · {{ $user->email }}</p>
                    </div>
                    <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างแก้ไขผู้ใช้งาน">×</button></form>
                </div>
                <form method="POST" action="{{ route('users.update', $user) }}" class="user-dialog__form"
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
                    <input type="hidden" name="_form_context" value="edit_user">
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    <div class="user-dialog__content">
                        @if($errors->any() && old('_form_context') === 'edit_user' && (string) old('user_id') === (string) $user->id)
                            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert" aria-live="assertive">
                                <p class="font-semibold">บันทึกไม่สำเร็จ</p>
                                <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @include('users._form', ['user' => $user, 'inDialog' => true, 'idPrefix' => 'edit-user-'.$user->id])
                    </div>
                    <div class="ncr-corrective-dialog__footer">
                        <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                        <button type="submit" class="ncr-dialog-submit">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </dialog>
        @endforeach
    @endcan
</x-app-layout>
