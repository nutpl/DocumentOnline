(() => {
    document.title = 'ระบบจัดการเอกสารคำร้อง | คณะวิศวกรรมศาสตร์และเทคโนโลยี';

    const faviconPath = '/Project/assets/images/fet-logo-transparent.png?v=20260902-1';
    let favicon = document.querySelector('link[rel~="icon"]');
    if (!favicon) {
        favicon = document.createElement('link');
        favicon.rel = 'icon';
        document.head.appendChild(favicon);
    }
    favicon.type = 'image/png';
    favicon.href = faviconPath;
})();
