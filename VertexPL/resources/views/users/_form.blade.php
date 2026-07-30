@php
    $editing = isset($user);
    $isSelf = $editing && auth()->user()->is($user);
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">ข้อมูลผู้ใช้งาน</h2>
            <p class="mt-1 text-sm text-slate-500">ข้อมูลสำหรับระบุตัวตนและเข้าสู่ระบบ</p>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อผู้ใช้งาน <span class="text-red-500">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required autocomplete="name" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">อีเมล <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required autocomplete="email" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">รหัสผ่าน</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $editing ? 'เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน' : 'กำหนดรหัสผ่านเริ่มต้นสำหรับผู้ใช้งาน' }}</p>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสผ่าน @unless($editing)<span class="text-red-500">*</span>@endunless</label>
                    <input id="password" name="password" type="password" {{ ! $editing ? 'required' : '' }} autocomplete="new-password" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">ยืนยันรหัสผ่าน @unless($editing)<span class="text-red-500">*</span>@endunless</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" {{ ! $editing ? 'required' : '' }} autocomplete="new-password" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">อย่างน้อย 14 ตัวอักษร และต้องมีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และสัญลักษณ์</p>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">สิทธิ์และสถานะ</h2>

            <div class="mt-5 space-y-5">
                <div>
                    <label for="role_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Role <span class="text-red-500">*</span></label>
                    <select id="role_id" name="role_id" required class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">เลือก Role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ (string) old('role_id', $editing ? optional($user->roles->first())->id : '') === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role_id') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="is_active" class="mb-1.5 block text-sm font-semibold text-slate-700">สถานะบัญชี</label>
                    @if ($isSelf)
                        <input type="hidden" name="is_active" value="1">
                        <select id="is_active" disabled class="block w-full rounded-lg border-slate-200 bg-slate-100 text-sm text-slate-500">
                            <option>Active</option>
                        </select>
                        <p class="mt-1.5 text-xs text-amber-600">ไม่สามารถปิดบัญชีของตนเองได้</p>
                    @else
                        <select id="is_active" name="is_active" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1" {{ (string) old('is_active', $user->is_active ?? 1) === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (string) old('is_active', $user->is_active ?? 1) === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    @endif
                    @error('is_active') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>
    </div>
</div>

<div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">ยกเลิก</a>
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        {{ $editing ? 'บันทึกการแก้ไข' : 'เพิ่มผู้ใช้งาน' }}
    </button>
</div>
