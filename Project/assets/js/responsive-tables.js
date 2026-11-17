(() => {
    const makeTablesScrollable = () => {
        document.querySelectorAll('main table').forEach((table) => {
            if (table.closest('.overflow-x-auto, .responsive-table-scroll')) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'responsive-table-scroll';
            wrapper.setAttribute('data-responsive-table-scroll', '');
            table.parentNode?.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', makeTablesScrollable, { once: true });
    } else {
        makeTablesScrollable();
    }
})();
