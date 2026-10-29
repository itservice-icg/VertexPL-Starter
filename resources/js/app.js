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
    // Keep date/time controls in Laravel's canonical formats while presenting
    // Thai dates and a stable 24-hour clock regardless of browser locale.
    document.querySelectorAll('input[type="date"], input[type="time"], input[type="datetime-local"]').forEach((field) => {
        const timeOnly = field.type === 'time';
        const dateTime = field.type === 'datetime-local';

        const submittedValue = document.createElement('input');
        submittedValue.type = 'hidden';
        submittedValue.name = field.name;
        field.removeAttribute('name');

        const wrap = document.createElement('div');
        wrap.className = `${timeOnly ? 'localized-time-control' : 'localized-date-control'} mt-1`;
        field.parentNode.insertBefore(wrap, field);
        wrap.append(field);
        field.type = 'text';
        field.lang = 'th-TH';
        field.inputMode = 'numeric';
        field.placeholder = timeOnly ? 'HH:mm' : (dateTime ? 'dd/mm/yyyy HH:mm' : 'dd/mm/yyyy');
        field.autocomplete = 'off';
        field.classList.remove('mt-1', 'mt-2');
        field.classList.add('w-full', 'pr-12');
        field.value = timeOnly ? formatTime(field.value) : (dateTime ? formatDateTime(field.value) : formatDate(field.value));
        const calendarButton = document.createElement('button');
        calendarButton.type = 'button';
        calendarButton.className = 'localized-date-button';
        calendarButton.setAttribute('aria-label', timeOnly ? 'เลือกเวลา' : (dateTime ? 'เลือกวันที่และเวลา' : 'เลือกวันที่'));
        calendarButton.setAttribute('aria-haspopup', 'dialog');
        calendarButton.setAttribute('aria-expanded', 'false');
        calendarButton.disabled = field.readOnly;
        calendarButton.innerHTML = `<i class="ph ph-${timeOnly ? 'clock' : 'calendar-blank'} text-lg leading-none" aria-hidden="true"></i>`;
        wrap.append(calendarButton);
        if (timeOnly) attachThaiTimePicker(wrap, field, calendarButton);
        else attachThaiCalendar(wrap, field, dateTime, calendarButton);
        wrap.append(submittedValue);

        const syncSubmittedValue = () => {
            const parsed = timeOnly ? parseDisplayTime(field.value) : parseDisplayDate(field.value, dateTime);
            submittedValue.value = parsed;
            return parsed !== '' || field.value.trim() === '';
        };
        syncSubmittedValue();

        field.addEventListener('input', () => {
            syncSubmittedValue();
            validateTemporalField(field, timeOnly, dateTime);
        });
        field.addEventListener('blur', () => {
            const parsed = timeOnly ? parseDisplayTime(field.value) : parseDisplayDate(field.value, dateTime);
            if (parsed) {
                field.value = timeOnly ? formatTime(parsed) : (dateTime ? formatDateTime(parsed) : formatDate(parsed));
            }
            syncSubmittedValue();
            validateTemporalField(field, timeOnly, dateTime);
        });
        field.addEventListener('keydown', (event) => {
            if (! field.readOnly && event.key === 'ArrowDown' && event.altKey) {
                event.preventDefault();
                calendarButton.click();
            }
        });

        const form = wrap.closest('form');
        if (form) {
            form.addEventListener('reset', () => window.setTimeout(() => {
                field.value = timeOnly ? formatTime(field.value) : (dateTime ? formatDateTime(field.value) : formatDate(field.value));
                syncSubmittedValue();
                validateTemporalField(field, timeOnly, dateTime);
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
            const submittedValue = dateControl?.querySelector('input[type="hidden"]');
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

function attachThaiCalendar(wrap, field, dateTime, trigger) {
    const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    const weekdays = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
    const calendar = document.createElement('div');
    calendar.className = 'localized-calendar';
    calendar.hidden = true;
    calendar.setAttribute('role', 'dialog');
    calendar.setAttribute('aria-label', 'ปฏิทินเลือกวันที่');
    calendar.innerHTML = `
        <div class="localized-calendar__header">
            <button type="button" class="localized-calendar__nav" data-calendar-previous aria-label="เดือนก่อนหน้า">‹</button>
            <h2 class="localized-calendar__title" aria-live="polite" aria-atomic="true"></h2>
            <button type="button" class="localized-calendar__nav" data-calendar-next aria-label="เดือนถัดไป">›</button>
        </div>
        <div class="localized-calendar__grid" role="grid">
            <div class="localized-calendar__weekdays" role="row"></div>
            <div class="localized-calendar__days" role="rowgroup"></div>
        </div>
        <div class="localized-calendar__footer">
            <button type="button" class="localized-calendar__text-button" data-calendar-clear>ล้างค่า</button>
            <button type="button" class="localized-calendar__text-button" data-calendar-today>วันนี้</button>
        </div>`;
    wrap.append(calendar);

    const title = calendar.querySelector('.localized-calendar__title');
    const weekdayRow = calendar.querySelector('.localized-calendar__weekdays');
    const daysGrid = calendar.querySelector('.localized-calendar__days');
    const today = bangkokTodayIso();
    const selected = parseDisplayDate(field.value, dateTime);
    let selectedIso = selected ? selected.slice(0, 10) : '';
    let viewIso = selectedIso || today;
    let view = dateFromIso(viewIso);

    weekdays.forEach((weekday) => {
        const day = document.createElement('span');
        day.setAttribute('role', 'columnheader');
        day.textContent = weekday;
        weekdayRow.append(day);
    });

    const close = (restoreFocus = false) => {
        calendar.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) trigger.focus();
    };

    const render = (focusIso = selectedIso || today) => {
        const year = view.getFullYear();
        const month = view.getMonth();
        title.textContent = `${monthNames[month]} ${year + 543}`;
        daysGrid.replaceChildren();

        const firstDayOffset = new Date(year, month, 1).getDay();
        const firstCell = new Date(year, month, 1 - firstDayOffset);
        for (let week = 0; week < 6; week += 1) {
            const row = document.createElement('div');
            row.className = 'localized-calendar__week';
            row.setAttribute('role', 'row');
            for (let weekday = 0; weekday < 7; weekday += 1) {
                const index = week * 7 + weekday;
                const date = new Date(firstCell.getFullYear(), firstCell.getMonth(), firstCell.getDate() + index);
                const iso = dateToIso(date);
                const cell = document.createElement('div');
                cell.setAttribute('role', 'gridcell');
                const dayButton = document.createElement('button');
                dayButton.type = 'button';
                dayButton.className = 'localized-calendar__day';
                if (date.getMonth() !== month) dayButton.classList.add('is-outside-month');
                if (iso === selectedIso) dayButton.classList.add('is-selected');
                if (iso === today) dayButton.classList.add('is-today');
                dayButton.dataset.date = iso;
                dayButton.textContent = String(date.getDate());
                dayButton.setAttribute('aria-label', `${date.getDate()} ${monthNames[date.getMonth()]} พ.ศ. ${date.getFullYear() + 543}`);
                dayButton.setAttribute('aria-pressed', iso === selectedIso ? 'true' : 'false');
                dayButton.tabIndex = iso === focusIso ? 0 : -1;
                dayButton.addEventListener('click', () => choose(iso));
                cell.append(dayButton);
                row.append(cell);
            }
            daysGrid.append(row);
        }

        if (calendar.getBoundingClientRect().height && ! calendar.hidden) {
            daysGrid.querySelector(`[data-date="${focusIso}"]`)?.focus();
        }
    };

    const open = () => {
        const parsed = parseDisplayDate(field.value, dateTime);
        selectedIso = parsed ? parsed.slice(0, 10) : '';
        viewIso = selectedIso || today;
        view = dateFromIso(viewIso);
        calendar.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        render();
        const bounds = wrap.getBoundingClientRect();
        calendar.classList.toggle('align-right', window.innerWidth - bounds.left < 360);
        fitPopoverToViewport(calendar);
        daysGrid.querySelector(`[data-date="${selectedIso || today}"]`)?.focus();
    };

    const choose = (iso) => {
        const parsed = parseDisplayDate(field.value, dateTime);
        const time = dateTime && parsed ? parsed.slice(11, 16) : '';
        field.value = `${formatDate(iso)}${dateTime && time ? ` ${time}` : ''}`;
        selectedIso = iso;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        field.focus();
    };

    const moveMonth = (amount) => {
        view = new Date(view.getFullYear(), view.getMonth() + amount, 1);
        render(dateToIso(view));
    };

    calendar.querySelector('[data-calendar-previous]').addEventListener('click', () => moveMonth(-1));
    calendar.querySelector('[data-calendar-next]').addEventListener('click', () => moveMonth(1));
    calendar.querySelector('[data-calendar-today]').addEventListener('click', () => choose(today));
    calendar.querySelector('[data-calendar-clear]').addEventListener('click', () => {
        field.value = '';
        selectedIso = '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        field.focus();
    });

    daysGrid.addEventListener('keydown', (event) => {
        const current = event.target.closest('[data-date]');
        if (! current) return;
        const date = dateFromIso(current.dataset.date);
        let next = null;
        if (event.key === 'ArrowLeft') next = new Date(date.getFullYear(), date.getMonth(), date.getDate() - 1);
        if (event.key === 'ArrowRight') next = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1);
        if (event.key === 'ArrowUp') next = new Date(date.getFullYear(), date.getMonth(), date.getDate() - 7);
        if (event.key === 'ArrowDown') next = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 7);
        if (event.key === 'Home') next = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay());
        if (event.key === 'End') next = new Date(date.getFullYear(), date.getMonth(), date.getDate() + (6 - date.getDay()));
        if (event.key === 'PageUp') next = new Date(date.getFullYear(), date.getMonth() - 1, date.getDate());
        if (event.key === 'PageDown') next = new Date(date.getFullYear(), date.getMonth() + 1, date.getDate());
        if (! next) return;

        event.preventDefault();
        view = new Date(next.getFullYear(), next.getMonth(), 1);
        render(dateToIso(next));
    });

    calendar.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close(true);
    });
    document.addEventListener('pointerdown', (event) => {
        if (! wrap.contains(event.target)) close();
    });
    trigger.addEventListener('click', () => {
        if (calendar.hidden) open();
        else close();
    });
    return calendar;
}

