<?php

namespace Tests\Feature;

use Tests\TestCase;

class NcrPresentationContractTest extends TestCase
{
    public function test_all_canonical_ncr_statuses_have_configured_labels(): void
    {
        $canonicalStatuses = [
            'DRAFT',
            'RETURNED_FOR_CORRECTION',
            'INITIAL_REVIEW',
            'PENDING_MANAGER_APPROVAL',
            'RETURNED_FOR_DISPOSITION_REVISION',
            'DISPOSITION_APPROVED',
            'ACTION_IN_PROGRESS',
            'PENDING_ACTION_VERIFICATION',
            'RETURNED_FOR_REWORK',
            'QA_VERIFIED',
            'CLOSED',
        ];

        foreach ($canonicalStatuses as $status) {
            $this->assertConfiguredStatusLabel($status);
        }

        $this->assertSame(
            'รอรองกรรมการผู้จัดการอนุมัติ Disposition',
            config('ncr.status_labels.PENDING_MANAGER_APPROVAL')
        );
    }

    public function test_technical_destruction_status_has_a_configured_label(): void
    {
        $this->assertConfiguredStatusLabel('PENDING_DESTRUCTION_APPROVAL');
    }

    public function test_reporter_form_excludes_initial_disposition_inputs_and_initial_review_keeps_canonical_inputs(): void
    {
        $reporterForm = file_get_contents(resource_path('views/ncr/form.blade.php'));
        $initialReviewForm = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $initialDispositionInputNames = [
            'initial_disposition_type_id',
            'initial_disposition_detail',
            'initial_responsible_department_id',
            'initial_proposed_due_date',
        ];

        foreach ($initialDispositionInputNames as $inputName) {
            $this->assertIsString($reporterForm);
            $this->assertStringNotContainsString('name="'.$inputName.'"', $reporterForm);
        }

        $canonicalDispositionInputNames = [
            'review_result',
            'disposition_type_id',
            'disposition_detail',
            'responsible_department_id',
        ];

        foreach ($canonicalDispositionInputNames as $inputName) {
            $this->assertIsString($initialReviewForm);
            $this->assertStringContainsString('name="'.$inputName.'"', $initialReviewForm);
        }
        $this->assertStringNotContainsString('name="proposed_due_date"', $initialReviewForm);
        $this->assertStringContainsString('name="due_date"', file_get_contents(resource_path('views/ncr/corrective-action-form.blade.php')));
    }

    public function test_ncr_ux_keeps_one_action_submit_and_preserves_distinct_problem_fields(): void
    {
        $correctiveActionsView = file_get_contents(resource_path('views/ncr/corrective-actions.blade.php'));
        $correctiveActionForm = file_get_contents(resource_path('views/ncr/corrective-action-form.blade.php'));
        $reportForm = file_get_contents(resource_path('views/ncr/form.blade.php'));

        $this->assertIsString($correctiveActionsView);
        $this->assertSame(1, substr_count($correctiveActionsView, "route('ncr.corrective-actions.submit',"));
        $this->assertStringNotContainsString('submit-corrective-actions', $correctiveActionsView);
        $this->assertStringContainsString('data-ncr-dialog-open="create-corrective-action"', $correctiveActionsView);
        $this->assertStringContainsString('id="create-corrective-action"', $correctiveActionsView);
        $this->assertStringContainsString("route('ncr.corrective-actions.update',", $correctiveActionsView);

        $reportView = file_get_contents(resource_path('views/ncr/show.blade.php'));
        $this->assertIsString($reportView);
        $this->assertStringContainsString('รอการพิจารณาโดย: ผู้จัดการโรงงาน / รองกรรมการผู้จัดการ', $reportView);

        $this->assertIsString($correctiveActionForm);
        $this->assertSame(0, substr_count($correctiveActionForm, 'class="ncr-stage-kicker">Action record'));
        $this->assertStringContainsString('name="corrective_action_detail"', $correctiveActionForm);

        $this->assertIsString($reportForm);
        $this->assertStringContainsString('name="problem_description"', $reportForm);
        $this->assertStringContainsString('items[${index}][problem_description]', $reportForm);
    }

