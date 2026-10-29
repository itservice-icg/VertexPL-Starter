<x-guest-layout>
    <main class="min-h-screen bg-slate-100 px-4 py-6">
        <div class="mx-auto max-w-6xl">
            <h1 class="text-2xl font-bold text-slate-900">VertexPL — Dev Login</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">เลือกบัญชีเพื่อเข้าสู่ระบบทดสอบได้ทันที โดยไม่ต้องกรอกรหัสผ่าน หน้านี้ใช้ได้เฉพาะ environment local และ testing</p>

            <x-auth-session-status class="mt-6 text-sm text-emerald-700" :status="session('status')" />
            <x-auth-validation-errors class="mt-6" :errors="$errors" />

            <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="dev-login-accounts-heading">
                <header class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <h2 id="dev-login-accounts-heading" class="text-base font-semibold text-slate-900">บัญชีทดสอบ</h2>
                    <p class="text-sm text-slate-500">{{ number_format($userCount) }} บัญชี · เลือกตามหน่วยงาน</p>
                </header>
                @forelse ($usersByDepartment as $departmentName => $departmentUsers)
                    <section aria-label="บัญชีทดสอบหน่วยงาน {{ $departmentName }}" class="border-b border-slate-200 last:border-b-0">
                        <header class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-3 sm:px-5">
                            <h3 class="text-sm font-bold text-slate-800">{{ $departmentName }}</h3>
                            <span class="shrink-0 text-xs font-medium text-slate-500">{{ number_format($departmentUsers->count()) }} บัญชี</span>
                        </header>
                        @foreach ($departmentUsers as $user)
                            <form method="POST" action="{{ route('dev.store') }}" class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-5">
                                @csrf
                                <div class="grid min-w-0 grid-cols-2 gap-2 text-xs sm:items-center sm:gap-x-4 sm:gap-y-2 md:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)_minmax(0,1fr)]">
                                    <div class="col-span-2 min-w-0 md:col-span-1">
                                        <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                        <p class="mt-1 flex min-w-0 gap-1 text-xs"><span class="shrink-0 font-semibold text-slate-600">บัญชี</span><span class="break-all font-mono text-slate-700">{{ $user->email }}</span></p>
                                    </div>
                                    <dl class="grid min-w-0 gap-1">
                                        <dt class="font-semibold text-slate-600">Role ระบบ</dt>
                                        <dd class="flex flex-wrap gap-1.5">
                                            @forelse ($user->roles as $role)
                                                <span class="inline-flex rounded-md bg-indigo-50 px-2 py-1 font-medium text-indigo-700">{{ $role->name }}</span>
                                            @empty
                                                <span class="text-slate-500">ไม่มีบทบาท</span>
                                            @endforelse
                                        </dd>
                                    </dl>
                                    <dl class="grid min-w-0 gap-1">
                                        <dt class="font-semibold text-slate-600">ตำแหน่งในหน่วยงาน</dt>
                                        <dd class="text-slate-700">{{ optional($user->departmentRole)->name_th ?: '—' }}</dd>
                                    </dl>
                                </div>
                                <button type="submit" name="user_id" value="{{ $user->id }}"
                                    aria-label="เข้าสู่ระบบด้วยบัญชี {{ $user->email }}"
                                    class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                                    เข้าใช้บัญชีนี้
                                </button>
                            </form>
                        @endforeach
                    </section>
                @empty
                    <div class="px-4 py-5 sm:px-5">
                        <p class="font-semibold text-slate-900">ยังไม่มีบัญชีทดสอบที่ active</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">สร้างบัญชีด้วย <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">NcrMockupSeeder</code> หรือ <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">NcrReporterUsersSeeder</code> ตามขั้นตอนใน README.md แล้วโหลดหน้านี้ใหม่</p>
                    </div>
                @endforelse
            </section>

            <a href="{{ route('login') }}" class="mt-5 inline-flex min-h-11 items-center text-sm font-semibold text-indigo-600 underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">ไปหน้าเข้าสู่ระบบปกติ</a>
        </div>
    </main>
</x-guest-layout>
