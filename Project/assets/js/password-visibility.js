(() => {
    const eyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.3 12s3.5-6 9.7-6 9.7 6 9.7 6-3.5 6-9.7 6-9.7-6-9.7-6Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
    const eyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 6.2A10.7 10.7 0 0 1 12 6c6.2 0 9.7 6 9.7 6a18.1 18.1 0 0 1-3.1 3.7M6.2 6.2A18 18 0 0 0 2.3 12S5.8 18 12 18c1.1 0 2.1-.2 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    const attachToggle = (input) => {
        if (input.dataset.passwordToggleReady === 'true') return;
        input.dataset.passwordToggleReady = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'password-visibility-field';
        wrapper.style.position = 'relative';
        wrapper.style.width = '100%';
        input.parentNode?.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.style.paddingRight = '3.25rem';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-visibility-toggle';
        button.setAttribute('aria-label', 'แสดงรหัสผ่าน');
        button.setAttribute('title', 'แสดงรหัสผ่าน');
        button.innerHTML = eyeIcon;
        Object.assign(button.style, {
            position: 'absolute', right: '0.45rem', top: '50%', transform: 'translateY(-50%)',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '2.5rem', height: '2.5rem',
            border: '0', borderRadius: '0.5rem', background: 'transparent', color: '#64748b', cursor: 'pointer'
        });
        const icon = button.querySelector('svg');
        if (icon) Object.assign(icon.style, { width: '1.25rem', height: '1.25rem' });

        button.addEventListener('click', () => {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.setAttribute('aria-label', visible ? 'แสดงรหัสผ่าน' : 'ซ่อนรหัสผ่าน');
            button.setAttribute('title', visible ? 'แสดงรหัสผ่าน' : 'ซ่อนรหัสผ่าน');
            button.innerHTML = visible ? eyeIcon : eyeOffIcon;
            const nextIcon = button.querySelector('svg');
            if (nextIcon) Object.assign(nextIcon.style, { width: '1.25rem', height: '1.25rem' });
        });
        wrapper.appendChild(button);
    };

    const initialize = () => document.querySelectorAll('input[type="password"]').forEach(attachToggle);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
