<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-indigo-600">ภาพรวมระบบ</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Dashboard</h1>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6">
        <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white shadow-lg">
            <p class="text-sm text-indigo-100">ยินดีต้อนรับกลับ</p>
            <h2 class="mt-1 text-2xl font-bold">{{ Auth::user()->name }}</h2>
            <p class="mt-2 max-w-2xl text-sm text-indigo-100">
                คุณเข้าสู่ระบบ VertexPL เรียบร้อยแล้ว เมนูด้านข้างจะแสดงตามสิทธิ์ที่ได้รับ
            </p>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">สถานะบัญชี</p>
                <p class="mt-2 text-lg font-bold text-emerald-600">ใช้งานอยู่</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">บทบาท</p>
                <p class="mt-2 text-lg font-bold text-slate-800">{{ Auth::user()->getRoleNames()->join(', ') ?: 'ยังไม่ได้กำหนด' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
                <p class="text-sm font-medium text-slate-500">เข้าสู่ระบบล่าสุด</p>
                <p class="mt-2 text-lg font-bold text-slate-800">{{ Auth::user()->last_login_at ? Auth::user()->last_login_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') : '-' }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
