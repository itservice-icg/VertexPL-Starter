require('./bootstrap');

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.Alpine = Alpine;
window.Swal = Swal;
window.permissionMatrix = (initialSelected = []) => ({
    selected: initialSelected.map(Number),
    toggleGroup(ids) {
        const normalizedIds = ids.map(Number);
        const allSelected = normalizedIds.every((id) => this.selected.includes(id));

        if (allSelected) {
            this.selected = this.selected.filter((id) => ! normalizedIds.includes(id));
            return;
        }

        this.selected = [...new Set([...this.selected, ...normalizedIds])];
    },
    groupSelected(ids) {
        return ids.map(Number).every((id) => this.selected.includes(id));
    },
    selectAll(ids) {
        const normalizedIds = ids.map(Number);
        this.selected = this.selected.length === normalizedIds.length ? [] : normalizedIds;
    },
});
window.passwordStrength = () => ({
    password: '',
    showCurrent: false,
    showPassword: false,
    showConfirmation: false,
    get checks() {
        return {
            length: this.password.length >= 14,
            lowercase: /[a-z]/.test(this.password),
            uppercase: /[A-Z]/.test(this.password),
            number: /[0-9]/.test(this.password),
            symbol: /[^A-Za-z0-9]/.test(this.password),
        };
    },
    get score() {
        return Object.values(this.checks).filter(Boolean).length;
    },
    get label() {
        return ['ยังไม่ได้กรอก', 'อ่อนมาก', 'อ่อน', 'ปานกลาง', 'ดี', 'แข็งแรง'][this.score];
    },
});

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    // Keep date controls in ISO form for Laravel while displaying a stable
    // day/month/year format regardless of the browser's locale.
    document.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach((field) => {
        const dateTime = field.type === 'datetime-local';
        const picker = document.createElement('input');
        picker.type = dateTime ? 'datetime-local' : 'date';
        picker.tabIndex = -1;
        picker.setAttribute('aria-hidden', 'true');
        picker.className = 'localized-date-picker';
        if (field.min) picker.min = field.min;
        if (field.max) picker.max = field.max;

        const submittedValue = document.createElement('input');
        submittedValue.type = 'hidden';
        submittedValue.name = field.name;
        field.removeAttribute('name');

        const wrap = document.createElement('div');
        wrap.className = 'localized-date-control mt-1';
        field.parentNode.insertBefore(wrap, field);
        wrap.append(field);
        field.type = 'text';
        field.inputMode = 'numeric';
        field.placeholder = dateTime ? 'dd/mm/yyyy HH:mm' : 'dd/mm/yyyy';
        field.autocomplete = 'off';
        field.classList.remove('mt-1', 'mt-2');
        field.classList.add('w-full', 'pr-12');
        picker.value = dateTime ? toDateTimeValue(field.value) : toDateValue(field.value);
        field.value = dateTime ? formatDateTime(picker.value) : formatDate(picker.value);
        const calendarButton = document.createElement('button');
        calendarButton.type = 'button';
        calendarButton.className = 'localized-date-button';
        calendarButton.setAttribute('aria-label', dateTime ? 'เลือกวันที่และเวลา' : 'เลือกวันที่');
        calendarButton.disabled = field.readOnly;
        calendarButton.innerHTML = '<i class="ph ph-calendar-blank text-lg leading-none" aria-hidden="true"></i>';
        calendarButton.addEventListener('click', () => {
            try {
                if (typeof picker.showPicker === 'function') picker.showPicker();
                else picker.focus();
            } catch (_) {
                picker.focus();
            }
        });
        wrap.append(calendarButton, picker, submittedValue);

        const syncSubmittedValue = () => {
            const parsed = parseDisplayDate(field.value, dateTime);
            submittedValue.value = parsed;
            return parsed !== '' || field.value.trim() === '';
        };
        syncSubmittedValue();

        const syncFromPicker = () => {
            field.value = dateTime ? formatDateTime(picker.value) : formatDate(picker.value);
            syncSubmittedValue();
            validateDateField(field, dateTime);
        };
        picker.addEventListener('input', syncFromPicker);
        picker.addEventListener('change', syncFromPicker);
        field.addEventListener('input', () => {
            syncSubmittedValue();
            validateDateField(field, dateTime);
        });
        field.addEventListener('blur', () => {
            const parsed = parseDisplayDate(field.value, dateTime);
            if (parsed) {
                picker.value = parsed;
                field.value = dateTime ? formatDateTime(parsed) : formatDate(parsed);
            }
            syncSubmittedValue();
            validateDateField(field, dateTime);
        });
        field.addEventListener('keydown', (event) => {
            if (! field.readOnly && event.key === 'ArrowDown' && event.altKey) {
                event.preventDefault();
                if (typeof picker.showPicker === 'function') picker.showPicker();
                else picker.focus();
            }
        });

        const form = wrap.closest('form');
        if (form) {
            form.addEventListener('reset', () => window.setTimeout(() => {
                picker.value = dateTime ? toDateTimeValue(field.value) : toDateValue(field.value);
                field.value = dateTime ? formatDateTime(picker.value) : formatDate(picker.value);
                syncSubmittedValue();
                validateDateField(field, dateTime);
            }));
        }
    });

    document.querySelectorAll('[data-ncr-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialog = document.getElementById(trigger.dataset.ncrDialogOpen);
            if (dialog instanceof HTMLDialogElement && ! dialog.open) {
                if (trigger.hasAttribute('data-ncr-dialog-reset-form')) {
                    const form = dialog.querySelector('form.department-dialog__form');
                    if (form) {
                        form.reset();
                        form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), textarea').forEach((field) => {
                            field.value = '';
                        });
                        form.querySelectorAll('[data-dialog-form-error]').forEach((error) => error.remove());
                    }
                }

                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('[data-action-status-select]').forEach((statusField) => {
        statusField.addEventListener('change', () => {
            if (statusField.value !== 'COMPLETED') return;

            const form = statusField.closest('form');
            const completedDateField = form?.querySelector('[data-action-completed-date]');
            if (! completedDateField) return;

            const today = new Date();
            const localTodayIso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            const todayIso = statusField.dataset.currentDate || localTodayIso;
            completedDateField.value = formatDate(todayIso);
            const dateControl = completedDateField.closest('.localized-date-control');
            const picker = dateControl?.querySelector('.localized-date-picker');
            const submittedValue = dateControl?.querySelector('input[type="hidden"]');
            if (picker) picker.value = todayIso;
            if (submittedValue) submittedValue.value = todayIso;
            validateDateField(completedDateField, false);
        });
    });

    document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => {
        if (dialog instanceof HTMLDialogElement && ! dialog.open) dialog.showModal();
    });

    if (! ('closedBy' in HTMLDialogElement.prototype)) {
        document.querySelectorAll('dialog[closedby="any"]').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target !== dialog) return;

                const bounds = dialog.getBoundingClientRect();
                const clickedInside = event.clientX >= bounds.left
                    && event.clientX <= bounds.right
                    && event.clientY >= bounds.top
                    && event.clientY <= bounds.bottom;

                if (! clickedInside) dialog.close();
            });
        });
    }

    const firstValidationError = document.querySelector('[data-validation-error]');
    if (firstValidationError) {
        const collapsedGroup = firstValidationError.closest('details');
        if (collapsedGroup) collapsedGroup.open = true;

        window.requestAnimationFrame(() => {
            firstValidationError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof firstValidationError.focus === 'function') {
                firstValidationError.focus({ preventScroll: true });
            }
        });
    }

    document.querySelectorAll('[data-flash-message]').forEach((element) => {
        Swal.fire({
            toast: element.dataset.flashType !== 'error',
            position: element.dataset.flashType === 'error' ? 'center' : 'top-end',
            icon: element.dataset.flashType || 'success',
            title: element.dataset.flashType === 'error' ? 'ไม่สามารถดำเนินการได้' : element.dataset.flashMessage,
            text: element.dataset.flashType === 'error' ? element.dataset.flashMessage : undefined,
            showConfirmButton: element.dataset.flashType === 'error',
            confirmButtonText: 'ตกลง',
            timer: element.dataset.flashType === 'error' ? undefined : 3500,
            timerProgressBar: element.dataset.flashType !== 'error',
        });
    });

    // Navigation aids only: the server remains the source of truth for every NCR action.
    document.querySelectorAll('[data-ncr-scroll]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            const target = document.querySelector(trigger.dataset.ncrScroll);
            if (! target) return;

            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.classList.add('ncr-focus-target');
            window.setTimeout(() => target.classList.remove('ncr-focus-target'), 1400);
        });
    });

    // Native browser validation can target a field inside a collapsed details group.
    // Open that group before the browser moves focus; this never changes validation rules.
    document.addEventListener('invalid', (event) => {
        const field = event.target;
        if (! field || ! field.closest) return;

        const collapsedGroup = field.closest('details');
        if (collapsedGroup) collapsedGroup.open = true;
    }, true);

    document.querySelectorAll('[data-qa-return-submit]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const form = document.getElementById(button.getAttribute('form'));
            const detailField = form?.querySelector('[name="followup_detail"]');

            if (! detailField || detailField.checkValidity()) return;

            event.preventDefault();
            detailField.setAttribute('aria-invalid', 'true');

            Swal.fire({
                icon: 'warning',
                title: 'กรุณากรอกข้อมูลก่อนส่งกลับ',
                text: 'กรุณากรอก “รายละเอียดผลติดตามที่ยังไม่ผ่าน / ต้องติดตามต่อ *”',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#dc2626',
            }).then(() => {
                detailField.focus({ preventScroll: false });
            });
        });

        const form = document.getElementById(button.getAttribute('form'));
        const detailField = form?.querySelector('[name="followup_detail"]');
        detailField?.addEventListener('input', () => {
            if (detailField.checkValidity()) detailField.removeAttribute('aria-invalid');
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            const submitter = event.submitter;
            const inactiveField = form.querySelector('[name="is_active"]');
            const shouldConfirmInactive = form.hasAttribute('data-confirm-when-inactive')
                && inactiveField
                && inactiveField.value === '0';

            if (form.dataset.confirmHandled === 'true') {
                return;
            }

            if (! shouldConfirmInactive && ! form.hasAttribute('data-confirm-always')) {
                return;
            }

            event.preventDefault();

            const result = await Swal.fire({
                icon: 'warning',
                title: form.dataset.confirmTitle || 'ยืนยันการทำรายการ',
                text: form.dataset.confirmText || 'กรุณายืนยันก่อนดำเนินการต่อ',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc2626',
                reverseButtons: true,
            });

            if (result.isConfirmed) {
                form.dataset.confirmHandled = 'true';
                form.requestSubmit(submitter || undefined);
            }
        });
    });

    // Keep workflow submissions single-flight. The server remains authoritative;
    // this only prevents an accidental double click while the response is pending.
    document.querySelectorAll('.ncr-workflow-form, .ncr-page form[data-ncr-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || form.dataset.submitting === 'true') {
                if (form.dataset.submitting === 'true') event.preventDefault();
                return;
            }

            const submitter = event.submitter;
            if (submitter?.formMethod === 'dialog' || submitter?.formNoValidate) return;

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');

            const buttons = submitter
                ? [submitter]
                : [...form.querySelectorAll('button[type="submit"], button:not([type])')];
            buttons.forEach((button) => {
                if (button.formMethod === 'dialog' || button.formNoValidate) return;
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
                button.dataset.originalText = button.textContent.trim();
                button.textContent = 'กำลังบันทึก…';
            });
        });
    });
});

