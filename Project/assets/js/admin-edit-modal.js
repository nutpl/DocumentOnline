(() => {
    const openEditModal = (link) => {
        const overlay = document.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', link.dataset.editModalTitle || 'แก้ไขข้อมูล');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.58);backdrop-filter:blur(3px);';
        const url = new URL(link.href, window.location.href);
        url.searchParams.set('embed', '1');
        overlay.innerHTML = '<section style="width:660px;max-width:calc(100vw - 32px);height:650px;max-height:calc(100dvh - 32px);overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.35);"><div style="height:48px;display:flex;align-items:center;justify-content:space-between;padding:0 14px;border-bottom:1px solid #e2e8f0;background:#fff;"><strong style="font:700 15px system-ui;color:#0f172a;">' + (link.dataset.editModalTitle || 'แก้ไขข้อมูล') + '</strong><button type="button" aria-label="ปิด" style="width:34px;height:34px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#64748b;font-size:18px;cursor:pointer;">×</button></div><iframe title="' + (link.dataset.editModalTitle || 'แก้ไขข้อมูล') + '" src="' + url.href + '" style="display:block;width:100%;height:calc(100% - 48px);border:0;"></iframe></section>';
        const close = () => overlay.remove();
        const closeButton = overlay.querySelector('button');
        const frame = overlay.querySelector('iframe');
        let hasLoaded = false;
        closeButton.addEventListener('click', close);
        overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
        document.addEventListener('keydown', function onKey(event) { if (event.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); } });
        frame.addEventListener('load', () => {
            if (!hasLoaded) { hasLoaded = true; return; }
            try {
                if (frame.contentWindow.location.pathname === window.location.pathname) window.location.reload();
            } catch (_) { /* Same-origin pages are expected; retain the modal if unavailable. */ }
        });
        document.body.appendChild(overlay);
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-admin-edit-modal]');
        if (!link) return;
        event.preventDefault();
        openEditModal(link);
    });
})();
