@php
    $editing = isset($user);
    $isSelf = $editing && auth()->user()->is($user);
    $inDialog = $inDialog ?? false;
    $idPrefix = $idPrefix ?? 'user';
    $formContext = $editing ? 'edit_user' : 'create_user';
    $matchesFormInput = ! $inDialog || (old('_form_context') === $formContext && (! $editing || (string) old('user_id') === (string) $user->id));
    $selectedRoleId = $matchesFormInput ? old('role_id', $editing ? optional($user->roles->first())->id : '') : ($editing ? optional($user->roles->first())->id : '');
    $selectedDepartmentId = $matchesFormInput ? old('department_id', $user->department_id ?? '') : ($user->department_id ?? '');
    $selectedDepartmentRole = $matchesFormInput ? old('department_role', $user->department_role ?? 'MEMBER') : ($user->department_role ?? 'MEMBER');
    $selectedIsActive = $matchesFormInput ? old('is_active', $user->is_active ?? 1) : ($user->is_active ?? 1);
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">ข้อมูลผู้ใช้งาน</h2>
            <p class="mt-1 text-sm text-slate-500">ข้อมูลสำหรับระบุตัวตนและเข้าสู่ระบบ</p>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="{{ $idPrefix }}-name" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อผู้ใช้งาน <span class="text-red-500">*</span></label>
                    <input id="{{ $idPrefix }}-name" name="name" type="text" value="{{ $matchesFormInput ? old('name', $user->name ?? '') : ($user->name ?? '') }}" required autocomplete="name" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @if($matchesFormInput) @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>
                <div>
                    <label for="{{ $idPrefix }}-email" class="mb-1.5 block text-sm font-semibold text-slate-700">อีเมล <span class="text-red-500">*</span></label>
                    <input id="{{ $idPrefix }}-email" name="email" type="email" value="{{ $matchesFormInput ? old('email', $user->email ?? '') : ($user->email ?? '') }}" required autocomplete="email" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @if($matchesFormInput) @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>
            </div>
            <div class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="text-base font-bold text-slate-900">รหัสผ่าน</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $editing ? 'เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน' : 'กำหนดรหัสผ่านเริ่มต้นสำหรับผู้ใช้งาน' }}</p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="{{ $idPrefix }}-password" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสผ่าน @unless($editing)<span class="text-red-500">*</span>@endunless</label>
                        <input id="{{ $idPrefix }}-password" name="password" type="password" {{ ! $editing ? 'required' : '' }} autocomplete="new-password" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @if($matchesFormInput) @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="{{ $idPrefix }}-password-confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">ยืนยันรหัสผ่าน @unless($editing)<span class="text-red-500">*</span>@endunless</label>
                        <input id="{{ $idPrefix }}-password-confirmation" name="password_confirmation" type="password" {{ ! $editing ? 'required' : '' }} autocomplete="new-password" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">อย่างน้อย 14 ตัวอักษร และต้องมีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และสัญลักษณ์</p>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">สิทธิ์และสถานะ</h2>

            <div class="mt-5 space-y-5">
                <div>
                    <label for="{{ $idPrefix }}-role-id" class="mb-1.5 block text-sm font-semibold text-slate-700">Role ระบบ (ชุดสิทธิ์) <span class="text-red-500">*</span></label>
                    <select id="{{ $idPrefix }}-role-id" name="role_id" required aria-describedby="{{ $idPrefix }}-role-id-help" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">เลือก Role ระบบ</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ (string) $selectedRoleId === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p id="{{ $idPrefix }}-role-id-help" class="mt-1.5 text-xs text-slate-500">กำหนดว่าผู้ใช้มีสิทธิ์เข้าใช้เมนูและทำงานใดได้บ้าง เช่น NCR Reporter, QA หรือ Managing Director</p>
                    @if($matchesFormInput) @error('role_id') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="{{ $idPrefix }}-department-id" class="mb-1.5 block text-sm font-semibold text-slate-700">หน่วยงาน</label>
                    <select id="{{ $idPrefix }}-department-id" name="department_id" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ยังไม่กำหนดหน่วยงาน</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" {{ (string) $selectedDepartmentId === (string) $department->id ? 'selected' : '' }}>
                                {{ $department->code }} — {{ $department->name_th }}{{ $department->is_active ? '' : ' (Inactive)' }}
                            </option>
                        @endforeach
                    </select>
                    @if($matchesFormInput) @error('department_id') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="{{ $idPrefix }}-department-role" class="mb-1.5 block text-sm font-semibold text-slate-700">ตำแหน่งในหน่วยงาน</label>
                    <select id="{{ $idPrefix }}-department-role" name="department_role" required aria-describedby="{{ $idPrefix }}-department-role-help" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($departmentRoles as $departmentRole)
                            <option value="{{ $departmentRole->code }}" {{ $selectedDepartmentRole === $departmentRole->code ? 'selected' : '' }}>{{ $departmentRole->name_th }}</option>
                        @endforeach
                    </select>
                    <p id="{{ $idPrefix }}-department-role-help" class="mt-1.5 text-xs text-slate-500">อนุมัติแผน Corrective Action ได้เมื่อมีสิทธิ์ NCR “จัดทำ ดำเนินการ และอนุมัติแผน Corrective Action” และเป็นหัวหน้าหน่วยงาน/ผู้ดูแลระบบของหน่วยงานที่รับผิดชอบ หรือมีบทบาท Administrator</p>
                    @if($matchesFormInput) @error('department_role') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="is_active" class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะบัญชี</label>
                    @if ($isSelf)
                        <input type="hidden" name="is_active" value="1">
                            <select id="{{ $idPrefix }}-is-active" disabled class="block w-full rounded-lg border-slate-200 bg-slate-100 text-sm text-slate-500">
                            <option>Active</option>
                        </select>
                        <p class="mt-1.5 text-xs text-amber-600">ไม่สามารถปิดบัญชีของตนเองได้</p>
                    @else
                        <select id="{{ $idPrefix }}-is-active" name="is_active" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1" {{ (string) $selectedIsActive === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (string) $selectedIsActive === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    @endif
                    @if($matchesFormInput) @error('is_active') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror @endif
                </div>
            </div>
        </section>
    </div>
</div>

@unless($inDialog)
    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ยกเลิก</a>
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            {{ $editing ? 'บันทึกการแก้ไข' : 'เพิ่มผู้ใช้งาน' }}
        </button>
    </div>
@endunless
