@php
    $currentActionCycle = $ncr->currentCorrectiveActionCycle;
    $currentCycleActions = $ncr->correctiveActions->filter(
        fn ($action) => (int) $action->corrective_action_cycle_id === (int) $ncr->current_corrective_action_cycle_id
    );
    $currentCycleActionStatusLabel = 'ยังไม่มีรอบ Corrective Action';
    if ($currentActionCycle) {
        $currentCycleActionStatusLabel = [
            'DRAFT' => 'กำลังจัดทำแผน',
            'PENDING_APPROVAL' => 'รออนุมัติแผน',
            'REJECTED' => 'แผนถูกส่งกลับให้แก้ไข',
            'APPROVED' => 'อนุมัติแผนแล้ว',
        ][$currentActionCycle->status] ?? $currentActionCycle->status;

        if ($currentActionCycle->status === 'APPROVED') {
            $currentCycleActionStatusLabel = [
                'PENDING_DESTRUCTION_APPROVAL' => 'รออนุมัติทำลาย',
                'ACTION_IN_PROGRESS' => 'กำลังดำเนินการ',
                'PENDING_ACTION_VERIFICATION' => 'รอตรวจยืนยันผล',
                'RETURNED_FOR_REWORK' => 'ส่งกลับให้ทำ Rework',
                'QA_VERIFIED' => 'รอ QA ติดตามประสิทธิผล',
                'CLOSED' => 'ปิด NCR แล้ว',
            ][$ncr->current_status] ?? 'อนุมัติแผนแล้ว';
        }
    }
    $currentCycleActionCounts = $currentCycleActions->countBy('action_status');
    $canCreateCorrectiveAction = $isCorrectiveActionProposer
        && $ncr->current_status === 'DISPOSITION_APPROVED'
        && in_array($currentActionCycle?->status, ['DRAFT', 'REJECTED'], true);
    $latestCycleAction = $currentCycleActions->sortByDesc('action_no')->first();
    $incompleteRequiredActions = $currentCycleActions->filter(function ($action) {
        return $action->is_required && $action->action_status !== 'COMPLETED';
    });
    $canSubmitCorrectiveActions = $latestCycleAction
        && $latestCycleAction->action_status === 'COMPLETED'
        && $incompleteRequiredActions->isEmpty();
@endphp

