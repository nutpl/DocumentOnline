(() => {
    const key = 'rmuti-theme';
    const preferredTheme = () => {
        try {
            const saved = localStorage.getItem(key);
            if (saved === 'dark' || saved === 'light') return saved;
        } catch (_) {}
        return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };
    const applyTheme = (theme) => {
        const dark = theme === 'dark';
        document.documentElement.classList.toggle('theme-dark', dark);
        document.body?.classList.toggle('theme-dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const label = dark ? 'เปลี่ยนเป็นโหมดสว่าง' : 'เปลี่ยนเป็นโหมดมืด';
            button.setAttribute('title', label);
            button.setAttribute('aria-label', label);
            button.setAttribute('aria-pressed', String(dark));
            const icon = button.querySelector('[data-theme-icon]');
            if (icon) icon.className = `fa-solid ${dark ? 'fa-sun' : 'fa-moon'}`;
            const text = button.querySelector('[data-theme-label]');
            if (text) text.textContent = label;
        });
    };
    const init = () => {
        applyTheme(preferredTheme());
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-theme-toggle]');
            if (!button) return;
            const next = document.documentElement.classList.contains('theme-dark') ? 'light' : 'dark';
            try { localStorage.setItem(key, next); } catch (_) {}
            applyTheme(next);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
