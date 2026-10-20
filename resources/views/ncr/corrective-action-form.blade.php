@php
$value = fn ($key, $default = '') => old($key, $action ? $action->getAttribute($key) : $default);
$defaultStartDate = now(config('app.display_timezone'))->toDateString();
$defaultDueDate = now(config('app.display_timezone'))->addDays(7)->toDateString();
$defaultCompletedDate = now(config('app.display_timezone'))->toDateString();
$acknowledgedAt = $value('responsible_acknowledged_at', $action ? '' : now(config('app.display_timezone'))->format('Y-m-d\\TH:i'));
$acknowledgedAtValue = $acknowledgedAt instanceof \DateTimeInterface ? $acknowledgedAt->format('Y-m-d\TH:i') : $acknowledgedAt;
$formKey = $action ? 'edit-'.$action->id : 'new';
$fieldIdPrefix = 'corrective-action-'.$formKey;
$showErrors = old('action_form_id') === $formKey;
$canUpdateExecution = $action && $ncr->current_status === 'ACTION_IN_PROGRESS';
$executionStatus = $value('action_status', $canUpdateExecution ? 'IN_PROGRESS' : 'OPEN');
if ($canUpdateExecution && $executionStatus === 'APPROVED') $executionStatus = 'IN_PROGRESS';
$completedDateValue = $value('completed_date') ?: ($executionStatus === 'COMPLETED' ? $defaultCompletedDate : '');
@endphp

<input type="hidden" name="version_no" value="{{ $ncr->version_no }}">
<input type="hidden" name="action_type" value="{{ $value('action_type', 'CORRECTION') }}">
<input type="hidden" name="action_form_id" value="{{ $formKey }}">

<div class="ncr-form-card ncr-form-card__body ncr-action-form space-y-3">
@if($canUpdateExecution)
    <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">แผน Corrective Action อนุมัติแล้ว ข้อมูลแผน ผู้รับผิดชอบ และไฟล์หลักฐานถูกล็อก แก้ไขได้เฉพาะความคืบหน้าและผลการดำเนินงาน</p>