function toDateValue(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

function toDateTimeValue(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
    return match ? match[1] : '';
}

function formatDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
}

function formatDateTime(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})$/);
    return match ? `${formatDate(match[1])} ${match[2]}:${match[3]}` : '';
}

function parseDisplayDate(value, dateTime) {
    const expression = dateTime
        ? /^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/
        : /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;
    const match = String(value || '').trim().match(expression);
    if (! match) return '';

    const [, dayText, monthText, yearText, hourText, minuteText] = match;
    const day = Number(dayText);
    const month = Number(monthText);
    const year = Number(yearText);
    const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    const daysInMonth = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if (month < 1 || month > 12 || day < 1 || day > daysInMonth[month - 1]) return '';

    const date = `${yearText}-${monthText.padStart(2, '0')}-${dayText.padStart(2, '0')}`;
    if (! dateTime) return date;
    const hour = Number(hourText);
    const minute = Number(minuteText);
    if (hour > 23 || minute > 59) return '';
    return `${date}T${String(hour).padStart(2, '0')}:${minuteText}`;
}

function validateDateField(field, dateTime) {
    const value = field.value.trim();
    const valid = value === '' || parseDisplayDate(value, dateTime) !== '';
    field.setCustomValidity(valid ? '' : (dateTime ? 'กรุณากรอกวันที่และเวลาเป็น dd/mm/yyyy HH:mm' : 'กรุณากรอกวันที่เป็น dd/mm/yyyy'));
    field.setAttribute('aria-invalid', valid ? 'false' : 'true');
}
