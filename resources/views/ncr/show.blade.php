<x-app-layout>
    @php
    $fmDispositionLabels = [
    'ACCEPT_AS_IS' => 'เสนอขอรับใช้ตามสภาพ',
    'HOLD_STATUS' => 'เสนอติดสถานะ',
    'SEND_TO_RD' => 'ส่งให้ RD',
    'REWORK' => 'REWORK / ให้แก้ไขงาน',
    'SUPPLIER_COORDINATION' => 'ส่งให้จัดซื้อ-ประสานงานผู้ผลิต',
    'CUSTOMER_COORDINATION' => 'ส่งฝ่ายขาย-ประสานงานลูกค้า',
    'CONDITIONAL_ACCEPTANCE' => 'เสนอขอรับภายใต้เงื่อนไข',
    'RETURN_TO_SUPPLIER' => 'RETURN / ส่งคืน / แลกเปลี่ยน / ลดหนี้ / ชดเชย',
    'SCRAP' => 'SCRAP / เสนอทำลาย',
    'RECALL' => 'RECALL',
    'OTHER' => 'OTHERS / อื่น ๆ',
    ];
    $dispositionOrder = array_flip([
        'ACCEPT_AS_IS',
        'HOLD_STATUS',
        'SEND_TO_RD',
        'REWORK',
        'SUPPLIER_COORDINATION',
        'CUSTOMER_COORDINATION',
        'CONDITIONAL_ACCEPTANCE',
        'RETURN_TO_SUPPLIER',
        'SCRAP',
        'RECALL',
        'OTHER',
    ]);
    $dispositionTypes = $dispositionTypes->sortBy(
        fn ($type) => sprintf('%02d-%s', $dispositionOrder[$type->code] ?? 99, $type->code)
    )->values();

    $flowSteps = [
    ['status' => 'DRAFT', 'label' => config('ncr.status_labels.DRAFT')],
    ['status' => 'INITIAL_REVIEW', 'label' => config('ncr.status_labels.INITIAL_REVIEW')],
    ['status' => 'RETURNED_FOR_CORRECTION', 'label' => config('ncr.status_labels.RETURNED_FOR_CORRECTION')],
    ['status' => 'PENDING_MANAGER_APPROVAL', 'label' => config('ncr.status_labels.PENDING_MANAGER_APPROVAL')],
    ['status' => 'RETURNED_FOR_DISPOSITION_REVISION', 'label' => config('ncr.status_labels.RETURNED_FOR_DISPOSITION_REVISION')],
    ['status' => 'DISPOSITION_APPROVED', 'label' => config('ncr.status_labels.DISPOSITION_APPROVED')],
    ['status' => 'ACTION_IN_PROGRESS', 'label' => config('ncr.status_labels.ACTION_IN_PROGRESS')],
    ['status' => 'PENDING_ACTION_VERIFICATION', 'label' => config('ncr.status_labels.PENDING_ACTION_VERIFICATION')],
    ['status' => 'RETURNED_FOR_REWORK', 'label' => config('ncr.status_labels.RETURNED_FOR_REWORK')],
    ['status' => 'QA_VERIFIED', 'label' => config('ncr.status_labels.QA_VERIFIED')],
    ['status' => 'CLOSED', 'label' => config('ncr.status_labels.CLOSED')],
    ];
    // Destruction approval is a technical sub-state; the Flow places it in the action branch.
    $trackerStatus = $ncr->current_status === 'PENDING_DESTRUCTION_APPROVAL'
    ? 'ACTION_IN_PROGRESS'
    : $ncr->current_status;
    $currentFlowStep = collect($flowSteps)->search(fn ($step) => $step['status'] === $trackerStatus);
    $currentFlowStep = $currentFlowStep === false ? -1 : $currentFlowStep;
    $viewer = auth()->user();
    $isCurrentOwner = (int) $ncr->current_owner_user_id === (int) $viewer->id;
    $currentDisposition = $ncr->dispositions->first();
    $currentHistory = $ncr->statusHistory->sortByDesc('id')->first();
    $currentCycleActions = $ncr->correctiveActions->where('corrective_action_cycle_id', $ncr->current_corrective_action_cycle_id);
    $actionPlanReturnApproval = $ncr->dispositions
        ->flatMap(fn ($disposition) => $disposition->approvals)
        ->filter(fn ($approval) => $approval->approval_stage === 'CORRECTIVE_ACTION_APPROVAL'
            && (int) $approval->corrective_action_cycle_id === (int) $ncr->current_corrective_action_cycle_id
            && $approval->decision === 'REJECTED')
        ->sortByDesc('id')
        ->first();
    $actionPlanReturnReason = $ncr->current_status === 'DISPOSITION_APPROVED'
        && $currentCycleActions->contains(fn ($action) => $action->action_status === 'REJECTED')
        ? trim((string) $actionPlanReturnApproval?->decision_note)
        : '';
    $actionsAwaitingApproval = $currentCycleActions->isNotEmpty()
        && $currentCycleActions->every(fn ($action) => $action->action_status === 'PENDING_APPROVAL');
    $returnReason = in_array($ncr->current_status, [
        'RETURNED_FOR_CORRECTION',
        'RETURNED_FOR_DISPOSITION_REVISION',
        'RETURNED_FOR_REWORK',
    ], true) ? trim((string) $currentHistory?->action_note) : '';
    $latestInitialReview = $ncr->initialReviews->sortByDesc('review_round')->first();
    $reviewResultLabels = ['COMPLETE' => 'ข้อมูลครบถ้วน', 'INCOMPLETE' => 'ข้อมูลไม่ครบ — ส่งกลับแก้ไข'];
    $dispositionStatusLabels = ['DRAFT' => 'ฉบับร่าง', 'PENDING' => 'รอพิจารณา', 'APPROVED' => 'อนุมัติแล้ว', 'REJECTED' => 'ส่งกลับแก้ไข'];
    $ownerDepartment = $currentDisposition?->responsibleDepartment ?: $ncr->reporterDepartment;
    $isCorrectiveActionProposer = $viewer->can('ncr.action.manage')
        && ($viewer->hasRole('Super Admin') || (int) $viewer->department_id === (int) $currentDisposition?->responsible_department_id);
    $isCorrectiveActionExecutor = $viewer->can('ncr.action.manage')
        && ($viewer->hasRole('Super Admin') || (int) $viewer->department_id === (int) $currentDisposition?->responsible_department_id);
    $currentAction = 'ติดตามข้อมูล NCR';
    $currentActionTone = 'indigo';
    $currentActionRole = 'ผู้รับผิดชอบตามขั้นตอน';
    $nextAction = 'ตรวจสอบรายละเอียดและประวัติการดำเนินงาน';
    $nextActionHref = null;

    if (in_array($ncr->current_status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true) && $isCurrentOwner && $viewer->can('ncr.update')) {
    $currentActionRole = 'ผู้รายงาน / เจ้าของ NCR';
    $currentAction = 'แก้ไขข้อมูล NCR และส่งตรวจสอบอีกครั้ง';
    $nextAction = 'เปิดแบบฟอร์มแก้ไข แล้วกดส่งตรวจสอบ';
    } elseif ($ncr->current_status === 'INITIAL_REVIEW' && ! $ncr->dispositions->where('status', 'DRAFT')->count()) {
    $currentActionRole = 'ผู้ตรวจสอบ Initial Review';
    $currentAction = $viewer->can('ncr.review') ? 'ตรวจสอบและบันทึกผล Initial Review' : 'รอผู้ตรวจสอบบันทึกผล Initial Review';
    $nextAction = $viewer->can('ncr.review') ? 'บันทึกผลตรวจสอบและข้อเสนอแนะการจัดการเบื้องต้น' : 'ผู้ตรวจสอบบันทึกผลและข้อเสนอแนะการจัดการเบื้องต้น';
    } elseif ($ncr->current_status === 'INITIAL_REVIEW' && $ncr->dispositions->where('status', 'DRAFT')->count() && $viewer->can('ncr.disposition.manage')) {
    $currentActionRole = 'ผู้จัดทำ / ทบทวน Disposition';
    $currentAction = 'ส่งข้อเสนอแนะการจัดการให้ผู้มีอำนาจพิจารณา';
    $nextAction = 'ตรวจสอบ Disposition แล้วกดส่งให้ผู้จัดการอนุมัติ';
    $currentActionTone = 'amber';
    } elseif ($ncr->current_status === 'PENDING_MANAGER_APPROVAL' && $viewer->can('ncr.disposition.approve')) {
    $currentActionRole = 'ผู้มีสิทธิ์อนุมัติ Disposition';
    $currentAction = 'พิจารณาอนุมัติ Disposition';
    $nextAction = 'อนุมัติหรือส่งกลับแก้ไขพร้อมเหตุผล';
    $currentActionTone = 'amber';
    } elseif ($ncr->current_status === 'RETURNED_FOR_DISPOSITION_REVISION' && $viewer->can('ncr.disposition.manage')) {
    $currentActionRole = 'ผู้จัดทำ / ทบทวน Disposition';
    $currentAction = 'ทบทวนและเสนอ Disposition ใหม่';
    $nextAction = 'สร้าง Revision แล้วส่งให้ผู้มีอำนาจพิจารณา';
    $currentActionTone = 'amber';
    } elseif ($ncr->current_status === 'PENDING_DESTRUCTION_APPROVAL') {
    $currentActionRole = 'ผู้มีสิทธิ์อนุมัติการทำลาย';
    $currentAction = 'พิจารณาการทำลาย (SCRAP)';
    $nextAction = 'บันทึกผลอนุมัติหรือไม่อนุมัติการทำลาย';
    $currentActionTone = 'red';
    } elseif ($ncr->current_status === 'DISPOSITION_APPROVED' && $actionsAwaitingApproval) {
    $currentActionRole = 'หัวหน้าแผนกผู้รับผิดชอบ';
    $currentAction = $viewer->can('ncr.corrective-action.approve', $ncr) ? 'พิจารณาแผน Corrective Action' : 'รออนุมัติแผน Corrective Action';
    $nextAction = $viewer->can('ncr.corrective-action.approve', $ncr) ? 'อนุมัติแผนเพื่อเริ่มงาน หรือส่งกลับพร้อมเหตุผลให้แก้ไข' : 'หัวหน้าแผนกผู้รับผิดชอบพิจารณาแผน; หากอนุมัติ หน่วยงานจะเริ่มดำเนินการ';
    } elseif ($ncr->current_status === 'DISPOSITION_APPROVED'
        && $isCorrectiveActionProposer
        && $currentCycleActions->contains(fn ($action) => $action->action_status === 'REJECTED')) {
    $currentActionRole = 'หน่วยงานรับผิดชอบ Corrective Action';
    $currentAction = 'แก้ไข Corrective Action ตามเหตุผลที่ส่งกลับ';
    $nextAction = 'เปิด Action ที่ถูกส่งกลับ แก้ไขแผน แล้วส่งอนุมัติใหม่';
    $currentActionTone = 'amber';
    } elseif ($ncr->current_status === 'DISPOSITION_APPROVED') {
    $currentActionRole = $isCorrectiveActionProposer ? 'หน่วยงานรับผิดชอบ' : 'หน่วยงานรับผิดชอบ Corrective Action';
    $currentAction = $isCorrectiveActionProposer ? 'เสนอหรือแก้ไข Corrective Action และระยะเวลาดำเนินการ' : 'รอหน่วยงานรับผิดชอบจัดทำ Corrective Action';
    $nextAction = $isCorrectiveActionProposer ? 'ส่งให้หัวหน้าแผนกผู้รับผิดชอบพิจารณาอนุมัติ' : 'หน่วยงานรับผิดชอบจัดทำ Corrective Action และระบุผู้รับผิดชอบ';
    } elseif (in_array($ncr->current_status, ['ACTION_IN_PROGRESS', 'RETURNED_FOR_REWORK'], true)) {
    $currentActionRole = 'หน่วยงานผู้รับผิดชอบ Corrective Action';
    $currentAction = $ncr->current_status === 'RETURNED_FOR_REWORK' ? 'เริ่ม Rework Corrective Action' : 'ดำเนินการ Corrective Action';
    $nextAction = $ncr->current_status === 'RETURNED_FOR_REWORK'
        ? 'อ่านเหตุผลที่ส่งกลับ กดเริ่ม Rework แล้วแก้แผน Action รอบใหม่'
        : 'อัปเดตผลและหลักฐานของ Action ให้ครบ แล้วส่งตรวจยืนยัน';
    } elseif ($ncr->current_status === 'PENDING_ACTION_VERIFICATION' && $viewer->can('ncr.action.verify')) {
    $currentActionRole = 'ผู้มีสิทธิ์ตรวจยืนยัน Corrective Action';
    $currentAction = 'ตรวจยืนยันผลและหลักฐาน Corrective Action';
    $nextAction = 'บันทึกผลผ่านเพื่อส่งต่อ QA หรือไม่ผ่านพร้อมระบุสิ่งที่ต้องแก้';
    $currentActionTone = 'emerald';
    } elseif ($ncr->current_status === 'QA_VERIFIED' && $viewer->can('ncr.qa.followup')) {
    $currentActionRole = 'QA ผู้ติดตามประสิทธิผล';
    $currentAction = 'ประเมินว่า Corrective Action ได้ผลหรือไม่';
    $nextAction = 'มีประสิทธิผล: ปิด NCR · ยังไม่ได้ผล: ส่งกลับ Rework · ต้องติดตามต่อ: นัดวันติดตาม';
    $currentActionTone = 'emerald';
    } elseif ($ncr->current_status === 'CLOSED') {
    $currentAction = 'ไม่มีงานที่ต้องดำเนินการ';
    $nextAction = 'ดูประวัติและเอกสารประกอบ';
    $currentActionTone = 'slate';
    } else {
    $ownerRolesByStatus = [
        'DRAFT' => 'ผู้รายงาน',
        'RETURNED_FOR_CORRECTION' => 'ผู้รายงาน',
        'INITIAL_REVIEW' => 'ผู้ตรวจสอบ Initial Review',
        'PENDING_MANAGER_APPROVAL' => 'ผู้มีสิทธิ์อนุมัติ Disposition',
        'RETURNED_FOR_DISPOSITION_REVISION' => 'ผู้จัดทำ Disposition',
        'PENDING_DESTRUCTION_APPROVAL' => 'ผู้มีสิทธิ์อนุมัติการทำลาย',
        'DISPOSITION_APPROVED' => $actionsAwaitingApproval ? 'หัวหน้าแผนกผู้รับผิดชอบ' : 'หน่วยงานรับผิดชอบ Corrective Action',
        'ACTION_IN_PROGRESS' => 'หน่วยงานผู้รับผิดชอบ Corrective Action',
        'PENDING_ACTION_VERIFICATION' => 'ผู้มีสิทธิ์ตรวจยืนยัน Corrective Action',
        'RETURNED_FOR_REWORK' => 'หน่วยงานผู้รับผิดชอบ Corrective Action',
        'QA_VERIFIED' => 'QA ผู้ติดตามประสิทธิผล',
    ];
    $currentActionRole = $ownerRolesByStatus[$ncr->current_status] ?? 'ผู้รับผิดชอบตามขั้นตอน';
    $currentAction = 'รอผู้รับผิดชอบดำเนินการ';
    $nextAction = 'ขั้นตอนถัดไปจะเริ่มเมื่อผู้รับผิดชอบปัจจุบันบันทึกผล';
    }

    $previousStep = $currentFlowStep > 0 ? $flowSteps[$currentFlowStep - 1] : null;
    $nextStep = $currentFlowStep >= 0 && isset($flowSteps[$currentFlowStep + 1]) ? $flowSteps[$currentFlowStep + 1] : null;
    $currentOwnerLabel = $ncr->current_status === 'CLOSED'
        ? 'ไม่มีงานค้าง'
        : ($actionsAwaitingApproval
            ? $currentActionRole
            : ($isCurrentOwner
            ? 'คุณ'
            : (optional($ncr->currentOwner)->name
                ?: (optional($ncr->currentOwnerDepartment)->name_th ?: $currentActionRole))));
    if ($ncr->current_status === 'INITIAL_REVIEW' && $viewer->can('ncr.review')) {
        $nextActionHref = $ncr->dispositions->where('status', 'DRAFT')->count()
            ? ($viewer->can('ncr.disposition.manage') ? '#disposition-submit' : null)
            : '#initial-review';
    } elseif ($ncr->current_status === 'INITIAL_REVIEW'
        && $ncr->dispositions->where('status', 'DRAFT')->count()
        && $viewer->can('ncr.disposition.manage')) {
        $nextActionHref = '#disposition-submit';
    } elseif ($ncr->current_status === 'PENDING_MANAGER_APPROVAL' && $viewer->can('ncr.disposition.approve')) {
        $nextActionHref = '#disposition-decision';
    } elseif ($ncr->current_status === 'RETURNED_FOR_DISPOSITION_REVISION' && $viewer->can('ncr.disposition.manage')) {
        $nextActionHref = '#disposition-revision';
    } elseif ($ncr->current_status === 'PENDING_DESTRUCTION_APPROVAL' && $viewer->can('ncr.destruction.approve')) {
        $nextActionHref = '#destruction-decision';
    } elseif ($ncr->current_status === 'DISPOSITION_APPROVED'
        && (($actionsAwaitingApproval && $viewer->can('ncr.corrective-action.approve', $ncr)) || (! $actionsAwaitingApproval && $isCorrectiveActionProposer))) {
        $nextActionHref = '#corrective-action';
    } elseif ($ncr->current_status === 'ACTION_IN_PROGRESS' && $isCorrectiveActionExecutor) {
        $nextActionHref = '#corrective-action';
    } elseif ($ncr->current_status === 'PENDING_ACTION_VERIFICATION' && $viewer->can('ncr.action.verify')) {
        $nextActionHref = '#action-verification';
    } elseif ($ncr->current_status === 'RETURNED_FOR_REWORK' && $isCorrectiveActionExecutor) {
        $nextActionHref = '#start-corrective-action-rework';
    } elseif ($ncr->current_status === 'QA_VERIFIED' && $viewer->can('ncr.qa.followup')) {
        $nextActionHref = '#qa-followup-form';
    } elseif ($ncr->current_status === 'CLOSED') {
        $nextActionHref = '#ncr-details';
    }
    if (in_array($ncr->current_status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true)
        && $isCurrentOwner
        && $viewer->can('ncr.update')) {
        $nextActionHref = route('ncr.edit', $ncr);
    }

    // Progressive disclosure: show completed stages and the current stage, but not future stages.
    $showStageTwo = $ncr->current_status !== 'DRAFT';
    $showStageThree = in_array($ncr->current_status, [
        'DISPOSITION_APPROVED',
        'PENDING_DESTRUCTION_APPROVAL',
        'ACTION_IN_PROGRESS',
        'PENDING_ACTION_VERIFICATION',
        'RETURNED_FOR_REWORK',
        'QA_VERIFIED',
        'CLOSED',
    ], true);
    $showStageFour = in_array($ncr->current_status, [
        'PENDING_ACTION_VERIFICATION',
        'RETURNED_FOR_REWORK',
        'QA_VERIFIED',
        'CLOSED',
    ], true);
    $showStageFive = in_array($ncr->current_status, ['QA_VERIFIED', 'CLOSED'], true);
    $showDispositionHistory = $ncr->dispositions->isNotEmpty() || $ncr->current_status === 'CLOSED';
    @endphp
    <div class="ncr-page ncr-detail flex w-full flex-col space-y-6 px-4 py-5 sm:px-6 sm:py-7">
        <div class="ncr-flow-header ncr-page-header">
            <div>
                <p class="ncr-eyebrow">Non-Conformance Report · Workspace</p>
                <h1 class="ncr-page-title mt-2">{{ $ncr->ncr_no }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span class="ncr-status-chip" title="{{ $ncr->current_status }}">{{ config('ncr.status_labels.'.$ncr->current_status, $ncr->current_status) }}</span>
                    <span>Version {{ $ncr->version_no }}</span>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ config('ncr.form.code') }} · Rev. {{ config('ncr.form.revision') }} · {{ \App\Support\ThaiDateFormatter::formatDate(config('ncr.form.revision_date')) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('ncr.update')
                @if(in_array($ncr->current_status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true))
                <a href="{{ route('ncr.edit', $ncr) }}" class="ncr-action-link ncr-action-link--secondary">แก้ไขข้อมูล</a>
                @endif
                @endcan
                @can('ncr.submit')
                @if(in_array($ncr->current_status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true))
                <form method="POST" action="{{ route('ncr.submit', $ncr) }}">@csrf<input type="hidden" name="version_no" value="{{ $ncr->version_no }}"><button class="ncr-action-link">ส่งตรวจ</button></form>
                @endif
                @endcan
            </div>
        </div>
        <section class="ncr-workflow-overview {{ $ncr->is_overdue ? 'is-overdue' : '' }}" aria-labelledby="workflow-progress-heading">
            @if($ncr->current_status !== 'CLOSED')
            <div class="ncr-current-action">
                <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="ncr-current-action__label">Current Action · งานที่ต้องทำตอนนี้</p>
                    <h2 id="current-action-heading" class="ncr-current-action__title mt-1">{{ $currentAction }}</h2>
                </div>
                @if($ncr->is_overdue)
                <span class="ncr-overdue-badge rounded-full px-3 py-1 text-xs font-bold">เกินกำหนด</span>
                @endif
                </div>
                <div class="ncr-current-action__grid">
                <div>
                    <p class="ncr-data-label">ผู้รับผิดชอบงานปัจจุบัน</p>
                    <p class="ncr-data-value mt-1">{{ $currentOwnerLabel }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $currentActionRole }}</p>
                </div>
                <div>
                    <p class="ncr-data-label">กำหนดเสร็จ NCR</p>
                    <p class="ncr-data-value mt-1 {{ $ncr->is_overdue ? 'ncr-danger-text' : '' }}">{{ \App\Support\ThaiDateFormatter::formatDate($ncr->due_date) ?: 'ยังไม่กำหนด' }}</p>
                </div>
                <div>
                    <p class="ncr-data-label">ขั้นตอนถัดไป</p>
                    @if($nextActionHref)
                    <a href="{{ $nextActionHref }}" class="ncr-data-value mt-1 inline-block underline decoration-indigo-400 underline-offset-2 hover:decoration-indigo-700">{{ $nextAction }}</a>
                    @else
                    <p class="ncr-data-value mt-1">{{ $nextAction }}</p>
                    @endif
                </div>
                </div>
                @if($returnReason !== '')
                <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950"><strong>เหตุผลที่ส่งกลับ:</strong> {{ $returnReason }}</p>
                @elseif($actionPlanReturnReason !== '')
                <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950"><strong>เหตุผลที่ส่งกลับแก้ไข Corrective Action:</strong> {{ $actionPlanReturnReason }}</p>
                @endif
            </div>
            @endif
            <div class="ncr-stepper ncr-progress-static" aria-labelledby="workflow-progress-heading">
            <div class="ncr-stepper__bar">
                <div>
                    <h2 id="workflow-progress-heading" class="ncr-section-title">เส้นทาง Workflow</h2>
                </div>
                <span class="ncr-progress-details__status">
                    <small>ขั้นที่ {{ $currentFlowStep >= 0 ? $currentFlowStep + 1 : '-' }} / {{ count($flowSteps) }}</small>
                    @if($ncr->current_status !== 'CLOSED')
                    <strong>{{ config('ncr.status_labels.'.$trackerStatus, $trackerStatus) }}</strong>
                    @elseif($ncr->closure?->closed_at)
                    <small>ปิดเมื่อ {{ \App\Support\ThaiDateFormatter::formatDateTime($ncr->closure->closed_at, config('app.display_timezone')) }}</small>
                    @endif
                </span>
            </div>
            <nav class="ncr-stepper__viewport" aria-label="{{ $ncr->current_status }}">
                <ol class="grid" style="grid-template-columns: repeat({{ count($flowSteps) }}, minmax(102px, 1fr)); min-width: {{ count($flowSteps) * 102 }}px;">
                    @foreach($flowSteps as $index => $step)
                    <li class="relative flex flex-col items-center px-2 text-center">
                        @if(! $loop->last)
                        <span class="absolute left-1/2 top-4 h-0.5 w-full {{ $index < $currentFlowStep ? 'bg-indigo-500' : 'bg-slate-200' }}" aria-hidden="true"></span>
                        @endif
                        <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold {{ $index < $currentFlowStep ? 'bg-indigo-600 text-white' : ($index === $currentFlowStep ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500 ring-offset-2' : 'bg-slate-100 text-slate-500') }}">
                            {{ $index + 1 }}
                        </span>
                        <span class="mt-2 text-xs font-semibold {{ $index === $currentFlowStep ? 'text-indigo-700' : 'text-slate-600' }}">{{ $step['label'] }}</span>
                    </li>
                    @endforeach
                </ol>
            </nav>
            </div>
        </section>
        <div class="ncr-flow-main space-y-6">
            <div id="ncr-details" class="ncr-stage-card ncr-panel ncr-report-stage min-w-0 space-y-4 p-4 sm:p-5">
                <div class="ncr-stage-card__header -mx-4 -mt-4 sm:-mx-5 sm:-mt-5">
                    <div>
                        <p class="ncr-stage-kicker">Stage 01 · Report</p>
                        <h2 class="ncr-section-title mt-1">การแจ้ง / รายละเอียดผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด</h2>
                        <p class="ncr-section-description mt-1">รายละเอียด NCR รายการผลิตภัณฑ์ ปัญหา และข้อมูลที่เกี่ยวข้อง</p>
                    </div>
                </div>
                <dl class="ncr-report-summary" aria-label="ข้อมูลสำคัญของรายงาน">
                    <div><dt>เลขที่ NCR</dt><dd>{{ $ncr->ncr_no }}</dd></div>
                    <div><dt>วันที่ออก</dt><dd>{{ \App\Support\ThaiDateFormatter::formatDate($ncr->report_date) }}</dd></div>
                    <div><dt>ผู้แจ้ง</dt><dd><span class="block">{{ $ncr->reporter->name }}</span><span class="mt-1 block text-xs font-normal text-slate-500">หน่วยงานผู้รายงาน: {{ optional($ncr->reporterDepartment)->name_th ?: '-' }}</span></dd></div>
                    <div><dt>หน่วยงานที่พบปัญหา</dt><dd>{{ $ncr->relatedDepartment?->name_th ?: '-' }}</dd></div>
                    <div><dt>ประเภท</dt><dd>{{ optional($ncr->problemType)->name_th ?: '-' }}</dd></div>
                </dl>
                <div>
                    <div class="ncr-report-section-heading">
                        <h3 class="ncr-section-title">ข้อมูลผลิตภัณฑ์ / รายงาน</h3>
                        <span>{{ $ncr->items->count() }} รายการ</span>
                    </div>
                    <div class="ncr-report-table-shell overflow-x-auto rounded-lg border">
                        <table class="ncr-mobile-cards min-w-full divide-y text-sm">
                            <caption class="sr-only">รายการผลิตภัณฑ์ที่เกี่ยวข้องกับ NCR</caption>
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left">#</th>
                                    <th scope="col" class="px-3 py-2 text-left">สินค้า</th>
                                    <th scope="col" class="px-3 py-2 text-left">Lot No. / Batch No. / PO No.</th>
                                    <th scope="col" class="px-3 py-2 text-right">จำนวนที่พบ</th>
                                    <th scope="col" class="px-3 py-2 text-right">จากจำนวนทั้งหมด</th>
                                    <th scope="col" class="px-3 py-2 text-right">คิดเป็นร้อยละ (%)</th>
                                    <th scope="col" class="px-3 py-2 text-left">ลักษณะผิดปกติที่พบ / รายละเอียด</th>
                                    <th scope="col" class="px-3 py-2 text-left">รายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">@foreach($ncr->items as $item)<tr>
                                    <td class="px-3 py-2" data-label="#">{{ $item->line_no }}</td>
                                    <td class="px-3 py-2" data-label="สินค้า">
                                        <div class="font-medium">{{ $item->product_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $item->product_code }}</div>
                                        <div class="text-xs text-slate-500">{{ $item->customer_name ?: '-' }}</div>
                                    </td>
                                    <td class="px-3 py-2" data-label="Lot / Batch / PO">{{ $item->lot_no ?: '-' }} / {{ $item->batch_no ?: '-' }} / {{ $item->po_no ?: '-' }}</td>
                                    <td class="px-3 py-2 text-right" data-label="จำนวนที่พบ">{{ $item->defect_quantity }} {{ $item->defect_unit_name ?: $item->unit_name }}</td>
                                    <td class="px-3 py-2 text-right" data-label="จำนวนทั้งหมด">{{ $item->total_quantity }} {{ $item->total_unit_name ?: $item->unit_name }}</td>
                                    <td class="px-3 py-2 text-right" data-label="ร้อยละ (%)">{{ $item->defect_percentage===null?'-':number_format($item->defect_percentage,2).'%' }}</td>
                                    <td class="px-3 py-2" data-label="ลักษณะผิดปกติที่พบ / รายละเอียด">{{ $item->problem_description ?: '-' }}</td>
                                    <td class="px-3 py-2" data-label="รายละเอียด NCR">
                                        <button type="button" class="ncr-item-dialog-trigger" data-ncr-dialog-open="ncr-item-details-{{ $item->id }}" aria-haspopup="dialog">
                                            รายละเอียด NCR ทั้งหมด
                                        </button>
                                        <dialog id="ncr-item-details-{{ $item->id }}" class="ncr-item-dialog" aria-labelledby="ncr-item-details-title-{{ $item->id }}">
                                            <div class="ncr-item-dialog__header">
                                                <div>
                                                    <p class="ncr-stage-kicker">รายละเอียดของรายการ {{ $item->line_no }}</p>
                                                    <h3 id="ncr-item-details-title-{{ $item->id }}" class="ncr-section-title mt-1">{{ $item->product_name ?: 'รายละเอียด NCR ทั้งหมด' }}</h3>
                                                </div>
                                                <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดรายละเอียด">×</button></form>
                                            </div>
                                            <div class="ncr-item-dialog__body">
                                                <section>
                                                    <h4 class="ncr-item-dialog__section-title">ข้อมูล NCR</h4>
                                                    @if($ncr->problem_description)
                                                    <div class="ncr-item-dialog__summary"><strong>สรุปเหตุการณ์ NCR (ภาพรวม)</strong><p>{{ $ncr->problem_description }}</p></div>
                                                    @endif
                                                    <dl class="ncr-item-dialog__grid">
                                                        @foreach(['ขอบเขตแบบฟอร์ม'=>optional($ncr->formScope)->name_th ?: ($ncr->legacy_form_scope_code ?: 'ยังไม่ได้เลือก'),'เลขที่ NCR'=>$ncr->ncr_no,'วันที่ออก'=>\App\Support\ThaiDateFormatter::formatDate($ncr->report_date),'วันที่/เวลาที่พบ'=>\App\Support\ThaiDateFormatter::formatDate($ncr->found_date).($ncr->found_time?' '.$ncr->found_time:''),'ผู้พบ / ผู้แจ้ง'=>optional($ncr->reporter)->name,'หน่วยงานผู้รายงาน'=>optional($ncr->reporterDepartment)->name_th,'หน่วยงานที่พบปัญหา'=>optional($ncr->relatedDepartment)->name_th,'ประเภทที่พบ'=>optional($ncr->problemType)->name_th,'ระดับความรุนแรง'=>optional($ncr->severityLevel)->name_th,'บริษัท'=>$ncr->company_name,'ชื่อสามัญ'=>$ncr->generic_name,'ผู้ผลิต/ผู้ขาย'=>$ncr->supplier_name,'พื้นที่ที่พบ'=>$ncr->found_location] as $label => $value)
                                                            @if(filled($value))<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                                                        @endforeach
                                                    </dl>
                                                </section>
                                                <section>
                                                    <h4 class="ncr-item-dialog__section-title">ข้อมูลผลิตภัณฑ์ / รายงาน — รายการ {{ $item->line_no }}</h4>
                                                    <dl class="ncr-item-dialog__grid">
                                                        @foreach(['รหัสสินค้า'=>$item->product_code,'ชื่อสินค้า'=>$item->product_name,'ชื่อลูกค้า'=>$item->customer_name,'PO No.'=>$item->po_no,'Lot No.'=>$item->lot_no,'Batch No.'=>$item->batch_no,'จำนวนที่พบ'=>trim($item->defect_quantity.' '.($item->defect_unit_name ?: $item->unit_name)),'จากจำนวนทั้งหมด'=>trim($item->total_quantity.' '.($item->total_unit_name ?: $item->unit_name)),'คิดเป็นร้อยละ (%)'=>$item->defect_percentage===null?null:number_format($item->defect_percentage,2).'%','ลักษณะผิดปกติที่พบ / รายละเอียด'=>$item->problem_description] as $label => $value)
                                                            @if(filled($value))<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                                                        @endforeach
                                                    </dl>
                                                </section>
                                            </div>
                                        </dialog>
                                    </td>
                                </tr>@endforeach</tbody>
                        </table>
                    </div>
                </div>
                <div class="ncr-report-attachments">
                    <h3 class="ncr-section-title">ไฟล์แนบ <span>{{ $ncr->attachments->count() }} ไฟล์</span></h3>
                    <div class="ncr-report-attachment-list">@forelse($ncr->attachments as $attachment)<a href="{{ route('ncr.attachments.download', [$ncr, $attachment]) }}"><span aria-hidden="true">↧</span><span>{{ $attachment->original_filename }}</span><small>{{ number_format($attachment->file_size / 1024, 1) }} KB</small></a>@empty<p>ไม่มีไฟล์แนบ</p>@endforelse</div>
                </div>
            </div>
            <section class="space-y-6" aria-label="NCR workflow">
                @if($showStageTwo)
                <div id="ncr-stage-actions" class="ncr-flow-part-two rounded-xl border border-indigo-200 bg-indigo-50/70 px-5 py-5">
                    <p class="ncr-stage-kicker">Stage 02 · Control</p>
                    <h2 class="ncr-section-title mt-1 text-indigo-950">การจัดการกับผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนด</h2>
                    <p class="ncr-section-description mt-1 text-indigo-800">Initial Review และ Disposition</p>
                </div>
                @if($latestInitialReview || $currentDisposition)
                <div data-stage-two-summary class="ncr-stage-card overflow-hidden" aria-labelledby="stage-two-summary-heading">
                    <div class="ncr-stage-card__header">
                        <div>
                            <p class="ncr-stage-kicker">Completed record</p>
                            <h3 id="stage-two-summary-heading" class="ncr-section-title mt-1">ผลการตรวจสอบและข้อเสนอแนะที่บันทึกไว้</h3>
                        </div>
                    </div>
                    <div class="ncr-stage-card__body grid gap-5 lg:grid-cols-2">
                        @if($latestInitialReview)
                        <section aria-labelledby="initial-review-summary-heading">
                            <h4 id="initial-review-summary-heading" class="font-bold text-slate-900">Initial Review รอบที่ {{ $latestInitialReview->review_round }}</h4>
                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="text-slate-500">ผลการตรวจสอบ</dt><dd class="mt-1 font-semibold">{{ $reviewResultLabels[$latestInitialReview->review_result] ?? $latestInitialReview->review_result }}</dd></div>
                                <div><dt class="text-slate-500">ผู้ตรวจสอบ</dt><dd class="mt-1 font-semibold">{{ $latestInitialReview->reviewer?->name ?: '-' }}</dd></div>
                    <div><dt class="text-slate-500">วันที่ตรวจสอบ</dt><dd class="mt-1 font-semibold">{{ \App\Support\ThaiDateFormatter::formatDateTime($latestInitialReview->reviewed_at, config('app.display_timezone')) ?: '-' }}</dd></div>
                                <div class="sm:col-span-2"><dt class="text-slate-500">หมายเหตุ</dt><dd class="mt-1 whitespace-pre-line">{{ $latestInitialReview->completeness_note ?: '-' }}</dd></div>
                            </dl>
                        </section>
                        @endif
                        @if($currentDisposition)
                        <section aria-labelledby="disposition-summary-heading">
                            <h4 id="disposition-summary-heading" class="font-bold text-slate-900">Disposition Revision {{ $currentDisposition->revision_no }}</h4>
                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="text-slate-500">ข้อเสนอแนะ</dt><dd class="mt-1 font-semibold">{{ $fmDispositionLabels[$currentDisposition->type->code] ?? $currentDisposition->type->name_th }}</dd></div>
                                <div><dt class="text-slate-500">สถานะ</dt><dd class="mt-1 font-semibold">{{ $dispositionStatusLabels[$currentDisposition->status] ?? $currentDisposition->status }}</dd></div>
                                <div><dt class="text-slate-500">หน่วยงานรับผิดชอบ</dt><dd class="mt-1 font-semibold">{{ $currentDisposition->responsibleDepartment->code }} - {{ $currentDisposition->responsibleDepartment->name_th }}</dd></div>
                                <div><dt class="text-slate-500">กำหนดเสร็จตามข้อเสนอ Disposition</dt><dd class="mt-1 font-semibold">{{ \App\Support\ThaiDateFormatter::formatDate($currentDisposition->proposed_due_date) ?: 'ยังไม่กำหนด' }}</dd></div>
                                <div class="sm:col-span-2"><dt class="text-slate-500">รายละเอียด</dt><dd class="mt-1 whitespace-pre-line">{{ $currentDisposition->disposition_detail }}</dd></div>
                            </dl>
                        </section>
                        @endif
                    </div>
                </div>
                @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-4 text-sm text-slate-600">
                    ยังไม่มีผลการตรวจสอบหรือข้อเสนอแนะที่บันทึกไว้
                </div>
                @endif
                @can('ncr.review')
                @if($ncr->current_status === 'INITIAL_REVIEW' && ! $ncr->dispositions->where('status', 'DRAFT')->count())

                <form id="initial-review"
                    method="POST"
                    action="{{ route('ncr.initial-review', $ncr) }}"
                    class="ncr-stage-card ncr-initial-review-card ncr-workflow-form xl:col-span-2"
                    aria-labelledby="initial-review-heading"
                    aria-describedby="initial-review-description"
                    x-data="{ reviewResult: '{{ old('review_result', 'COMPLETE') }}' }">
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">

                    <div class="ncr-stage-card__header ncr-initial-review-card__header">
                        <div>
                            <p class="ncr-stage-kicker">Current stage · Initial Review</p>
                            <h2 id="initial-review-heading" class="ncr-section-title mt-1">การตรวจสอบเบื้องต้น (Initial Review)</h2>
                            <p id="initial-review-description" class="ncr-section-description mt-1">ตรวจว่าข้อมูล NCR เพียงพอสำหรับพิจารณาหรือไม่ หากครบ ให้เสนอ Disposition (แนวทางจัดการปัญหา) และหน่วยงานรับผิดชอบเพื่อส่งอนุมัติต่อ</p>
                            <p class="ncr-initial-review-card__legend mt-2">I = รอตรวจสอบ · R = รอแก้ไขงาน · V = รอส่งคืนผู้ขาย · S = รอเสนอทำลาย</p>
                        </div>
                    </div>

                    <div class="ncr-initial-review-card__body space-y-4">
                        <div class="ncr-initial-review-card__review-fields">
                            <div>
                                <fieldset>
                                    <legend class="mb-1 block text-sm font-medium">ผลการตรวจสอบ <span class="text-red-600">*</span></legend>
                                    <input type="hidden" name="review_result" :value="reviewResult">
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus-within:ring-2 focus-within:ring-indigo-500">
                                            <input type="checkbox" value="COMPLETE" :checked="reviewResult === 'COMPLETE'" x-on:change="reviewResult = $event.target.checked ? 'COMPLETE' : (reviewResult === 'COMPLETE' ? '' : reviewResult)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>ข้อมูลครบ — ระบุ Disposition และส่งให้ผู้จัดการอนุมัติต่อ</span>
                                        </label>
                                        <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus-within:ring-2 focus-within:ring-indigo-500">
                                            <input type="checkbox" value="INCOMPLETE" :checked="reviewResult === 'INCOMPLETE'" x-on:change="reviewResult = $event.target.checked ? 'INCOMPLETE' : (reviewResult === 'INCOMPLETE' ? '' : reviewResult)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>ข้อมูลไม่ครบ — ส่งกลับผู้รายงานเพื่อแก้ไขก่อนตรวจใหม่</span>
                                        </label>
                                    </div>
                                </fieldset>
                                @error('review_result')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="completeness_note" class="mb-1 block text-sm font-medium">
                                    <span x-text="reviewResult === 'INCOMPLETE' ? 'เหตุผลที่ส่งกลับ' : 'หมายเหตุ (ถ้ามี)'"></span>
                                    <span x-show="reviewResult === 'INCOMPLETE'" class="text-red-600">*</span>
                                </label>
                                <textarea
                                    id="completeness_note"
                                    name="completeness_note"
                                    rows="2"
                                    :required="reviewResult === 'INCOMPLETE'"
                                    placeholder="ระบุหมายเหตุหรือเหตุผลที่ส่งกลับ"
                                    class="w-full rounded-lg border-slate-300">{{ old('completeness_note') }}</textarea>
                                @error('completeness_note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div x-show="reviewResult === 'COMPLETE'" x-cloak class="ncr-initial-review-card__disposition space-y-3">
                            <div class="ncr-initial-review-card__disposition-fields">
                                <div class="ncr-initial-review-card__disposition-type">
                                    <label for="disposition_type_id" class="mb-1 block text-sm font-medium">แนวทางจัดการปัญหา (Disposition) <span class="text-red-600">*</span></label>
                                    <select id="disposition_type_id" name="disposition_type_id" :required="reviewResult === 'COMPLETE'" class="w-full rounded-lg border-slate-300">
                                        <option value="">เลือกข้อเสนอแนะการจัดการเบื้องต้น</option>
                                        @foreach($dispositionTypes as $type)
                                        <option value="{{ $type->id }}" {{ (string) old('disposition_type_id') === (string) $type->id ? 'selected' : '' }}>{{ $fmDispositionLabels[$type->code] ?? $type->name_th }}</option>
                                        @endforeach
                                    </select>
                                    @error('disposition_type_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="ncr-initial-review-card__responsible-department">
                                    <label for="responsible_department_id" class="mb-1 block text-sm font-medium">หน่วยงานที่จะรับผิดชอบทำ Corrective Action <span class="text-red-600">*</span></label>
                                    <select id="responsible_department_id" name="responsible_department_id" :required="reviewResult === 'COMPLETE'" class="w-full rounded-lg border-slate-300">
                                        <option value="">เลือกแผนก/ฝ่ายที่รับผิดชอบ</option>
                                        @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ (string) old('responsible_department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->code }} - {{ $department->name_th }}</option>
                                        @endforeach
                                    </select>
                                    @error('responsible_department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="ncr-initial-review-card__disposition-detail">
                                    <label for="disposition_detail" class="mb-1 block text-sm font-medium">รายละเอียด / OTHERS (อื่น ๆ โปรดระบุ) <span class="text-red-600">*</span></label>
                                    <textarea id="disposition_detail" name="disposition_detail" rows="3" :required="reviewResult === 'COMPLETE'" placeholder="ระบุรายละเอียดข้อเสนอแนะ หรือรายละเอียดอื่น ๆ" class="w-full rounded-lg border-slate-300">{{ old('disposition_detail') }}</textarea>
                                    @error('disposition_detail')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ncr-initial-review-card__actions">
                        <button type="submit" class="ncr-initial-review-card__submit rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">บันทึกผลตรวจสอบ</button>
                    </div>
                </form>
                @endif
                @endcan
                @can('ncr.disposition.manage')
                @if($ncr->current_status === 'INITIAL_REVIEW' && $ncr->dispositions->where('status', 'DRAFT')->count())<form id="disposition-submit" method="POST" action="{{ route('ncr.disposition.submit', $ncr) }}" class="ncr-stage-card space-y-4 p-5 xl:col-span-2">@csrf<input type="hidden" name="version_no" value="{{ $ncr->version_no }}"><button class="ncr-action-link w-full">ส่งข้อเสนอแนะให้ผู้จัดการอนุมัติ</button></form>@endif
                @if($ncr->current_status === 'RETURNED_FOR_DISPOSITION_REVISION')
                @php($revisionSource = $ncr->dispositions->firstWhere('status', 'DRAFT') ?: $ncr->dispositions->first())
                <form id="disposition-revision" method="POST" action="{{ route('ncr.disposition.revise', $ncr) }}" class="ncr-stage-card ncr-initial-review-card ncr-workflow-form xl:col-span-2" aria-labelledby="disposition-revision-heading">
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    <div class="ncr-stage-card__header ncr-initial-review-card__header">
                        <div>
                            <p class="ncr-stage-kicker">Disposition · Revision {{ ($revisionSource?->revision_no ?? 0) + 1 }}</p>
                            <h2 id="disposition-revision-heading" class="ncr-section-title mt-1">แก้ไขแนวทางจัดการปัญหา (Disposition)</h2>
                            <p class="ncr-section-description mt-1">แก้ไขข้อมูลจากข้อเสนอฉบับก่อน แล้วส่งให้ผู้จัดการพิจารณาอีกครั้ง</p>
                            @if($revisionSource?->rejection_reason)
                            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">เหตุผลที่ส่งกลับ: {{ $revisionSource->rejection_reason }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="ncr-initial-review-card__body space-y-4">
                        <div class="ncr-initial-review-card__disposition space-y-3">
                            <div class="ncr-initial-review-card__disposition-fields">
                                <div class="ncr-initial-review-card__disposition-type">
                                    <label for="revision_disposition_type_id" class="mb-1 block text-sm font-medium">แนวทางจัดการปัญหา (Disposition) <span class="text-red-600">*</span></label>
                                    <select id="revision_disposition_type_id" name="disposition_type_id" required class="w-full rounded-lg border-slate-300">
                                        @foreach($dispositionTypes as $type)
                                        <option value="{{ $type->id }}" {{ (string) old('disposition_type_id', $revisionSource?->disposition_type_id) === (string) $type->id ? 'selected' : '' }}>{{ $fmDispositionLabels[$type->code] ?? $type->name_th }}</option>
                                        @endforeach
                                    </select>
                                    @error('disposition_type_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="ncr-initial-review-card__responsible-department">
                                    <label for="revision_responsible_department_id" class="mb-1 block text-sm font-medium">หน่วยงานที่จะรับผิดชอบทำ Corrective Action <span class="text-red-600">*</span></label>
                                    <select id="revision_responsible_department_id" name="responsible_department_id" required class="w-full rounded-lg border-slate-300">
                                        @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ (string) old('responsible_department_id', $revisionSource?->responsible_department_id) === (string) $department->id ? 'selected' : '' }}>{{ $department->code }} - {{ $department->name_th }}</option>
                                        @endforeach
                                    </select>
                                    @error('responsible_department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="ncr-initial-review-card__disposition-detail">
                                    <label for="revision_disposition_detail" class="mb-1 block text-sm font-medium">รายละเอียด / OTHERS (อื่น ๆ โปรดระบุ) <span class="text-red-600">*</span></label>
                                    <textarea id="revision_disposition_detail" name="disposition_detail" rows="3" required class="w-full rounded-lg border-slate-300">{{ old('disposition_detail', $revisionSource?->disposition_detail) }}</textarea>
                                    @error('disposition_detail')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ncr-initial-review-card__actions">
                        <div class="flex w-full flex-col justify-end gap-2 sm:flex-row">
                            <button type="submit" class="ncr-initial-review-card__submit rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">ส่งข้อเสนอฉบับแก้ไขให้ผู้จัดการอนุมัติ</button>
                        </div>
                    </div>
                </form>
                @endif
                @endcan
                @can('ncr.disposition.approve')
                @if($ncr->current_status === 'PENDING_MANAGER_APPROVAL')<form id="disposition-decision" method="POST" action="{{ route('ncr.manager-decision', $ncr) }}" class="ncr-stage-card space-y-4 p-5 xl:col-span-2" data-confirm data-confirm-always data-confirm-title="ยืนยันผลการพิจารณา" data-confirm-text="กรุณาตรวจสอบผลการพิจารณาก่อนยืนยัน" data-confirm-button="ยืนยันผล">@csrf<input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    @php($pendingDisposition = $ncr->dispositions->firstWhere('status', 'PENDING'))
                    <h2 class="font-bold">ข้อเสนอแนะการจัดการที่รอพิจารณา</h2>
                    @if($pendingDisposition)
                    <dl class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">ข้อเสนอแนะ</dt><dd class="font-medium">{{ $fmDispositionLabels[$pendingDisposition->type->code] ?? $pendingDisposition->type->name_th }}</dd></div>
                        <div><dt class="text-slate-500">แผนก/ฝ่ายที่รับผิดชอบ</dt><dd class="font-medium">{{ $pendingDisposition->responsibleDepartment->code }} - {{ $pendingDisposition->responsibleDepartment->name_th }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">รายละเอียด / OTHERS</dt><dd class="whitespace-pre-line">{{ $pendingDisposition->disposition_detail }}</dd></div>
                    </dl>
                    @endif
                    <h2 class="font-bold">ผลการพิจารณา Disposition</h2><p class="text-sm text-slate-600">Disposition คือแนวทางจัดการปัญหาและหน่วยงานรับผิดชอบ เมื่ออนุมัติ หน่วยงานจะจัดทำ Corrective Action; เมื่อไม่อนุมัติ NCR จะกลับไปให้ผู้เสนอแก้ไขและส่งพิจารณาใหม่</p><label for="manager_decision_note" class="block text-sm font-medium">หมายเหตุการพิจารณา</label><textarea id="manager_decision_note" name="decision_note" placeholder="ต้องระบุเหตุผลเมื่อไม่อนุมัติ" class="w-full rounded-lg border-slate-300"></textarea>
                    <div class="ncr-decision-buttons grid gap-2 sm:grid-cols-3">
                        <button name="decision" value="APPROVED" class="min-h-[44px] rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">อนุมัติ</button>
                        <button name="decision" value="REJECTED" class="min-h-[44px] rounded-lg border border-red-300 bg-white px-3 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-50">ไม่อนุมัติ</button>
                    </div>
                    <p class="text-xs text-slate-500">ผู้พิจารณาและวันที่/เวลาจะบันทึกจากผู้ใช้งานปัจจุบันโดยอัตโนมัติ</p>
                </form>@endif
                @endcan
                @can('ncr.destruction.approve')
                @if($ncr->current_status === 'PENDING_DESTRUCTION_APPROVAL')
                <form id="destruction-decision" method="POST" action="{{ route('ncr.destruction-decision', $ncr) }}" class="ncr-stage-card space-y-4 border-amber-200 bg-amber-50 p-5" data-confirm data-confirm-always data-confirm-title="ยืนยันผลการพิจารณาทำลาย" data-confirm-text="กรุณาตรวจสอบผลการพิจารณา SCRAP ก่อนยืนยัน" data-confirm-button="ยืนยันผล">@csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    <h2 class="font-bold text-amber-900">การอนุมัติกรณีทำลาย (S = Scrap)</h2>
                    <textarea name="decision_note" rows="3" placeholder="หมายเหตุการพิจารณา" class="w-full rounded-lg border-slate-300"></textarea>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button name="decision" value="APPROVED" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">อนุมัติทำลาย</button>
                        <button name="decision" value="REJECTED" class="rounded-lg bg-red-600 px-4 py-2 font-semibold text-white">ไม่อนุมัติ / ส่งทบทวน</button>
                    </div>
                </form>
                @endif
                @endcan
                @endif
            </section>
        </div>
        @if($showStageThree)
        @include('ncr.corrective-actions')
        @endif
        @if($showStageFour)
        @include('ncr.action-verification')
        @endif
        @if($showStageFive)
        <div class="ncr-flow-part-three border-l-4 border-emerald-400 bg-emerald-50/70 px-5 py-4">
            <h2 class="font-bold text-emerald-900">ส่วนที่ 3 : การติดตามผลการดำเนินการเพื่อให้มั่นใจว่าองค์กรสามารถควบคุมผลิตภัณฑ์ที่ไม่เป็นไปตามข้อกำหนดได้ / การปิดงาน</h2>
            <p class="mt-1 text-sm text-emerald-800">QA Follow-up และการปิด NCR</p>
        </div>
        @include('ncr.qa-followup')
        @endif
        @if($showDispositionHistory)
        <div class="ncr-flow-history rounded-xl border border-slate-200 bg-white p-3">
            <h2 class="font-bold">ประวัติข้อเสนอแนะและการพิจารณา</h2>
            <p class="mt-1 text-sm text-slate-500">แต่ละรอบแสดงข้อเสนอและผลพิจารณาที่เกี่ยวข้อง เรียงตามเวลาที่พิจารณา</p>
            <div class="mt-4 space-y-4">
                @forelse($ncr->dispositions as $disposition)
                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ncr-status-chip">รอบที่ {{ $disposition->revision_no }}</span>
                            <h3 class="font-semibold">{{ $fmDispositionLabels[$disposition->type->code] ?? $disposition->type->name_th }}</h3>
                        </div>
                        <span class="ncr-status-chip" title="{{ $disposition->status }}">สถานะข้อเสนอ: {{ config('ncr.disposition_status_labels.'.$disposition->status, $disposition->status) }}</span>
                    </header>
                    <dl class="grid gap-4 py-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold text-slate-500">รายละเอียดข้อเสนอ</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ $disposition->disposition_detail ?: '-' }}</dd>
                            @if($disposition->rejection_reason)<dd class="mt-2 whitespace-pre-line text-sm text-red-600">เหตุผลที่ส่งกลับ: {{ $disposition->rejection_reason }}</dd>@endif
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-slate-500">หน่วยงานรับผิดชอบ</dt>
                            <dd class="mt-1">{{ $disposition->responsibleDepartment?->name_th ?: '-' }}</dd>
                            <dd class="mt-2 text-sm text-slate-500">กำหนดเสร็จ: {{ \App\Support\ThaiDateFormatter::formatDate($disposition->proposed_due_date) ?: 'ยังไม่กำหนด' }}</dd>
                        </div>
                    </dl>
                    <section class="border-t border-slate-200 pt-3" aria-label="ผลการพิจารณารอบที่ {{ $disposition->revision_no }}">
                        <h4 class="text-sm font-semibold">ผลการพิจารณารอบนี้</h4>
                        @if($disposition->approvals->isNotEmpty())
                        <ol class="mt-2 space-y-2">
                            @foreach($disposition->approvals->sortBy('decided_at') as $approval)
                            <li class="rounded-md border border-slate-200 bg-white p-3 text-sm">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="font-semibold {{ $approval->decision === 'REJECTED' ? 'text-red-600' : 'text-emerald-700' }}">{{ config('ncr.approval_decision_labels.'.$approval->decision, $approval->decision) }}</span>
                                    <span>โดย {{ $approval->approver?->name ?: '-' }}</span>
                                    <time class="text-slate-500" datetime="{{ $approval->decided_at?->toIso8601String() }}">{{ \App\Support\ThaiDateFormatter::formatDateTime($approval->decided_at, config('app.display_timezone')) ?: '-' }}</time>
                                </div>
                                @if($approval->decision_note)<p class="mt-2 whitespace-pre-line text-slate-600">ความเห็น: {{ $approval->decision_note }}</p>@endif
                            </li>
                            @endforeach
                        </ol>
                        @else
                        <p class="mt-2 text-sm text-slate-500">ยังไม่มีผู้พิจารณาบันทึกผล</p>
                        @endif
                    </section>
                </article>
                @empty
                <p class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-slate-500">ยังไม่มีข้อเสนอแนะการจัดการ</p>
                @endforelse
            </div>
        </div>
        @endif
        @if($ncr->formSignoffs->count())<div class="ncr-flow-signoffs rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-4 font-bold">FM-DQA-06 Sign-off Trace</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">ส่วน</th>
                            <th class="px-3 py-2 text-left">Stage</th>
                            <th class="px-3 py-2 text-left">ผู้ลงชื่อ/ผู้รับทราบ</th>
                            <th class="px-3 py-2 text-left">ผล/วันที่</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">@foreach($ncr->formSignoffs as $signoff)<tr>
                            <td class="px-3 py-2">Part {{ $signoff->form_part }}</td>
                            <td class="px-3 py-2">{{ $signoff->signoff_stage }}</td>
                            <td class="px-3 py-2">{{ $signoff->signer?->name ?: $signoff->signer_name_snapshot ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $signoff->signoff_result ?: 'RECORDED' }} · {{ \App\Support\ThaiDateFormatter::formatDateTime($signoff->signed_at, config('app.display_timezone')) }}@if($signoff->signoff_note)<br>{{ $signoff->signoff_note }}@endif</td>
                        </tr>@endforeach</tbody>
                </table>
            </div>
        </div>@endif
        <details id="ncr-status-history" class="ncr-supporting-details mt-6">
            <summary><span class="text-base font-extrabold">ประวัติการเปลี่ยนสถานะ</span><span class="text-xs font-medium text-slate-500">{{ $ncr->statusHistory->count() }} รายการ</span></summary>
            <div class="ncr-supporting-details__body">
                <ol class="ncr-status-history max-h-96 space-y-4 overflow-y-auto pr-2">@foreach($ncr->statusHistory as $history)<li class="border-l-2 border-indigo-300 pl-4">
                        @php($historyLabel = config('ncr.history_action_labels.'.$history->action_code) ?: config('ncr.status_labels.'.$history->to_status, $history->to_status))
                        <div class="font-semibold" title="{{ $history->to_status }}">{{ $historyLabel }}</div>
                        <div class="text-xs text-slate-500">ดำเนินการโดย: {{ optional($history->changedBy)->name ?: 'ไม่ระบุผู้ใช้' }}</div>
                        @if($history->action_code === 'SUBMIT_DISPOSITION')
                            <div class="text-xs font-medium text-amber-700">รอการพิจารณาโดย: ผู้จัดการโรงงาน / รองกรรมการผู้จัดการ</div>
                        @endif
                        <div class="text-xs text-slate-500">{{ $history->action_code }} · {{ \App\Support\ThaiDateFormatter::formatDateTime($history->changed_at, config('app.display_timezone')) }}</div>@if($history->action_note)<p class="mt-1 text-sm">{{ $history->action_note }}</p>@endif
                    </li>@endforeach</ol>
            </div>
        </details>
    </div>
</x-app-layout>