function attachThaiTimePicker(wrap, field, trigger) {
    const picker = document.createElement('div');
    picker.className = 'localized-time-picker';
    picker.hidden = true;
    picker.setAttribute('role', 'dialog');
    picker.setAttribute('aria-label', 'เลือกเวลาแบบ 24 ชั่วโมง');
    picker.innerHTML = `
        <h2 class="localized-time-picker__title">เลือกเวลา (24 ชั่วโมง)</h2>
        <div class="localized-time-picker__fields">
            <label>ชั่วโมง<select data-time-hour aria-label="ชั่วโมง"></select></label>
            <span aria-hidden="true">:</span>
            <label>นาที<select data-time-minute aria-label="นาที"></select></label>
        </div>
        <div class="localized-time-picker__footer">
            <button type="button" class="localized-calendar__text-button" data-time-clear>ล้าง</button>
            <button type="button" class="localized-time-picker__apply" data-time-apply>ใช้เวลานี้</button>
        </div>`;
    const hours = picker.querySelector('[data-time-hour]');
    const minutes = picker.querySelector('[data-time-minute]');
    for (let hour = 0; hour < 24; hour += 1) {
        const option = document.createElement('option');
        option.value = String(hour).padStart(2, '0');
        option.textContent = option.value;
        hours.append(option);
    }
    for (let minute = 0; minute < 60; minute += 1) {
        const option = document.createElement('option');
        option.value = String(minute).padStart(2, '0');
        option.textContent = option.value;
        minutes.append(option);
    }
    wrap.append(picker);

    const close = (restoreFocus = false) => {
        picker.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) trigger.focus();
    };
    const open = () => {
        const current = parseDisplayTime(field.value) || '00:00';
        [hours.value, minutes.value] = current.split(':');
        picker.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        const bounds = wrap.getBoundingClientRect();
        picker.classList.toggle('align-right', window.innerWidth - bounds.left < 300);
        fitPopoverToViewport(picker);
        hours.focus();
    };
    const apply = (value) => {
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        field.focus();
    };

    picker.querySelector('[data-time-apply]').addEventListener('click', () => apply(`${hours.value}:${minutes.value}`));
    picker.querySelector('[data-time-clear]').addEventListener('click', () => apply(''));
    picker.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close(true);
    });
    document.addEventListener('pointerdown', (event) => {
        if (! wrap.contains(event.target)) close();
    });
    trigger.addEventListener('click', () => {
        if (picker.hidden) open();
        else close();
    });
}

