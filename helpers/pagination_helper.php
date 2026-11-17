<?php

function selected_per_page(int $default = 10): int {
    $allowed = [5, 10, 15, 25, 50, 100, 250, 500];
    $requested = isset($_GET['per_page']) ? (int) $_GET['per_page'] : $default;
    return in_array($requested, $allowed, true) ? $requested : $default;
}

function render_per_page_selector(int $perPage, string $anchor = ''): void {
    $query = $_GET;
    unset($query['page'], $query['per_page']);
    $action = $anchor !== '' ? '#' . rawurlencode($anchor) : '';
    $controlId = 'per-page-control-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $anchor !== '' ? $anchor : 'list');
    ?>
    <div id="<?= htmlspecialchars($controlId, ENT_QUOTES, 'UTF-8') ?>" class="border-b border-slate-100 bg-white px-4 py-3 sm:px-5">
    <form method="get" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center justify-start gap-2 text-sm text-slate-600">
        <?php foreach ($query as $name => $value): ?>
            <?php if (is_scalar($value)): ?><input type="hidden" name="<?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        <?php endforeach; ?>
        <label for="per-page-<?= htmlspecialchars($anchor !== '' ? $anchor : 'list', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600">จำนวนรายการต่อหน้า</label>
        <select id="per-page-<?= htmlspecialchars($anchor !== '' ? $anchor : 'list', ENT_QUOTES, 'UTF-8') ?>" name="per_page" class="h-9 rounded-lg border border-slate-300 bg-white px-3 font-semibold text-slate-700" onchange="this.form.submit()" aria-label="จำนวนรายการต่อหน้า">
            <?php foreach ([5, 10, 15, 25, 50, 100, 250, 500] as $option): ?><option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> รายการ</option><?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold">ตกลง</button></noscript>
    </form>
    </div>
    <?php
}

function render_pagination(
    int $totalItems,
    int $currentPage,
    int $perPage,
    string $pageParameter = 'page',
    string $anchor = ''
): void {
    if ($totalItems <= 0) {
        return;
    }

    $controlId = 'per-page-control-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $anchor !== '' ? $anchor : 'list');
    ?>
    <div data-pagination-output>
        <?php render_per_page_selector($perPage, $anchor); ?>
    </div>
    <script>
    (function () {
        const movePerPageControl = function () {
        const control = document.getElementById(<?= json_encode($controlId) ?>);
        const output = control ? control.closest('[data-pagination-output]') : null;
        // The output wrapper sits immediately after the list section.  Use
        // the wrapper itself so previousElementSibling is that list section,
        // not the surrounding <main> element.
        const source = output;
        const fallbackTarget = source && source.previousElementSibling && source.previousElementSibling.matches('section, [data-pagination-list]')
            ? source.previousElementSibling
            : document.querySelector('main');
        const target = <?= $anchor !== '' ? 'document.getElementById(' . json_encode($anchor) . ')' : 'fallbackTarget' ?>;
        if (control && target && control.parentElement !== target) {
            // Keep the selector as its own first row in the list card, above
            // the heading and records, matching the shared list layout.
            target.insertBefore(control, target.firstChild);
        }
        if (output) output.remove();
        let emptyContainer = source;
        const main = document.querySelector('main');
        while (emptyContainer && emptyContainer !== target && emptyContainer !== main && emptyContainer.children.length === 0 && emptyContainer.textContent.trim() === '') {
            const parent = emptyContainer.parentElement;
            emptyContainer.remove();
            emptyContainer = parent;
        }
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', movePerPageControl);
        else movePerPageControl();
    })();
    </script>
    <?php
}