    public function test_corrective_action_dialog_keeps_scrollable_content_and_actions_visible(): void
    {
        $view = file_get_contents(resource_path('views/ncr/corrective-actions.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('ncr-corrective-dialog__content', $view);
        $this->assertStringContainsString('ncr-corrective-dialog__footer', $view);
        $this->assertStringContainsString('closedby="any"', $view);
        $this->assertStringContainsString('formmethod="dialog"', $view);
    }

    public function test_create_form_uses_compact_responsive_layout_without_changing_submission_contract(): void
    {
        $reportForm = file_get_contents(resource_path('views/ncr/form.blade.php'));

        $this->assertIsString($reportForm);
        $this->assertStringContainsString('ncr-create-page', $reportForm);
        $this->assertStringContainsString('ncr-create-form space-y-4', $reportForm);
        $this->assertStringContainsString('ncr-compact-grid', $reportForm);
        $this->assertStringContainsString('ncr-items-table', $reportForm);
        $this->assertStringContainsString('ncr-description-grid', $reportForm);
        $this->assertStringContainsString('ncr-create-heading', $reportForm);
        $this->assertStringContainsString('ncr-form-section--compact', $reportForm);
        $this->assertStringContainsString('id="ncr-context"', $reportForm);
        $this->assertStringContainsString('id="ncr-products"', $reportForm);
        $this->assertStringContainsString('id="ncr-evidence"', $reportForm);
        $this->assertSame(1, substr_count($reportForm, '<form'));

        foreach ([
            'name="problem_description"',
            'name="attachments[]"',
            ':name="`items[${index}][product_name]`"',
            ':name="`items[${index}][defect_quantity]`"',
            ':name="`items[${index}][total_quantity]`"',
            'data-ncr-submit',
        ] as $contract) {
            $this->assertStringContainsString($contract, $reportForm);
        }
    }

    public function test_completing_an_action_defaults_completion_date_without_a_progress_field(): void
    {
        $correctiveActionForm = file_get_contents(resource_path('views/ncr/corrective-action-form.blade.php'));
        $appScript = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($correctiveActionForm);
        $this->assertIsString($appScript);
        $this->assertStringContainsString('data-action-status-select data-current-date="{{ $defaultCompletedDate }}"', $correctiveActionForm);
        $this->assertStringContainsString('$completedDateValue = $value(\'completed_date\') ?: ($executionStatus === \'COMPLETED\' ? $defaultCompletedDate : \'\');', $correctiveActionForm);
        $this->assertStringNotContainsString('progress_percent', $correctiveActionForm);
        $this->assertStringNotContainsString('Progress (%)', $correctiveActionForm);
        $this->assertStringContainsString("if (statusField.value !== 'COMPLETED') return;", $appScript);
        $this->assertStringContainsString('completedDateField.value = formatDate(todayIso);', $appScript);
    }

    public function test_create_action_button_is_limited_to_a_draft_action_cycle(): void
    {
        $correctiveActionsView = file_get_contents(resource_path('views/ncr/corrective-actions.blade.php'));

        $this->assertIsString($correctiveActionsView);
        $this->assertStringContainsString("in_array(\$currentActionCycle?->status, ['DRAFT', 'REJECTED'], true)", $correctiveActionsView);
        $this->assertStringContainsString('@if($canCreateCorrectiveAction)', $correctiveActionsView);
    }

    public function test_submit_action_button_is_disabled_until_actions_are_completed(): void
    {
        $correctiveActionsView = file_get_contents(resource_path('views/ncr/corrective-actions.blade.php'));
        $appStyles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($correctiveActionsView);
        $this->assertIsString($appStyles);
        $this->assertStringContainsString("\$latestCycleAction->action_status === 'COMPLETED'", $correctiveActionsView);
        $this->assertStringContainsString('@if(! $canSubmitCorrectiveActions) disabled @endif', $correctiveActionsView);
        $this->assertStringNotContainsString('@disabled(! $canSubmitCorrectiveActions)', $correctiveActionsView);
        $this->assertStringContainsString('background: rgb(var(--theme-background-slate-200));', $correctiveActionsView);
        $this->assertStringContainsString('ตรวจสอบรายการบังคับให้มีสถานะเสร็จแล้วก่อนส่งตรวจ', $correctiveActionsView);
        $this->assertStringNotContainsString('progress_percent', $correctiveActionsView);
        $this->assertStringContainsString('.ncr-action-link:disabled', $appStyles);
        $this->assertStringContainsString('$latestCycleAction?->is($action)', $correctiveActionsView);
        $this->assertStringContainsString('data-ncr-dialog-open="view-corrective-action-{{ $action->id }}"', $correctiveActionsView);
        $this->assertStringContainsString('ดูรายละเอียด', $correctiveActionsView);
        $this->assertStringContainsString('@if($canEditAction)', $correctiveActionsView);
    }

    public function test_product_items_are_edited_in_a_native_dialog_and_keep_the_submission_contract(): void
    {
        $reportForm = file_get_contents(resource_path('views/ncr/form.blade.php'));

        $this->assertIsString($reportForm);
        $this->assertStringContainsString('<dialog x-ref="itemDialog" x-on:close="itemDialogOpen = false"', $reportForm);
        $this->assertStringContainsString('disabled x-bind:disabled="!itemDialogOpen"', $reportForm);
        $this->assertStringContainsString('this.$refs.itemDialog.showModal()', $reportForm);
        $this->assertStringContainsString('x-on:click="saveItem()"', $reportForm);
        $this->assertStringContainsString('x-on:click="editItem(index)"', $reportForm);
        $this->assertStringContainsString('x-show="items.length === 0"', $reportForm);
        $this->assertStringContainsString('ยังไม่มีรายการสินค้า', $reportForm);
        $this->assertStringContainsString("                    : [];", $reportForm);
        $this->assertStringContainsString(':name="`items[${index}][product_name]`"', $reportForm);
        $this->assertStringContainsString(':name="`items[${index}][problem_description]`"', $reportForm);
        $this->assertStringNotContainsString('min-w-[1900px]', $reportForm);
    }

    public function test_stage_one_report_uses_a_compact_accessible_summary(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $this->assertIsString($showView);
        $this->assertStringContainsString('ncr-report-stage', $showView);
        $this->assertStringContainsString('ncr-report-summary', $showView);
        $this->assertStringContainsString('id="ncr-details"', $showView);
        $this->assertStringContainsString('aria-label="ข้อมูลสำคัญของรายงาน"', $showView);
        $this->assertStringContainsString('หน่วยงานผู้รายงาน:', $showView);
        $this->assertStringContainsString('<dt>หน่วยงานที่พบปัญหา</dt>', $showView);
        $this->assertStringNotContainsString('<dt>ความรุนแรง</dt>', $showView);
        $this->assertStringContainsString('<caption class="sr-only">รายการผลิตภัณฑ์ที่เกี่ยวข้องกับ NCR</caption>', $showView);
        $this->assertSame(8, substr_count($showView, 'scope="col"'));
        $this->assertStringContainsString('aria-haspopup="dialog"', $showView);
    }

    public function test_terminal_ncr_hides_current_task_navigation(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $this->assertIsString($showView);
        $this->assertStringContainsString("@if(\$ncr->current_status !== 'CLOSED')", $showView);
        $this->assertStringNotContainsString('ไปยังงานปัจจุบัน', $showView);
        $this->assertStringNotContainsString('$currentActionTarget', $showView);
    }

    public function test_detail_page_progressively_discloses_workflow_stages(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $this->assertIsString($showView);
        $this->assertStringContainsString("\$showStageTwo = \$ncr->current_status !== 'DRAFT';", $showView);
        $this->assertStringContainsString('@if($showStageTwo)', $showView);
        $this->assertStringContainsString('@if($showStageThree)', $showView);
        $this->assertStringContainsString('@if($showStageFour)', $showView);
        $this->assertStringContainsString('@if($showStageFive)', $showView);
        $this->assertStringContainsString('@if($showDispositionHistory)', $showView);
    }

    public function test_status_history_is_separate_from_the_active_workflow(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $this->assertIsString($showView);
        $historyPosition = strpos($showView, '<details id="ncr-status-history"');
        $workflowEnd = strrpos($showView, '</section>');

        $this->assertNotFalse($historyPosition);
        $this->assertNotFalse($workflowEnd);
        $this->assertGreaterThan($workflowEnd, $historyPosition);
        $this->assertStringNotContainsString('Supporting record', $showView);
        $this->assertStringNotContainsString('@if($errors->any()) open', $showView);
    }

    public function test_detail_header_precedes_action_and_terminal_progress_is_collapsible(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));
        $appCss = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($showView);
        $this->assertIsString($appCss);
        $this->assertLessThan(strpos($showView, 'class="ncr-current-action'), strpos($showView, 'class="ncr-flow-header'));
        $this->assertStringContainsString('ncr-stepper ncr-progress-static', $showView);
        $this->assertStringContainsString('ncr-workflow-overview', $showView);
        $this->assertStringNotContainsString('ncr-progress-details[open]', $appCss);
        $this->assertStringNotContainsString('ดูทั้งหมด +', $appCss);
        $this->assertStringNotContainsString('Workflow Summary', $showView);
        $this->assertStringNotContainsString('ncr-tracker-mobile', $showView);
        $this->assertStringNotContainsString('.ncr-flow-header { order:', $appCss);
        $this->assertStringNotContainsString('.ncr-flow-main { order:', $appCss);
    }

