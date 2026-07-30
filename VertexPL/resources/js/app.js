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
    document.querySelectorAll('[data-flash-message]').forEach((element) => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: element.dataset.flashType || 'success',
            title: element.dataset.flashMessage,
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
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
                form.submit();
            }
        });
    });
});