function fitPopoverToViewport(popover, padding = 12) {
    popover.style.transform = '';
    const bounds = popover.getBoundingClientRect();
    const shift = bounds.left < padding
        ? padding - bounds.left
        : (bounds.right > window.innerWidth - padding ? window.innerWidth - padding - bounds.right : 0);
    if (shift) popover.style.transform = `translateX(${shift}px)`;
}

function bangkokTodayIso() {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
    return `${values.year}-${values.month}-${values.day}`;
}

function dateFromIso(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date();
}

function dateToIso(date) {
    return `${String(date.getFullYear()).padStart(4, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function toDateValue(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

function toDateTimeValue(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
    return match ? match[1] : '';
}

function toTimeValue(value) {
    const match = String(value || '').match(/^(\d{1,2}:\d{2})/);
    if (! match) return '';
    const [hour, minute] = match[1].split(':');
    return `${hour.padStart(2, '0')}:${minute}`;
}

function formatDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? `${match[3]}/${match[2]}/${String(Number(match[1]) + 543).padStart(4, '0')}` : '';
}

function formatDateTime(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})$/);
    return match ? `${formatDate(match[1])} ${match[2]}:${match[3]}` : '';
}

function formatTime(value) {
    return toTimeValue(value);
}

function parseDisplayDate(value, dateTime) {
    const expression = dateTime
        ? /^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/
        : /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;
    const match = normalizeThaiDigits(value).trim().match(expression);
    if (! match) return '';

    const [, dayText, monthText, yearText, hourText, minuteText] = match;
    const day = Number(dayText);
    const month = Number(monthText);
    const enteredYear = Number(yearText);
    const year = enteredYear >= 2400 ? enteredYear - 543 : enteredYear;
    if (year < 1 || year > 9999) return '';
    const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    const daysInMonth = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if (month < 1 || month > 12 || day < 1 || day > daysInMonth[month - 1]) return '';

    const date = `${String(year).padStart(4, '0')}-${monthText.padStart(2, '0')}-${dayText.padStart(2, '0')}`;
    if (! dateTime) return date;
    const hour = Number(hourText);
    const minute = Number(minuteText);
    if (hour > 23 || minute > 59) return '';
    return `${date}T${String(hour).padStart(2, '0')}:${minuteText}`;
}

function parseDisplayTime(value) {
    const match = normalizeThaiDigits(value).trim().match(/^(\d{1,2}):(\d{2})$/);
    if (! match) return '';
    const hour = Number(match[1]);
    const minute = Number(match[2]);
    return hour <= 23 && minute <= 59
        ? `${String(hour).padStart(2, '0')}:${match[2]}`
        : '';
}

function normalizeThaiDigits(value) {
    return String(value || '').replace(/[๐-๙]/g, (digit) => String('๐๑๒๓๔๕๖๗๘๙'.indexOf(digit)));
}

function validateTemporalField(field, timeOnly, dateTime) {
    if (timeOnly) {
        const value = field.value.trim();
        const valid = value === '' || parseDisplayTime(value) !== '';
        field.setCustomValidity(valid ? '' : 'กรุณากรอกเวลาแบบ 24 ชั่วโมง HH:mm');
        field.setAttribute('aria-invalid', valid ? 'false' : 'true');
        return;
    }

    validateDateField(field, dateTime);
}

function validateDateField(field, dateTime) {
    const value = field.value.trim();
    const valid = value === '' || parseDisplayDate(value, dateTime) !== '';
    field.setCustomValidity(valid ? '' : (dateTime ? 'กรุณากรอกวันที่และเวลาเป็น dd/mm/yyyy HH:mm' : 'กรุณากรอกวันที่เป็น dd/mm/yyyy (พ.ศ. หรือ ค.ศ.)'));
    field.setAttribute('aria-invalid', valid ? 'false' : 'true');
}
