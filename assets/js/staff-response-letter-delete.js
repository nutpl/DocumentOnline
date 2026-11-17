(() => {
    const page = window.location.pathname.split('/').pop();
    if (page !== 'response_letters.php') return;

    const showMessage = (icon, title, text) => {
        if (window.Swal) {
            window.Swal.fire({ icon, title, text, confirmButtonText: 'ตกลง', confirmButtonColor: icon === 'success' ? '#047857' : '#dc2626' });
        } else {
            window.alert(`${title}\n${text}`);
        }
    };

    const resultMessages = {
        deleted: ['success', 'ลบใบตอบรับแล้ว', 'นักศึกษาสามารถอัปโหลดใบตอบรับฉบับใหม่ได้'],
        send_exists: ['warning', 'ลบใบตอบรับไม่ได้', 'รายการนี้ออกใบส่งตัวแล้ว จึงไม่สามารถลบใบตอบรับเพื่อป้องกันเอกสารไม่ตรงกัน'],
        not_found: ['error', 'ไม่พบใบตอบรับ', 'รายการอาจถูกลบไปแล้ว หรือไม่อยู่ในสาขาที่รับผิดชอบ'],
        failed: ['error', 'ลบใบตอบรับไม่สำเร็จ', 'กรุณาลองใหม่อีกครั้ง'],
        invalid: ['error', 'ดำเนินการไม่ถูกต้อง', 'กรุณากลับไปเลือกใบตอบรับใหม่'],
    };

    const query = new URLSearchParams(window.location.search);
    const result = query.get('response_delete');
    if (result && resultMessages[result]) {
        const [icon, title, text] = resultMessages[result];
        showMessage(icon, title, text);
        query.delete('response_delete');
        const clean = `${window.location.pathname}${query.toString() ? `?${query}` : ''}${window.location.hash}`;
        window.history.replaceState({}, '', clean);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const csrf = document.querySelector('meta[name="staff-csrf-token"]')?.content
            || document.querySelector('input[name="csrf_token"]')?.value;
        if (!csrf) return;

        document.querySelectorAll('a[href*="download_response_letter.php?id="]').forEach((link) => {
            const url = new URL(link.href, window.location.href);
            const responseId = url.searchParams.get('id');
            if (!responseId || link.parentElement?.querySelector('[data-delete-response-letter]')) return;

            const form = document.createElement('form');
            form.method = 'post';
            form.action = 'delete_response_letter.php';
            form.className = 'w-full';
            form.dataset.deleteResponseLetter = '1';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrf;
            form.append(csrfInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'response_document_id';
            idInput.value = responseId;
            form.append(idInput);

            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'group inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-red-100 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-red-300';
            button.innerHTML = '<i class="fa-solid fa-trash-can text-red-500 transition group-hover:text-red-700"></i><span>ลบไฟล์เดิม</span><i class="fa-solid fa-arrow-rotate-right text-xs text-red-400"></i>';
            form.append(button);

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const studentName = link.closest('article')?.querySelector('h3')?.textContent?.trim() || 'นักศึกษารายนี้';
                const proceed = () => form.submit();
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'ลบใบตอบรับเดิม?',
                        html: `ใบตอบรับของ <b>${studentName.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]))}</b> จะถูกลบ เพื่อให้นักศึกษาอัปโหลดฉบับใหม่`,
                        showCancelButton: true,
                        confirmButtonText: 'ลบใบตอบรับ',
                        cancelButtonText: 'ยกเลิก',
                        confirmButtonColor: '#dc2626',
                    }).then((answer) => { if (answer.isConfirmed) proceed(); });
                } else if (window.confirm(`ลบใบตอบรับของ ${studentName} เพื่อให้นักศึกษาอัปโหลดใหม่หรือไม่?`)) {
                    proceed();
                }
            });

            const actionGroup = document.createElement('div');
            actionGroup.className = 'w-full shrink-0 rounded-2xl border border-slate-200 bg-slate-50 p-2 sm:w-52';

            const hint = document.createElement('p');
            hint.className = 'mb-2 px-1 text-center text-xs leading-5 text-slate-500';
            hint.textContent = 'ต้องการให้ผู้เรียนอัปโหลดใบตอบรับฉบับใหม่';

            link.classList.remove('shrink-0');
            link.classList.add('w-full');
            link.parentElement?.insertBefore(actionGroup, link);
            actionGroup.append(link, hint, form);
        });
    });
})();
