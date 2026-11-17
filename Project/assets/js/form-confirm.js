(function () {
    'use strict';

    const nativeSubmit = HTMLFormElement.prototype.submit;
    const ignoredNames = new Set(['csrf_token', 'action', 'id', 'user_id', 'employee_id', 'document_id']);

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        })[char]);
    }

    function fieldLabel(field) {
        const label = field.closest('label');
        if (label) {
            const clone = label.cloneNode(true);
            clone.querySelectorAll('input,select,textarea,button,[data-field-hint]').forEach(element => element.remove());
            const text = clone.textContent.trim();
            if (text) return text;
        }
        return field.dataset.label || field.name.replaceAll('_', ' ');
    }

    function fieldValue(field, showPassword = false) {
        if (field.type === 'password') return field.value ? (showPassword ? field.value : 'กรอกแล้ว (ไม่แสดงรหัสผ่าน)') : '';
        if (field.type === 'checkbox' || field.type === 'radio') return field.checked ? 'เลือก' : '';
        if (field.tagName === 'SELECT') return field.selectedOptions[0]?.textContent.trim() || '';
        return field.value.trim();
    }

    function formRows(form) {
        const rows = [];
        const handledRadioNames = new Set();
        const showPassword = form.dataset.confirmShowPassword === 'true';
        form.querySelectorAll('input,select,textarea').forEach(field => {
            if (!field.name || field.disabled || ignoredNames.has(field.name) || field.type === 'hidden') return;
            if (field.name === 'confirm_password') return;
            if (field.type === 'radio') {
                if (handledRadioNames.has(field.name)) return;
                handledRadioNames.add(field.name);
                field = form.querySelector(`input[type="radio"][name="${CSS.escape(field.name)}"]:checked`) || field;
            }
            const value = fieldValue(field, showPassword);
            if (!value) return;
            rows.push([fieldLabel(field), value]);
        });
        return rows;
    }

    function tableRows(element) {
        const row = element.closest('tr');
        if (!row) return [];
        const headers = Array.from(row.closest('table')?.querySelectorAll('thead th') || []);
        return Array.from(row.children).slice(0, -1).map((cell, index) => [
            headers[index]?.textContent.trim() || `ข้อมูล ${index + 1}`,
            cell.querySelector('input,select')?.value?.trim() || cell.textContent.trim()
        ]).filter(item => item[1]);
    }

    function summaryHtml(rows) {
        if (!rows.length) return '<p class="text-gray-500">โปรดตรวจสอบรายการนี้ก่อนยืนยัน</p>';
        return `<div style="max-height:250px;overflow:auto;text-align:left;border:1px solid #e5e7eb;border-radius:8px">${rows.map(([label, value]) =>
            `<div style="display:grid;grid-template-columns:minmax(96px,32%) 1fr;gap:8px;padding:7px 10px;border-bottom:1px solid #f3f4f6"><strong>${escapeHtml(label)}</strong><span style="overflow-wrap:anywhere">${escapeHtml(value)}</span></div>`
        ).join('')}</div>`;
    }

    function classify(form, submitter) {
        const explicit = submitter?.dataset.confirmAction || form.dataset.confirmAction;
        if (explicit) return explicit;
        const action = (form.getAttribute('action') || location.pathname).toLowerCase();
        const command = `${form.elements.action?.value || ''} ${submitter?.value || ''} ${submitter?.textContent || ''} ${submitter?.title || ''}`.toLowerCase();
        if (action.includes('register_process') || command.includes('สมัครสมาชิก')) return 'register';
        if (action.includes('delete.php') || /(^|\s)(delete|ลบ)(\s|$)/.test(command)) return 'delete';
        if (/(store\.php|(?:\/|_)add\.php|\/create\.php)/.test(action) || /(^|\s)(create|add|เพิ่ม)(\s|$)/.test(command)) return 'create';
        if (/(update\.php|save_.*\.php|toggle_.*\.php)/.test(action) || /(^|\s)(save|update|บันทึก|แก้ไข)(\s|$)/.test(command)) return 'save';
        return null;
    }

    function entityName(element, rows) {
        if (!element) return '';
        const explicit = element.dataset.confirmName || element.dataset.name || element.dataset.fullname;
        if (explicit) return explicit.trim();

        const container = element.closest('tr, .card, [data-record]');
        const detail = container?.querySelector('[data-firstname], [data-confirm-name], [data-name]');
        if (detail) {
            const fullName = detail.dataset.confirmName
                || detail.dataset.name
                || `${detail.dataset.firstname || ''} ${detail.dataset.lastname || ''}`;
            if (fullName.trim()) return fullName.trim();
        }

        const nameRow = rows.find(([label]) => /ชื่อ|name|รายการ|เอกสาร|คณะ|สาขา|กลุ่ม/i.test(label));
        return nameRow?.[1]?.replace(/\s+/g, ' ').trim() || '';
    }

    async function confirmAction(type, rows, name = '') {
        const settings = {
            create: ['ยืนยันการเพิ่มข้อมูล?', 'เพิ่มข้อมูล', '#dc2626'],
            register: ['ยืนยันการสมัครสมาชิก?', 'สมัครสมาชิก', '#dc2626'],
            save: ['ยืนยันการบันทึกข้อมูล?', 'บันทึกข้อมูล', '#2563eb'],
            return: [name ? `ส่งคำร้องของ “${name}” กลับให้แก้ไขใช่ไหม?` : 'ส่งคำร้องกลับให้แก้ไข?', 'ส่งกลับให้แก้ไข', '#b45309'],
            delete: [name ? `คุณต้องการลบ “${name}” ใช่ไหม?` : 'ยืนยันการลบข้อมูล?', 'ลบข้อมูล', '#b91c1c']
        }[type];
        if (!settings) return false;
        const result = await Swal.fire({
            icon: (type === 'delete' || type === 'return') ? 'warning' : 'question',
            title: settings[0],
            html: summaryHtml(rows),
            showCancelButton: true,
            confirmButtonText: settings[1],
            cancelButtonText: 'กลับไปตรวจสอบ',
            confirmButtonColor: settings[2],
            cancelButtonColor: '#6b7280',
            focusCancel: type === 'delete' || type === 'return',
            width: 500
        });
        return result.isConfirmed;
    }

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === 'true' || form.dataset.confirmSkip === 'true') return;
        const type = classify(form, event.submitter);
        if (!type || typeof Swal === 'undefined') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const swalRequiredField = form.querySelector('[data-swal-required]');
        if (swalRequiredField && !fieldValue(swalRequiredField)) {
            await Swal.fire({
                icon: 'warning',
                title: swalRequiredField.dataset.swalTitle || 'กรุณากรอกข้อมูลให้ครบถ้วน',
                text: swalRequiredField.dataset.swalText || 'กรุณาระบุข้อมูลก่อนดำเนินการต่อ',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#dc2626'
            });
            swalRequiredField.focus();
            return;
        }
        const password = form.querySelector('[name="password"]');
        const confirmation = form.querySelector('[name="confirm_password"]');
        if (password && confirmation && password.value !== confirmation.value) {
            await Swal.fire({
                icon: 'error',
                title: 'รหัสผ่านไม่ตรงกัน',
                text: 'กรุณาตรวจสอบรหัสผ่านและยืนยันรหัสผ่านอีกครั้ง',
                confirmButtonColor: '#dc2626'
            });
            confirmation.focus();
            return;
        }
        const rows = formRows(form);
        if (await confirmAction(type, rows, (type === 'delete' || type === 'return') ? entityName(event.submitter || form, rows) : '')) {
            form.dataset.confirmed = 'true';
            if (event.submitter?.name && !form.querySelector(`input[type="hidden"][data-confirm-submitter="${CSS.escape(event.submitter.name)}"]`)) {
                const submitValue = document.createElement('input');
                submitValue.type = 'hidden';
                submitValue.name = event.submitter.name;
                submitValue.value = event.submitter.value;
                submitValue.dataset.confirmSubmitter = event.submitter.name;
                form.appendChild(submitValue);
            }
            nativeSubmit.call(form);
        }
    }, true);

    document.addEventListener('click', async event => {
        const deleteControl = event.target.closest('[data-confirm-action="delete"], .deleteBtn');
        if (deleteControl && deleteControl.dataset.confirmed !== 'true') {
            if (typeof Swal === 'undefined') return;
            const href = deleteControl.getAttribute('href');
            const id = deleteControl.dataset.id;
            const destination = href || deleteControl.dataset.deleteUrl || (id ? `delete.php?id=${encodeURIComponent(id)}` : '');
            if (!destination) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            const rows = tableRows(deleteControl);
            if (await confirmAction('delete', rows, entityName(deleteControl, rows))) {
                deleteControl.dataset.confirmed = 'true';
                location.href = destination;
            }
            return;
        }

        const link = event.target.closest('a[href]');
        if (!link || link.dataset.confirmed === 'true') return;
        const href = link.getAttribute('href') || '';
        const explicit = link.dataset.confirmAction;
        if (explicit !== 'delete' && !/(^|\/)delete\.php(?:\?|$)/i.test(href)) return;
        if (typeof Swal === 'undefined') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const rows = tableRows(link);
        if (await confirmAction('delete', rows, entityName(link, rows))) {
            link.dataset.confirmed = 'true';
            location.href = link.href;
        }
    }, true);
})();
