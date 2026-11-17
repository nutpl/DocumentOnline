(() => {
    const loadSweetAlert = () => {
        if (window.Swal) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-success-alert-loader]');
            if (existing) {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', reject, { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            script.dataset.successAlertLoader = '1';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    };

    const cleanQueryParameters = (value) => {
        if (!value) return;
        const url = new URL(window.location.href);
        value.split(',').map(item => item.trim()).filter(Boolean).forEach(item => url.searchParams.delete(item));
        window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
    };

    const showAlerts = async () => {
        const alerts = [...document.querySelectorAll('[data-success-alert]')];
        if (!alerts.length) return;
        alerts.forEach(element => { element.hidden = true; });

        try {
            await loadSweetAlert();
        } catch (error) {
            alerts.forEach(element => { element.hidden = false; });
            return;
        }

        for (const element of alerts) {
            const actionUrl = element.dataset.successUrl || '';
            const secondaryActionUrl = element.dataset.successSecondaryUrl || '';
            const timer = Number(element.dataset.successTimer || (actionUrl ? 0 : 2200));
            const options = {
                icon: element.dataset.successIcon || 'success',
                title: element.dataset.successTitle || 'ดำเนินการสำเร็จ',
                text: element.dataset.successText || element.textContent.trim(),
                confirmButtonText: element.dataset.successConfirm || 'ตกลง',
                confirmButtonColor: '#b91c1c',
            };
            if (timer > 0) {
                options.timer = timer;
                options.timerProgressBar = true;
            }
            if (actionUrl) {
                options.showCancelButton = true;
                options.cancelButtonText = 'ปิด';
                options.confirmButtonText = element.dataset.successConfirm || 'เปิดไฟล์';
            }
            if (secondaryActionUrl) {
                options.showDenyButton = true;
                options.denyButtonText = element.dataset.successSecondaryConfirm || 'ดาวน์โหลด Word';
                options.denyButtonColor = '#2563eb';
            }

            const result = await window.Swal.fire(options);
            if (actionUrl && result.isConfirmed) window.location.href = actionUrl;
            if (secondaryActionUrl && result.isDenied) window.location.href = secondaryActionUrl;
            cleanQueryParameters(element.dataset.successCleanParam || '');
            element.remove();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showAlerts, { once: true });
    } else {
        showAlerts();
    }
})();