    public function test_initial_review_uses_a_single_current_stage_card(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $this->assertIsString($showView);
        $this->assertStringContainsString('ncr-initial-review-card', $showView);
        $this->assertStringContainsString('aria-labelledby="initial-review-heading"', $showView);
        $this->assertStringContainsString('aria-describedby="initial-review-description"', $showView);
        $this->assertStringContainsString('ncr-initial-review-card__header', $showView);
        $this->assertStringContainsString('ncr-initial-review-card__body space-y-4', $showView);
        $this->assertStringContainsString('ncr-initial-review-card__disposition', $showView);
        $this->assertStringContainsString('ncr-initial-review-card__submit', $showView);
        $this->assertStringNotContainsString('class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 xl:col-span-2"', $showView);
        $this->assertSame(1, substr_count($showView, 'Current stage · Initial Review'));
    }

    public function legacy_qa_followup_uses_separate_followup_and_decision_regions(): void
    {
        $qaView = file_get_contents(resource_path('views/ncr/qa-followup.blade.php'));
        $appJs = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($qaView);
        $this->assertIsString($appJs);
        $this->assertStringContainsString('ncr-qa-workspace', $qaView);
        $this->assertStringContainsString('ncr-qa-primary', $qaView);
        $this->assertStringContainsString('ncr-qa-actions', $qaView);
        $this->assertStringNotContainsString('<template x-if="false">', $qaView);
        $this->assertStringNotContainsString('<details class="ncr-qa-direct-return"', $qaView);
        $this->assertStringContainsString('id="qa-followup-form"', $qaView);
        $this->assertStringContainsString('x-data="{ result: \'{{ old(\'effectiveness_result\', \'EFFECTIVE\') }}\', followupDetail: \'\' }"', $qaView);
        $this->assertStringContainsString('x-init="$nextTick(() => { if ($refs.followupDetail) followupDetail = $refs.followupDetail.value })"', $qaView);
        $this->assertStringContainsString('id="qa-followup-detail" x-ref="followupDetail" name="followup_detail"', $qaView);
        $this->assertStringContainsString('x-show="result === \'EFFECTIVE\'" x-cloak data-confirm', $qaView);
        $this->assertStringContainsString('data-confirm-button="ปิด NCR"', $qaView);
        $this->assertStringContainsString('>ปิด NCR</button>', $qaView);
        $this->assertStringContainsString('form="qa-followup-form"', $qaView);
        $this->assertStringContainsString('type="submit" form="qa-followup-form"', $qaView);
        $this->assertStringContainsString('x-show="result === \'NOT_EFFECTIVE\'" x-cloak data-qa-return-submit', $qaView);
        $this->assertStringContainsString('class="ncr-qa-action ncr-qa-action--return', $qaView);
        $this->assertStringContainsString('ncr-qa-action--return w-full rounded-lg px-4 py-3 font-semibold text-white', $qaView);
        $this->assertStringContainsString('data-qa-return-submit', $qaView);
        $this->assertStringContainsString('detailField.checkValidity()', $appJs);
        $this->assertStringContainsString('กรุณากรอก “รายละเอียดผลติดตามที่ยังไม่ผ่าน / ต้องติดตามต่อ *”', $appJs);
        $this->assertStringContainsString('ขั้นตอนหลัก: กรอกข้อมูลด้านซ้าย แล้วกด “บันทึกการติดตามผล” ก่อน', $qaView);
        $this->assertStringContainsString('ใช้ข้อความจากช่องรายละเอียดด้านซ้ายเป็นเหตุผลส่งกลับ', $qaView);
        $this->assertStringNotContainsString('<details class="ncr-qa-direct-return" open', $qaView);
        $this->assertStringContainsString('ผลที่เลือก: ไม่มีประสิทธิผล — บันทึกแล้วจะส่งกลับแก้ไข', $qaView);
        $this->assertStringContainsString('ผลที่เลือก: ติดตามครั้งถัดไป — กรอกวันที่นัดหมายแล้วบันทึก', $qaView);
        $this->assertStringContainsString('รายละเอียดผลติดตามที่ยังไม่ผ่าน / ต้องติดตามต่อ *', $qaView);
        $this->assertStringContainsString('name="return_reason" x-model="followupDetail" x-bind:disabled="result === \'EFFECTIVE\'"', $qaView);
        $this->assertStringContainsString('name="reason" x-model="followupDetail"', $qaView);
        $this->assertStringNotContainsString('name="reason" required x-model="followupDetail"', $qaView);
        $this->assertStringContainsString('aria-labelledby="qa-decision-title"', $qaView);
        $this->assertSame(1, substr_count($qaView, 'route(\'ncr.qa-followup.store\', $ncr)'));
        $this->assertSame(0, substr_count($qaView, 'route(\'ncr.qa-followup.ready\', $ncr)'));
        $this->assertSame(0, substr_count($qaView, 'route(\'ncr.close\', $ncr)'));
        $this->assertStringContainsString('name="checklist_confirmed"', $qaView);
        $this->assertStringContainsString('บันทึกผลและปิด NCR', $qaView);
    }

