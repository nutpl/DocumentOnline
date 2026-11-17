(() => {
    'use strict';

    const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

    const apiUrl = sourceUrl => {
        const url = new URL(sourceUrl, window.location.href);
        url.searchParams.set('pull_async', '1');
        return url.toString();
    };

    const downloadFile = url => {
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = '';
        anchor.style.display = 'none';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
    };

    const friendlyError = async message => {
        if (window.Swal) {
            await window.Swal.fire({
                icon: 'error',
                title: 'สร้างไฟล์ PDF ไม่สำเร็จ',
                text: message || 'ระบบไม่สามารถสร้าง PDF ได้ กรุณาลองใหม่อีกครั้ง',
                confirmButtonText: 'กลับ',
            });
        } else {
            window.alert(message || 'ระบบไม่สามารถสร้าง PDF ได้ กรุณาลองใหม่อีกครั้ง');
        }
    };

    const requestJob = async sourceUrl => {
        const response = await fetch(apiUrl(sourceUrl), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('ระบบตอบกลับไม่ถูกต้อง');
        }
        const payload = await response.json();
        if (!response.ok || !payload.status_url || !payload.download_url) {
            throw new Error(payload.message || payload.error || 'ไม่สามารถสร้างงาน PDF ได้');
        }
        return payload;
    };

    const waitForJob = async job => {
        if (!window.Swal) {
            throw new Error('ไม่สามารถเปิดหน้าต่างแสดงสถานะได้ กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง');
        }

        let closedByDownload = false;
        window.Swal.fire({
            title: 'กำลังสร้าง PDF ด้วย Microsoft Word',
            text: 'โปรดรอสักครู่ ระบบจะดาวน์โหลดไฟล์ให้อัตโนมัติ',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => window.Swal.showLoading(),
            willClose: () => { closedByDownload = true; },
        });

        while (!closedByDownload) {
            await wait(1000);
            const response = await fetch(job.status_url, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('ไม่สามารถตรวจสอบสถานะการสร้าง PDF ได้');
            const state = await response.json();
            if (state.status === 'completed') {
                closedByDownload = true;
                window.Swal.close();
                downloadFile(job.download_url);
                window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'เริ่มดาวน์โหลด PDF แล้ว',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                });
                return;
            }
            if (state.status === 'failed') {
                closedByDownload = true;
                window.Swal.close();
                throw new Error(state.message || 'Windows Word worker ไม่สามารถแปลงเอกสารได้');
            }
        }
    };

    const start = async sourceUrl => {
        try {
            const job = await requestJob(sourceUrl);
            await waitForJob(job);
        } catch (error) {
            if (window.Swal?.isVisible()) window.Swal.close();
            await friendlyError(error?.message);
        }
    };

    window.officialPdfPullDownload = start;
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-official-pdf-pull]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        start(link.href);
    });
})();