<section id="corrective-action" class="ncr-stage-card ncr-panel ncr-flow-corrective w-full overflow-hidden">
    <div class="ncr-stage-card__header">
        <div>
            <p class="ncr-stage-kicker">Stage 03 · Corrective Action</p>
            <h2 class="ncr-section-title mt-1">การดำเนินการแก้ไขและปรับปรุง (Corrective Action)</h2>
            <p class="ncr-section-description mt-1">บันทึกรายละเอียดวิธีแก้ไข หน่วยงาน ผู้รับผิดชอบ กำหนดเสร็จ และหลักฐาน</p>
        </div>

        @can('ncr.action.manage')
            @if($isCorrectiveActionProposer && $ncr->current_status === 'DISPOSITION_APPROVED' && $currentCycleActions->isNotEmpty() && $currentCycleActions->every(fn($action) => in_array($action->action_status, ['OPEN', 'REJECTED'], true)))
                <form method="POST" action="{{ route('ncr.corrective-actions.submit-approval', $ncr) }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    <button class="ncr-action-link w-full text-sm sm:w-auto">ส่งหัวหน้าแผนกอนุมัติ</button>
                </form>
            @endif
            @if($isCorrectiveActionExecutor && $ncr->current_status === 'ACTION_IN_PROGRESS' && $currentCycleActions->isNotEmpty())
                <form method="POST" action="{{ route('ncr.corrective-actions.submit', $ncr) }}" class="ncr-corrective-submit-header shrink-0">
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    <button
                        class="ncr-action-link w-full text-sm disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none sm:w-auto"
                        @if(! $canSubmitCorrectiveActions) disabled @endif
                        @if(! $canSubmitCorrectiveActions) style="border-color: rgb(var(--theme-border-slate-300)); background: rgb(var(--theme-background-slate-200)); box-shadow: none; color: rgb(var(--theme-text-slate-500)); cursor: not-allowed; opacity: 1; transform: none;" @endif
                        @if(! $canSubmitCorrectiveActions) aria-describedby="corrective-action-submit-button-hint" title="ตรวจสอบ Action ที่ยังไม่ครบก่อนส่งตรวจ" @endif>
                        ส่งตรวจ Corrective Action
                    </button>
                    @if(! $canSubmitCorrectiveActions)
                        <p id="corrective-action-submit-button-hint" class="mt-1 max-w-xs text-xs text-slate-500">ตรวจสอบรายการบังคับให้มีสถานะเสร็จแล้วก่อนส่งตรวจ</p>
                    @endif
                </form>
            @endif
        @endcan

    </div>

    @if($isCorrectiveActionExecutor && $ncr->current_status === 'ACTION_IN_PROGRESS' && $incompleteRequiredActions->isNotEmpty())
        <div id="corrective-action-submit-hint" class="mx-4 mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-950 sm:mx-5" role="status" aria-live="polite">
            <h3 class="text-sm font-semibold">ยังส่งตรวจไม่ได้ — แก้ Required Actions ต่อไปนี้ให้ครบ</h3>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach($incompleteRequiredActions as $action)
                    @php
                        $actionCanBeEdited = $latestCycleAction?->is($action);
                        $actionDialogId = ($actionCanBeEdited ? 'edit-corrective-action-' : 'view-corrective-action-').$action->id;
                        $missingActionRequirements = [];
                        if ($action->action_status !== 'COMPLETED') $missingActionRequirements[] = 'สถานะยังไม่เป็น “เสร็จแล้ว”';
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-200 bg-white/70 px-3 py-2">
                        <span><strong>Action #{{ $action->action_no }}</strong> · {{ implode(' · ', $missingActionRequirements) }}</span>
                        <button type="button" class="shrink-0 font-semibold underline underline-offset-2" data-ncr-dialog-open="{{ $actionDialogId }}" aria-haspopup="dialog">{{ $actionCanBeEdited ? 'แก้ไข Action' : 'ดูรายละเอียด' }}</button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ncr-stage-card__body space-y-5">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-xs font-semibold text-slate-600">Corrective Action รอบปัจจุบัน · Cycle {{ $currentActionCycle?->cycle_no ?: '-' }}</h3>
                    <p class="mt-1 font-bold text-slate-900">{{ $currentCycleActionStatusLabel }}</p>
                </div>
                <p class="text-sm text-slate-600">{{ $currentCycleActions->count() }} Action</p>
            </div>
            @if($currentCycleActions->isNotEmpty())
                <ul class="mt-3 flex flex-wrap gap-2" aria-label="จำนวน Action แยกตามสถานะ">
                    @foreach(['OPEN', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'IN_PROGRESS', 'COMPLETED', 'VERIFIED'] as $actionStatus)
                        @if($currentCycleActionCounts->get($actionStatus, 0) > 0)
                            <li class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700">
                                {{ config('ncr.action_status_labels.'.$actionStatus, $actionStatus) }} {{ $currentCycleActionCounts->get($actionStatus) }} รายการ
                            </li>
                        @endif
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-slate-600">ยังไม่มี Action ในรอบนี้</p>
            @endif
        </div>

        @can('ncr.action.manage')
            @if($isCorrectiveActionExecutor && $ncr->current_status === 'RETURNED_FOR_REWORK')
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <div class="font-semibold text-amber-900">NCR ถูกส่งกลับให้ทำ Rework</div>
                    <p class="mt-1 text-sm text-amber-800">กรุณาเริ่ม Rework ก่อน จึงจะเปิดแก้ไข Corrective Action และส่งตรวจรอบใหม่ได้</p>
                    <form id="start-corrective-action-rework" method="POST" action="{{ route('ncr.corrective-actions.start-rework', $ncr) }}" class="mt-3 scroll-mt-24" data-confirm data-confirm-always data-confirm-title="ยืนยันการเริ่ม Rework" data-confirm-text="ระบบจะเปิดรอบดำเนินการแก้ไขใหม่" data-confirm-button="เริ่ม Rework">
                        @csrf
                        <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-amber-600 px-5 py-2.5 font-semibold text-white hover:bg-amber-700 sm:w-auto">
                            เริ่ม Rework
                        </button>
                    </form>
                </div>
            @endif
        @endcan

        @if($ncr->correctiveActions->count())
            <div class="ncr-corrective-table-wrap">
                <table class="ncr-corrective-table min-w-[1100px] w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th scope="col">Action / สถานะ</th>
                            <th scope="col">แผนกและผู้รับผิดชอบ</th>
                            <th scope="col">กำหนดการ</th>
                            <th scope="col">รายละเอียดการปฏิบัติการแก้ไข</th>
                            <th scope="col">หลักฐาน / จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ncr->correctiveActions as $action)
                            <tr>
                                <td>
                                    <div class="ncr-corrective-table__action-title">Action #{{ $action->action_no }} · {{ $action->action_type }}</div>
                                    <dl class="ncr-corrective-table__stack mt-2">
                                        <div>
                                            <dt>สถานะ</dt>
                                            <dd>{{ config('ncr.action_status_labels.'.$action->action_status, $action->action_status) }}</dd>
                                        </div>
                                    </dl>
                                </td>
                                <td>
                                    <dl class="ncr-corrective-table__stack">
                                        <div><dt>แผนก/ฝ่าย <span>*</span></dt><dd>{{ optional($action->responsibleDepartment)->name_th ?: '-' }}</dd></div>
                                        <div><dt>ผู้รับผิดชอบ / ผู้ลงชื่อ</dt><dd>{{ optional($action->owner)->name ?: '-' }}</dd></div>
                                    </dl>
                                </td>
                                <td>
                                    <dl class="ncr-corrective-table__stack ncr-corrective-table__dates">
                                        <div><dt>วันที่รับทราบ</dt><dd>{{ $action->responsible_acknowledged_at ? $action->responsible_acknowledged_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') : '-' }}</dd></div>
                                        <div><dt>วันเริ่ม Action</dt><dd>{{ $action->start_date?->format('d/m/Y') ?: 'ยังไม่กำหนด' }}</dd></div>
                                        <div><dt>กำหนดเสร็จ Action <span>*</span></dt><dd>{{ $action->due_date?->format('d/m/Y') ?: 'ยังไม่กำหนด' }} @if($action->is_overdue)<span class="ncr-corrective-overdue">เลยกำหนด</span>@endif</dd></div>
                                    </dl>
                                </td>
                                <td class="ncr-corrective-table__detail">{{ $action->corrective_action_detail ?: '-' }}</td>
                                <td>
                                    <div class="ncr-corrective-table__attachments">
                                        @forelse($action->attachments as $attachment)
                                            <a href="{{ route('ncr.corrective-action-attachments.download', [$ncr, $attachment]) }}">{{ $attachment->original_filename }}</a>
                                        @empty
                                            <span>ยังไม่มีไฟล์แนบ</span>
                                        @endforelse
                                    </div>

                                    @php
                                        $canEditAction = auth()->user()->can('ncr.action.manage')
                                            && $latestCycleAction?->is($action)
                                            && (($isCorrectiveActionExecutor && $ncr->current_status === 'ACTION_IN_PROGRESS')
                                                || ($isCorrectiveActionProposer && $ncr->current_status === 'DISPOSITION_APPROVED' && in_array($action->action_status, ['OPEN', 'REJECTED'], true)));
                                    @endphp
                                    @if($canEditAction)
                                            <button type="button" class="ncr-corrective-edit-trigger ncr-corrective-edit-trigger--icon" data-ncr-dialog-open="edit-corrective-action-{{ $action->id }}" aria-label="แก้ไข Action #{{ $action->action_no }}" title="แก้ไข" aria-haspopup="dialog"><i class="ph ph-pencil-simple text-base leading-none" aria-hidden="true"></i></button>
                                            <dialog id="edit-corrective-action-{{ $action->id }}" class="ncr-item-dialog ncr-corrective-edit-dialog" aria-labelledby="edit-corrective-action-title-{{ $action->id }}" closedby="any" @if($errors->any() && old('action_form_id') === 'edit-'.$action->id) data-open-on-load @endif>
                                                <div class="ncr-item-dialog__header">
                                                    <div>
                                                        <p class="ncr-stage-kicker">แก้ไข Corrective Action</p>
                                                        <h2 id="edit-corrective-action-title-{{ $action->id }}" class="mt-1 text-lg font-bold">Action #{{ $action->action_no }} · {{ $action->action_type }}</h2>
                                                    </div>
                                                    <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างแก้ไข">×</button></form>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data" action="{{ route('ncr.corrective-actions.update', [$ncr, $action]) }}" class="ncr-corrective-dialog__form ncr-workflow-form">
                                                    @csrf @method('PUT')
                                                    <div class="ncr-corrective-dialog__content">
                                                        @include('ncr.corrective-action-form', ['action' => $action])
                                                    </div>
                                                    <div class="ncr-corrective-dialog__footer">
                                                        <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                                                        <button type="submit" class="ncr-dialog-submit">บันทึก Action</button>
                                                    </div>
                                                </form>
                                            </dialog>
                                    @else
                                        <button type="button" class="ncr-corrective-edit-trigger" data-ncr-dialog-open="view-corrective-action-{{ $action->id }}" aria-haspopup="dialog">ดูรายละเอียด</button>
                                        <dialog id="view-corrective-action-{{ $action->id }}" class="ncr-item-dialog" aria-labelledby="view-corrective-action-title-{{ $action->id }}" closedby="any">
                                            <div class="ncr-item-dialog__header">
                                                <div>
                                                    <p class="ncr-stage-kicker">รายละเอียด Corrective Action</p>
                                                    <h2 id="view-corrective-action-title-{{ $action->id }}" class="mt-1 text-lg font-bold">Action #{{ $action->action_no }} · {{ $action->action_type }}</h2>
                                                </div>
                                                <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างรายละเอียด">×</button></form>
                                            </div>
                                            <div class="ncr-item-dialog__body">
                                                <dl class="ncr-item-dialog__grid">
                                                    <div><dt>สถานะ</dt><dd>{{ config('ncr.action_status_labels.'.$action->action_status, $action->action_status) }}</dd></div>
                                                    <div><dt>แผนก/ฝ่าย</dt><dd>{{ optional($action->responsibleDepartment)->name_th ?: '-' }}</dd></div>
                                                    <div><dt>ผู้รับผิดชอบ</dt><dd>{{ optional($action->owner)->name ?: '-' }}</dd></div>
                                                    <div><dt>วันเริ่ม Action</dt><dd>{{ $action->start_date?->format('d/m/Y') ?: 'ยังไม่กำหนด' }}</dd></div>
                                                    <div><dt>กำหนดเสร็จ Action</dt><dd>{{ $action->due_date?->format('d/m/Y') ?: 'ยังไม่กำหนด' }}</dd></div>
                                                    <div><dt>วันที่เสร็จ Action</dt><dd>{{ $action->completed_date?->format('d/m/Y') ?: 'ยังไม่กำหนด' }}</dd></div>
                                                    <div><dt>บันทึกผลการดำเนินงาน</dt><dd class="whitespace-pre-line">{{ $action->completion_note ?: '-' }}</dd></div>
                                                </dl>
                                                <div>
                                                    <h3 class="ncr-item-dialog__section-title">รายละเอียดการดำเนินการ</h3>
                                                    <dl class="ncr-item-dialog__grid">
                                                        <div><dt>สาเหตุหลัก</dt><dd class="whitespace-pre-line">{{ $action->root_cause_detail ?: '-' }}</dd></div>
                                                        <div><dt>การแก้ไขทันที</dt><dd class="whitespace-pre-line">{{ $action->immediate_correction ?: '-' }}</dd></div>
                                                        <div><dt>Corrective Action</dt><dd class="whitespace-pre-line">{{ $action->corrective_action_detail ?: '-' }}</dd></div>
                                                        <div><dt>Preventive Action</dt><dd class="whitespace-pre-line">{{ $action->preventive_action_detail ?: '-' }}</dd></div>
                                                    </dl>
                                                </div>
                                                <div>
                                                    <h3 class="ncr-item-dialog__section-title">หลักฐานแนบ</h3>
                                                    <div class="ncr-report-attachment-list">
                                                        @forelse($action->attachments as $attachment)
                                                            <a href="{{ route('ncr.corrective-action-attachments.download', [$ncr, $attachment]) }}">{{ $attachment->original_filename }}</a>
                                                        @empty
                                                            <p>ไม่มีไฟล์แนบ</p>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>
                                        </dialog>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @can('ncr.action.manage')
            @if($canCreateCorrectiveAction)
                <div class="flex justify-end">
                    <button type="button" class="ncr-create-action-trigger" data-ncr-dialog-open="create-corrective-action" aria-haspopup="dialog">+ สร้าง Action ใหม่</button>
                </div>
                <dialog id="create-corrective-action" class="ncr-item-dialog ncr-corrective-edit-dialog" aria-labelledby="create-corrective-action-title" closedby="any" @if($errors->any() && old('action_form_id') === 'new') data-open-on-load @endif>
                    <div class="ncr-item-dialog__header">
                        <div>
                            <h2 id="create-corrective-action-title" class="text-lg font-bold">สร้าง Action ใหม่</h2>
                        </div>
                        <form method="dialog"><button type="submit" class="ncr-item-dialog__close" aria-label="ปิดหน้าต่างสร้าง Action">×</button></form>
                    </div>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('ncr.corrective-actions.store', $ncr) }}" class="ncr-corrective-dialog__form ncr-workflow-form">
                        @csrf
                        <div class="ncr-corrective-dialog__content">
                            @include('ncr.corrective-action-form', ['action' => null])
                        </div>
                        <div class="ncr-corrective-dialog__footer">
                            <button type="submit" formmethod="dialog" formnovalidate class="ncr-dialog-cancel">ยกเลิก</button>
                            <button type="submit" class="ncr-dialog-submit">สร้าง Action</button>
                        </div>
                    </form>
                </dialog>
            @endif
        @endcan

        @can('ncr.corrective-action.approve', $ncr)
            @if($ncr->current_status === 'DISPOSITION_APPROVED' && $currentCycleActions->isNotEmpty() && $currentCycleActions->every(fn($action) => $action->action_status === 'PENDING_APPROVAL'))
                <form method="POST" action="{{ route('ncr.corrective-actions.decision', $ncr) }}" class="ncr-corrective-approval">
                    @csrf
                    <input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
                    <div class="ncr-corrective-approval__heading">
                        <span class="ncr-stage-kicker">รอการพิจารณา</span>
                        <h3>ผลการอนุมัติ Corrective Action</h3>
                        <p>ตรวจสอบแผน วิธีแก้ไข ผู้รับผิดชอบ และกำหนดเสร็จ การอนุมัติจะเริ่มขั้นดำเนินการ (หรือส่งขออนุมัติทำลายหากเป็น SCRAP); การไม่อนุมัติจะส่งแผนกลับให้แก้ไขและเสนอใหม่</p>
                    </div>
                    <label for="corrective-action-decision-note">ความเห็นประกอบการพิจารณา <span class="text-xs font-normal text-slate-500">(จำเป็นเมื่อไม่อนุมัติ)</span></label>
                    <textarea id="corrective-action-decision-note" name="decision_note" rows="3" maxlength="2000" aria-describedby="corrective-action-decision-hint{{ $errors->has('decision_note') ? ' corrective-action-decision-note-error' : '' }}" @if($errors->has('decision_note')) aria-invalid="true" aria-errormessage="corrective-action-decision-note-error" @endif placeholder="หากไม่อนุมัติ กรุณาระบุเหตุผลให้ผู้รับผิดชอบนำไปแก้ไข">{{ old('decision_note') }}</textarea>
                    <p id="corrective-action-decision-hint" class="ncr-corrective-approval__hint">กรณีอนุมัติ ความเห็นเป็นข้อมูลเสริม; กรณีไม่อนุมัติ ต้องระบุเหตุผล</p>
                    @error('decision_note')<p id="corrective-action-decision-note-error" class="mt-1 text-sm font-medium text-red-600" role="alert">{{ $message }}</p>@enderror
                    <div class="ncr-corrective-approval__actions">
                        <button type="submit" name="decision" value="APPROVED" class="ncr-corrective-approval__approve">อนุมัติ</button>
                        <button type="submit" name="decision" value="REJECTED" class="ncr-corrective-approval__reject">ไม่อนุมัติและส่งกลับแก้ไข</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>
</section>
