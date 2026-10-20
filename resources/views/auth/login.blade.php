<x-guest-layout>
    <div class="min-h-screen bg-slate-100 lg:grid lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-slate-900 lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
            <div class="absolute inset-0">
                <div class="absolute -left-28 -top-28 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>
                <div class="absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-violet-500/20 blur-3xl"></div>
                <div class="absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 24px 24px;"></div>
            </div>

            <a href="/" class="relative flex items-center gap-3 text-white">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500 text-xl font-bold shadow-lg shadow-indigo-500/30">V</span>
                <span>
                    <span class="block text-xl font-bold leading-tight">VertexPL</span>
                    <span class="block text-xs tracking-wide text-slate-400">Vertex PHP Laravel</span>
                </span>
            </a>

            <div class="relative max-w-xl">
                <p class="mb-5 inline-flex rounded-full border border-indigo-400/20 bg-indigo-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-indigo-300">
                    Internal Application Platform
                </p>
                <h1 class="text-4xl font-bold leading-tight text-white xl:text-5xl">
                    ระบบกลางสำหรับงานภายในองค์กร
                </h1>
                <p class="mt-5 max-w-lg text-base leading-relaxed text-slate-300">
                    เข้าถึงเครื่องมือและข้อมูลที่จำเป็นจากพื้นที่เดียว พร้อมระบบสิทธิ์ที่ควบคุมการใช้งานตามบทบาทของคุณ
                </p>

                <div class="mt-9 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-300">
                            <i class="ph ph-shield-check h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-white">Role-based access</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-400">แสดงข้อมูลและเมนูตามสิทธิ์ที่ได้รับ</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-500/20 text-violet-300">
                            <i class="ph ph-clock h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-white">Activity tracking</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-400">รองรับการตรวจสอบการเข้าใช้งานระบบ</p>
                    </div>
                </div>
            </div>

            <p class="relative text-xs text-slate-500">
                &copy; {{ date('Y') }} VertexPL. For authorized personnel only.
            </p>
        </section>

        <main class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
            <div class="w-full max-w-md">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white shadow-lg shadow-indigo-500/20">V</span>
                    <div>
                        <p class="font-bold leading-tight text-slate-900">VertexPL</p>
                        <p class="text-xs text-slate-500">Internal Application Platform</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
                    <div>
                        <p class="text-sm font-semibold text-indigo-600">ยินดีต้อนรับกลับ</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900">เข้าสู่ระบบ</h2>
                        <p class="mt-2 text-sm text-slate-500">กรอกบัญชีที่ได้รับจากผู้ดูแลระบบ</p>
                    </div>

                    <x-auth-session-status class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700" :status="session('status')" />

                    @if ($errors->any())
                        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
                            <div class="flex gap-3">
                                <i class="ph ph-warning mt-0.5 h-5 w-5 shrink-0 text-xl leading-none text-red-500" aria-hidden="true"></i>
                                <div>
                                    <p class="text-sm font-semibold text-red-800">ไม่สามารถเข้าสู่ระบบได้</p>
                                    <ul class="mt-1 list-inside list-disc text-xs text-red-700">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5" x-data="{ showPassword: false }">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">อีเมล</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <i class="ph ph-envelope h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="name@example.com"
                                    class="block w-full rounded-lg border-slate-300 py-3 pl-11 pr-4 text-sm text-slate-900 placeholder-slate-400 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label for="password" class="block text-sm font-semibold text-slate-700">รหัสผ่าน</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-indigo-600 transition hover:text-indigo-800">
                                        ลืมรหัสผ่าน?
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <i class="ph ph-lock-key h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="กรอกรหัสผ่าน"
                                    class="block w-full rounded-lg border-slate-300 py-3 pl-11 pr-12 text-sm text-slate-900 placeholder-slate-400 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                                    @click="showPassword = !showPassword"
                                    :aria-label="showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'"
                                >
                                    <i x-show="!showPassword" class="ph ph-eye h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                                    <i x-show="showPassword" x-cloak class="ph ph-eye-slash h-5 w-5 text-xl leading-none" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <label for="remember" class="flex cursor-pointer items-center gap-2.5">
                            <input
                                id="remember"
                                name="remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span class="text-sm text-slate-600">จดจำการเข้าสู่ระบบ</span>
                        </label>

                        <button
                            type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-indigo-800"
                        >
                            เข้าสู่ระบบ
                            <i class="ph ph-arrow-right h-4 w-4 text-base leading-none" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs text-slate-500">
                    หากไม่สามารถเข้าสู่ระบบได้ กรุณาติดต่อผู้ดูแลระบบ
                </p>
            </div>
        </main>
    </div>
</x-guest-layout>