@endif
<div class="grid gap-3 md:grid-cols-2">
    <div>
        <label for="{{ $fieldIdPrefix }}-department" class="text-sm font-semibold">แผนก/ฝ่าย *</label>
        <input id="{{ $fieldIdPrefix }}-department" type="text" readonly aria-readonly="true" value="{{ $approvedResponsibleDepartment ? $approvedResponsibleDepartment->code.' - '.$approvedResponsibleDepartment->name_th : 'ไม่พบหน่วยงานจาก Disposition ที่อนุมัติแล้ว' }}" class="mt-1 min-h-[42px] w-full rounded-lg border-slate-300 bg-slate-50 text-sm text-slate-800 {{ $showErrors && $errors->has('responsible_department_id') ? 'border-red-400' : '' }}" @if($showErrors && $errors->has('responsible_department_id')) aria-invalid="true" data-validation-error @endif>
        <input type="hidden" name="responsible_department_id" value="{{ $approvedResponsibleDepartmentId }}">
        <p class="mt-1 text-xs text-slate-500">อ้างอิงหน่วยงานจาก Disposition ที่อนุมัติแล้ว</p>
        @if($showErrors) @error('responsible_department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}-owner" class="text-sm font-semibold">ผู้รับผิดชอบ / ผู้ลงชื่อ @unless($canUpdateExecution)<span aria-hidden="true">*</span>@endunless</label>
        <select id="{{ $fieldIdPrefix }}-owner" name="owner_user_id" @unless($canUpdateExecution) required @endunless @if($canUpdateExecution) disabled @endif class="mt-1 min-h-[42px] w-full rounded-lg {{ $showErrors && $errors->has('owner_user_id') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('owner_user_id')) aria-invalid="true" data-validation-error @endif>
            <option value="">-- เลือกผู้รับผิดชอบ --</option>
            @foreach($actionOwners as $user)
                <option value="{{ $user->id }}" {{ (string)$value('owner_user_id') === (string)$user->id ? 'selected' : '' }}>
                    {{ $user->name }} ({{ optional($user->department)->code ?: '-' }})
                </option>
            @endforeach
        </select>
        @if($canUpdateExecution)<input type="hidden" name="owner_user_id" value="{{ $value('owner_user_id') }}">@endif
        @if($showErrors) @error('owner_user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
    </div>
</div>

<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
    <div>
        <label for="{{ $fieldIdPrefix }}-acknowledged-at" class="text-sm font-semibold">วันที่รับทราบ</label>
        <input id="{{ $fieldIdPrefix }}-acknowledged-at" type="datetime-local" name="responsible_acknowledged_at" value="{{ $acknowledgedAtValue }}" @if($canUpdateExecution) readonly @endif aria-describedby="{{ $fieldIdPrefix }}-acknowledged-hint" class="mt-1 min-h-[42px] w-full rounded-lg border-slate-300">
        <p id="{{ $fieldIdPrefix }}-acknowledged-hint" class="mt-1 text-xs text-slate-500">ไม่เปลี่ยน Workflow</p>
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}-start-date" class="text-sm font-semibold">วันเริ่ม</label>
        <input id="{{ $fieldIdPrefix }}-start-date" type="date" name="start_date" value="{{ optional($value('start_date', $defaultStartDate))->format('Y-m-d') ?: $value('start_date', $defaultStartDate) }}" @if($canUpdateExecution) readonly @endif class="mt-1 min-h-[42px] w-full rounded-lg {{ $showErrors && $errors->has('start_date') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('start_date')) aria-invalid="true" data-validation-error @endif>
        @if($showErrors) @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}-due-date" class="text-sm font-semibold">กำหนดเสร็จ *</label>
        <input id="{{ $fieldIdPrefix }}-due-date" type="date" name="due_date" value="{{ optional($value('due_date', $defaultDueDate))->format('Y-m-d') ?: $value('due_date', $defaultDueDate) }}" @if($canUpdateExecution) readonly @endif required class="mt-1 min-h-[42px] w-full rounded-lg {{ $showErrors && $errors->has('due_date') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('due_date')) aria-invalid="true" data-validation-error @endif>
        @if($showErrors) @error('due_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
    </div>
</div>

@if($canUpdateExecution)
    <div class="grid gap-3 md:grid-cols-3">
        <div>
            <label class="text-sm font-semibold">สถานะ Action *</label>
            <select name="action_status" required data-action-status-select data-current-date="{{ $defaultCompletedDate }}" class="mt-1 min-h-[42px] w-full rounded-lg {{ $showErrors && $errors->has('action_status') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('action_status')) aria-invalid="true" data-validation-error @endif>
                @foreach(['OPEN', 'IN_PROGRESS', 'COMPLETED'] as $status)
                    <option value="{{ $status }}" {{ $executionStatus === $status ? 'selected' : '' }}>{{ config('ncr.action_status_labels.'.$status, $status) }}</option>
                @endforeach
            </select>
            @if($showErrors) @error('action_status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
        </div>
        <div>
            <label class="text-sm font-semibold">วันที่เสร็จ</label>
            <input type="date" name="completed_date" value="{{ optional($completedDateValue)->format('Y-m-d') ?: $completedDateValue }}" data-action-completed-date class="mt-1 min-h-[42px] w-full rounded-lg {{ $showErrors && $errors->has('completed_date') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('completed_date')) aria-invalid="true" data-validation-error @endif>
            @if($showErrors) @error('completed_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
        </div>
    </div>
@else
    <input type="hidden" name="action_status" value="OPEN">
    <input type="hidden" name="completed_date" value="">
@endif
<input type="hidden" name="root_cause_type_id" value="{{ $value('root_cause_type_id') }}">
<input type="hidden" name="root_cause_detail" value="{{ $value('root_cause_detail') }}">
<input type="hidden" name="immediate_correction" value="{{ $value('immediate_correction') }}">
<input type="hidden" name="preventive_action_detail" value="{{ $value('preventive_action_detail') }}">
@if($canUpdateExecution)
    <div>
        <label class="text-sm font-semibold">บันทึกผลการดำเนินงาน (ไม่บังคับ)</label>
        <textarea name="completion_note" rows="3" class="mt-1 w-full rounded-lg {{ $showErrors && $errors->has('completion_note') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('completion_note')) aria-invalid="true" data-validation-error @endif>{{ $value('completion_note') }}</textarea>
        <p class="mt-1 text-xs text-slate-500">ระบุเพิ่มเติมได้เมื่อดำเนินงานเสร็จแล้ว</p>
        @if($showErrors) @error('completion_note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
    </div>
@else
    <input type="hidden" name="completion_note" value="">
@endif

<div>
    <label for="{{ $fieldIdPrefix }}-detail" class="text-sm font-semibold">รายละเอียดการปฏิบัติการแก้ไข *</label>
    <textarea id="{{ $fieldIdPrefix }}-detail" name="corrective_action_detail" rows="3" @if($canUpdateExecution) readonly @endif required class="mt-1 w-full rounded-lg {{ $showErrors && $errors->has('corrective_action_detail') ? 'border-red-400' : 'border-slate-300' }}" @if($showErrors && $errors->has('corrective_action_detail')) aria-invalid="true" data-validation-error @endif>{{ $value('corrective_action_detail') }}</textarea>
    @if($showErrors) @error('corrective_action_detail')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
</div>

<div class="ncr-action-form__attachments border-t border-slate-200 pt-3">
    <label for="{{ $fieldIdPrefix }}-attachments" class="text-sm font-semibold">ไฟล์หลักฐาน (สูงสุด 10 ไฟล์)</label>
    @if($canUpdateExecution)
        <p class="mt-1 text-sm text-slate-500">ไฟล์หลักฐานเป็นส่วนหนึ่งของแผนที่อนุมัติแล้ว จึงไม่สามารถเพิ่มจากหน้านี้ได้</p>
    @else
    <input id="{{ $fieldIdPrefix }}-attachments" type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" class="mt-2 block w-full rounded-lg text-sm {{ $showErrors && ($errors->has('attachments') || $errors->has('attachments.*')) ? 'border border-red-400 p-2' : '' }}" @if($showErrors && ($errors->has('attachments') || $errors->has('attachments.*'))) aria-invalid="true" data-validation-error @endif>
    @if($showErrors) @error('attachments')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @error('attachments.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
    @endif
</div>

</div>
