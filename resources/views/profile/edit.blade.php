<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">บัญชีของฉัน</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Profile & Security</h1>
            <p class="mt-1 text-sm text-slate-500">จัดการข้อมูลส่วนตัวและความปลอดภัยของบัญชี</p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xl font-bold text-indigo-700">
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-bold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <dl class="mt-6 divide-y divide-slate-100 text-sm">
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-slate-500">Role</dt>
                            <dd class="text-right font-semibold text-slate-800">{{ $user->getRoleNames()->join(', ') ?: '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-slate-500">สถานะ</dt>
                            <dd>
                                <span class="{{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }} rounded-full px-2.5 py-1 text-xs font-semibold">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-slate-500">อีเมล</dt>
                            <dd class="min-w-0 truncate text-right font-semibold text-slate-800">{{ $user->email }}</dd>
                        </div>
                    </dl>

                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-bold text-slate-900">ข้อมูลความปลอดภัย</h2>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Login ล่าสุด</dt>
                            <dd class="mt-1 font-medium text-slate-700">{{ $user->last_login_at ? $user->last_login_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i:s') : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">IP ล่าสุด</dt>
                            <dd class="mt-1 font-medium text-slate-700">{{ $user->last_login_ip ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">เปลี่ยนรหัสผ่านล่าสุด</dt>
                            <dd class="mt-1 font-medium text-slate-700">{{ $user->password_changed_at ? $user->password_changed_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i:s') : 'ยังไม่มีข้อมูล' }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <div class="space-y-6 xl:col-span-2">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">ข้อมูลโปรไฟล์</h2>
                        <p class="mt-1 text-sm text-slate-500">การบันทึกจะขอให้ยืนยันรหัสผ่านก่อนดำเนินการ</p>
                    </div>

                    <form method="POST" action="{{ route('profile.update') }}" class="mt-6">
                        @csrf
                        @method('PATCH')

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="profile_name" class="mb-1.5 block text-sm font-semibold text-slate-700">ชื่อผู้ใช้งาน</label>
                                <input id="profile_name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="profile_email" class="mb-1.5 block text-sm font-semibold text-slate-700">อีเมล</label>
                                <input id="profile_email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                บันทึกโปรไฟล์
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="passwordStrength()">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">เปลี่ยนรหัสผ่าน</h2>
                        <p class="mt-1 text-sm text-slate-500">หลังเปลี่ยนสำเร็จ ระบบจะออกจาก Session ในอุปกรณ์อื่นทั้งหมด</p>
                    </div>

                    <form method="POST" action="{{ route('profile.password.update') }}" class="mt-6 space-y-5">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสผ่านปัจจุบัน</label>
                            <div class="relative">
                                <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" required autocomplete="current-password" class="block w-full rounded-lg border-slate-300 pr-12 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" @click="showCurrent = !showCurrent" class="absolute inset-y-0 right-0 px-4 text-xs font-semibold text-slate-500 hover:text-indigo-600" :aria-label="showCurrent ? 'ซ่อนรหัสผ่านปัจจุบัน' : 'แสดงรหัสผ่านปัจจุบัน'">
                                    <span x-text="showCurrent ? 'ซ่อน' : 'แสดง'">แสดง</span>
                                </button>
                            </div>
                            @error('current_password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="new_password" class="mb-1.5 block text-sm font-semibold text-slate-700">รหัสผ่านใหม่</label>
                                <div class="relative">
                                    <input id="new_password" name="password" :type="showPassword ? 'text' : 'password'" x-model="password" required autocomplete="new-password" class="block w-full rounded-lg border-slate-300 pr-12 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 px-4 text-xs font-semibold text-slate-500 hover:text-indigo-600" :aria-label="showPassword ? 'ซ่อนรหัสผ่านใหม่' : 'แสดงรหัสผ่านใหม่'">
                                        <span x-text="showPassword ? 'ซ่อน' : 'แสดง'">แสดง</span>
                                    </button>
                                </div>
                                @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">ยืนยันรหัสผ่านใหม่</label>
                                <div class="relative">
                                    <input id="password_confirmation" name="password_confirmation" :type="showConfirmation ? 'text' : 'password'" required autocomplete="new-password" class="block w-full rounded-lg border-slate-300 pr-12 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" @click="showConfirmation = !showConfirmation" class="absolute inset-y-0 right-0 px-4 text-xs font-semibold text-slate-500 hover:text-indigo-600" :aria-label="showConfirmation ? 'ซ่อนการยืนยันรหัสผ่าน' : 'แสดงการยืนยันรหัสผ่าน'">
                                        <span x-text="showConfirmation ? 'ซ่อน' : 'แสดง'">แสดง</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Password Strength</p>
                                <p class="text-xs font-semibold text-slate-700" x-text="label">ยังไม่ได้กรอก</p>
                            </div>
                            <div class="mt-2 grid grid-cols-5 gap-1.5">
                                @foreach (range(1, 5) as $score)
                                    <span class="h-1.5 rounded-full transition-colors" :class="score >= {{ $score }} ? (score >= 5 ? 'bg-emerald-500' : score >= 3 ? 'bg-amber-500' : 'bg-red-500') : 'bg-slate-200'"></span>
                                @endforeach
                            </div>
                            <div class="mt-3 grid gap-1.5 text-xs sm:grid-cols-2">
                                <p :class="checks.length ? 'text-emerald-600' : 'text-slate-400'">อย่างน้อย 14 ตัวอักษร</p>
                                <p :class="checks.uppercase && checks.lowercase ? 'text-emerald-600' : 'text-slate-400'">ตัวพิมพ์ใหญ่และเล็ก</p>
                                <p :class="checks.number ? 'text-emerald-600' : 'text-slate-400'">ตัวเลข</p>
                                <p :class="checks.symbol ? 'text-emerald-600' : 'text-slate-400'">สัญลักษณ์</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                เปลี่ยนรหัสผ่าน
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
