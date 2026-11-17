(() => {
    const isOfficialDocumentsPage = () => {
        const query = new URLSearchParams(window.location.search);
        return window.location.pathname.endsWith('/official_documents.php')
            && ['send', 'request'].includes(query.get('type'));
    };

    if (!isOfficialDocumentsPage()) return;

    document.addEventListener('DOMContentLoaded', () => {
        const pageType = new URLSearchParams(window.location.search).get('type');
        const isSend = pageType === 'send';
        const boxes = [...document.querySelectorAll('.student-checkbox')];
        if (!boxes.length) return;

        const groups = [...new Set(boxes.map((box) => box.closest('div.overflow-hidden.rounded-xl.border')))].filter(Boolean);
        const selectionSection = groups[0]?.closest('section');
        if (!selectionSection) return;

        groups.forEach((group) => {
            const list = [...group.children].find((child) => child.classList.contains('divide-y'));
            const header = [...group.children].find((child) => child !== list);
            if (!list || !header) return;

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = `${header.className} w-full cursor-pointer text-left transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-300`;
            toggle.setAttribute('aria-expanded', 'false');
            while (header.firstChild) toggle.append(header.firstChild);

            const icon = document.createElement('span');
            icon.className = 'ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-red-700 shadow-sm';
            icon.innerHTML = '<i class="fa-solid fa-chevron-down text-xs transition-transform"></i>';
            toggle.append(icon);

            const company = group.querySelector('.student-checkbox')?.dataset.company || '';
            const companyState = isSend ? window.officialSendCompanyStates?.[company] : null;
            if (companyState?.sent_count) {
                const complete = companyState.sent_count === companyState.total_count;
                const status = document.createElement('span');
                status.className = complete
                    ? 'mr-2 shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800'
                    : 'mr-2 shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800';
                status.innerHTML = complete
                    ? '<i class="fa-solid fa-circle-check mr-1"></i>สร้างใบส่งตัวแล้ว'
                    : `<i class="fa-solid fa-circle-half-stroke mr-1"></i>สร้างแล้ว ${companyState.sent_count}/${companyState.total_count}`;
                toggle.insertBefore(status, icon);

                const sentIds = new Set(companyState.sent_profile_ids.map(String));
                group.querySelectorAll('.student-checkbox').forEach((box) => {
                    if (!sentIds.has(box.value)) return;
                    box.checked = false;
                    box.disabled = true;
                    const row = box.closest('label');
                    row?.classList.add('cursor-not-allowed', 'bg-emerald-50/60', 'opacity-70');
                    const note = document.createElement('span');
                    note.className = 'ml-auto rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800';
                    note.textContent = 'ออกใบส่งตัวแล้ว';
                    row?.append(note);
                });
            }

            header.replaceWith(toggle);
            list.classList.add('hidden');

            const setOpen = (open) => {
                list.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', String(open));
                icon.querySelector('i')?.classList.toggle('rotate-180', open);
            };
            toggle.addEventListener('click', () => setOpen(list.classList.contains('hidden')));
            group.dataset.openCompany = '0';
            group._setCompanyOpen = setOpen;
        });

        // Remove the legacy bottom submit action. The contextual action bar
        // below is the single create button shown only after a selection.
        document.querySelectorAll('form[data-confirm-form] div.flex.justify-end').forEach((element) => {
            if (element.querySelector('button[type="submit"]')) element.remove();
        });
        document.querySelectorAll('form[data-confirm-form] button').forEach((button) => {
            if (button.textContent.trim() === 'สร้างหนังสือราชการ') button.closest('div')?.remove();
        });

        const actionBar = document.createElement('div');
        actionBar.className = 'sticky bottom-4 z-10 mb-4 hidden rounded-2xl border border-red-200 bg-white/95 p-3 shadow-lg backdrop-blur sm:flex sm:items-center sm:justify-between sm:gap-4';
        actionBar.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-building-circle-check"></i></span>
                <div><p class="text-sm font-bold text-slate-900" data-selection-title>เลือกสถานประกอบการแล้ว</p><p class="text-xs text-slate-500" data-selection-detail></p></div>
            </div>
            <button type="submit" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200 sm:mt-0 sm:w-auto"><i class="fa-solid fa-file-circle-plus mr-2"></i>${isSend ? 'สร้างใบส่งตัว' : 'สร้างหนังสือขอความอนุเคราะห์'}</button>`;
        const groupContainer = selectionSection.querySelector('.space-y-4');
        groupContainer?.prepend(actionBar);

        let pageSize = 5;
        const pager = document.createElement('nav');
        pager.className = 'flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4';
        pager.setAttribute('aria-label', 'แบ่งหน้าสถานประกอบการ');

        const pageSizeLabel = document.createElement('label');
        pageSizeLabel.className = 'flex items-center gap-3 text-sm font-semibold text-slate-700';
        pageSizeLabel.textContent = 'จำนวนสถานประกอบการต่อหน้า';
        const pageSizeSelect = document.createElement('select');
        pageSizeSelect.className = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100';
        [5, 10, 15, 'all'].forEach((size) => {
            const option = document.createElement('option');
            option.value = String(size);
            option.textContent = size === 'all' ? 'ทั้งหมด' : `${size} รายการ`;
            option.selected = size === pageSize;
            pageSizeSelect.append(option);
        });
        pageSizeLabel.append(pageSizeSelect);

        pager.append(pageSizeLabel);
        // Keep the per-page control above the company cards, where staff can
        // choose the view before scrolling through any results.
        groupContainer?.prepend(pager);
        const renderPage = () => {
            groups.forEach((group, index) => {
                group.style.display = index < pageSize ? '' : 'none';
            });
        };
        pageSizeSelect.addEventListener('change', () => {
            pageSize = pageSizeSelect.value === 'all' ? groups.length : Number(pageSizeSelect.value);
            renderPage();
        });

        const updateSelection = () => {
            const selected = boxes.filter((box) => box.checked);
            actionBar.classList.toggle('hidden', selected.length === 0);
            if (!selected.length) return;

            const companies = [...new Set(selected.map((box) => box.dataset.company).filter(Boolean))];
            const company = companies.length > 1
                ? `${companies.length} สถานประกอบการ · จะรวมเป็นไฟล์เดียวหลายหน้า`
                : (companies[0] || 'สถานประกอบการที่เลือก');
            actionBar.querySelector('[data-selection-title]').textContent = `เลือกแล้ว ${companies.length} สถานประกอบการ`;
            actionBar.querySelector('[data-selection-detail]').textContent = `รวม ${selected.length} รายชื่อ · ${company}`;
            const selectedGroup = selected[0].closest('div.overflow-hidden.rounded-xl.border');
            selectedGroup?._setCompanyOpen?.(true);
        };

        boxes.forEach((box) => box.addEventListener('change', () => window.setTimeout(updateSelection, 0)));
        document.addEventListener('official-selection-changed', updateSelection);
        renderPage();
        updateSelection();
    });
})();
