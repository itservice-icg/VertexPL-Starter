<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-xl font-bold text-slate-900">NCR My Tasks</h1><p class="mt-1 text-xs text-slate-500">รายการในสิทธิ์ของคุณ เรียงตามการอัปเดตล่าสุด</p></div>
            <a href="{{ route('ncr.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">ดู NCR ทั้งหมด</a>
        </div>
    </x-slot>

    <div class="ncr-page w-full space-y-2">
            <section class="ncr-table-shell" aria-labelledby="task-list-heading">
                <div class="ncr-table-shell__header">
                    <div>
                        <h2 id="task-list-heading" class="font-bold text-slate-950">งานที่ต้องดำเนินการ</h2>
                        <p class="mt-1 text-xs text-slate-500">เริ่มจากงาน “เร่งด่วน” หรือ “เกินกำหนด” · เปิด NCR เพื่อดูผู้รับผิดชอบ บทบาท และ Action ถัดไป</p>
                    </div>
                    <span class="text-sm text-slate-500">{{ $reports->total() }} รายการ</span>
                </div>

                <div class="ncr-table-shell__body">
                    <table class="ncr-mobile-cards w-full min-w-[980px] divide-y text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left">ลำดับความสำคัญ</th>
                                <th class="px-4 py-3 text-left">NCR / สถานะ</th>
                                <th class="px-4 py-3 text-left">งานถัดไป / บทบาท</th>
                                <th class="px-4 py-3 text-left">ผู้รับผิดชอบ</th>
                                <th class="px-4 py-3 text-left">กำหนดเสร็จ NCR</th>
                                <th class="px-4 py-3 text-right">เปิด</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($reports as $report)
                                @php
                                    $isTaskOwner = (int) $report->current_owner_user_id === (int) auth()->id();
                                    $isOverdue = $report->is_overdue;
                                    $taskPriority = $isOverdue ? 'เร่งด่วน · เกินกำหนด' : 'รอตามลำดับ Workflow';
                                    $taskPriorityClass = $isOverdue ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700';
                                    $taskRole = match ($report->current_status) {
                                        'DRAFT', 'RETURNED_FOR_CORRECTION' => 'ผู้รายงาน / เจ้าของ NCR',
                                        'INITIAL_REVIEW' => 'ผู้ตรวจสอบ Initial Review',
                                        'PENDING_MANAGER_APPROVAL' => 'ผู้จัดการผู้มีอำนาจอนุมัติ',
                                        'RETURNED_FOR_DISPOSITION_REVISION' => 'ผู้จัดทำ / ทบทวน Disposition',
                                        'PENDING_DESTRUCTION_APPROVAL' => 'ผู้มีอำนาจอนุมัติการทำลาย',
                                        'DISPOSITION_APPROVED', 'ACTION_IN_PROGRESS', 'RETURNED_FOR_REWORK' => 'หน่วยงานผู้รับผิดชอบ Corrective Action',
                                        'PENDING_ACTION_VERIFICATION' => 'ผู้ตรวจสอบผลการแก้ไข',
                                        'QA_VERIFIED' => 'QA ผู้ติดตามผล',
                                        default => 'ผู้รับผิดชอบตาม Workflow',
                                    };
                                    $taskAction = match ($report->current_status) {
                                        'RETURNED_FOR_CORRECTION' => 'แก้ไขข้อมูลและส่งตรวจสอบอีกครั้ง',
                                        'INITIAL_REVIEW' => 'บันทึกผล Initial Review และกำหนด Disposition',
                                        'PENDING_MANAGER_APPROVAL' => 'พิจารณาอนุมัติหรือส่งกลับแก้ไข',
                                        'RETURNED_FOR_DISPOSITION_REVISION' => 'ทบทวนและเสนอ Disposition ใหม่',
                                        'PENDING_DESTRUCTION_APPROVAL' => 'พิจารณาการทำลาย (SCRAP)',
                                        'DISPOSITION_APPROVED', 'ACTION_IN_PROGRESS' => 'ดำเนินการ Corrective Action',
                                        'RETURNED_FOR_REWORK' => 'เริ่ม Rework และส่งตรวจรอบใหม่',
                                        'PENDING_ACTION_VERIFICATION' => 'ตรวจสอบผลการดำเนินการ',
                                        'QA_VERIFIED' => 'บันทึกผลติดตามประสิทธิผล',
                                        default => 'ตรวจสอบรายละเอียด NCR',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top" data-label="ลำดับความสำคัญ">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $taskPriorityClass }}">{{ $taskPriority }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top" data-label="NCR / สถานะ">
                                        <a class="font-semibold text-indigo-600 hover:text-indigo-700" href="{{ route('ncr.show', $report) }}">{{ $report->ncr_no }}</a>
                                        <div class="mt-1 text-xs text-slate-600" title="{{ $report->current_status }}">{{ config('ncr.status_labels.'.$report->current_status, $report->current_status) }}</div>
                                    </td>
                                    <td class="px-4 py-3 align-top" data-label="งานถัดไป / บทบาท">
                                        <div class="font-semibold text-slate-900">{{ $taskAction }}</div>
                                        <div class="mt-1 text-xs text-slate-500">บทบาท: {{ $taskRole }}</div>
                                    </td>
                                    <td class="px-4 py-3 align-top" data-label="ผู้รับผิดชอบ">
                                        <div class="font-medium text-slate-800">{{ $isTaskOwner ? 'คุณ' : 'ผู้รับผิดชอบตาม Workflow' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $taskRole }}</div>
                                    </td>
                                    <td class="px-4 py-3 align-top" data-label="กำหนดเสร็จ NCR">
                                        <span class="{{ $isOverdue ? 'font-bold text-red-600' : 'text-slate-700' }}">{{ optional($report->due_date)->format('d/m/Y') ?: 'ยังไม่กำหนด' }}</span>
                                        @if($isOverdue)<div class="mt-1 text-xs font-semibold text-red-600">เกินกำหนด</div>@endif
                                    </td>
                                    <td class="px-4 py-3 text-right align-top" data-label="เปิด">
                                        <a href="{{ route('ncr.show', $report) }}" class="inline-flex min-h-[40px] items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">ดำเนินการ</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-10 text-center text-slate-500">ไม่มีงานที่ต้องดำเนินการ</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $reports->links() }}</div>
            </section>
    </div>
</x-app-layout>