    public function test_qa_followup_closes_in_one_atomic_form(): void
    {
        $qaView = file_get_contents(resource_path('views/ncr/qa-followup.blade.php'));

        $this->assertIsString($qaView);
        $this->assertSame(1, substr_count($qaView, 'route(\'ncr.qa-followup.store\', $ncr)'));
        $this->assertSame(0, substr_count($qaView, 'route(\'ncr.qa-followup.ready\', $ncr)'));
        $this->assertSame(0, substr_count($qaView, 'route(\'ncr.close\', $ncr)'));
        $this->assertStringContainsString('name="checklist_confirmed"', $qaView);
        $this->assertStringNotContainsString('name="closure_note"', $qaView);
        $this->assertStringContainsString("x-text=\"result === 'NOT_EFFECTIVE' ? 'ผลที่ติดตามและเหตุผลที่ส่งกลับ Rework' : 'ผลที่ติดตามและหลักฐานประกอบ'\"", $qaView);
        $this->assertStringNotContainsString('name="recurrence_found"', $qaView);
        $this->assertStringNotContainsString('QA Decision', $qaView);
        $this->assertStringNotContainsString('ncr-qa-actions', $qaView);
        $this->assertStringContainsString('บันทึกผลและปิด NCR', $qaView);
    }

    public function test_decision_forms_are_guarded_by_their_matching_permissions(): void
    {
        $showView = file_get_contents(resource_path('views/ncr/show.blade.php'));

        $this->assertIsString($showView);
        $this->assertMatchesRegularExpression("/@can\('ncr\.disposition\.approve'\).*?id=\"disposition-decision\".*?@endcan/s", $showView);
        $this->assertMatchesRegularExpression("/@can\('ncr\.destruction\.approve'\).*?id=\"destruction-decision\".*?@endcan/s", $showView);
    }

    private function assertConfiguredStatusLabel(string $status): void
    {
        $labels = config('ncr.status_labels');

        $this->assertIsArray($labels);
        $this->assertArrayHasKey($status, $labels);
        $this->assertIsString($labels[$status]);
        $this->assertNotSame('', trim($labels[$status]));
    }
}
