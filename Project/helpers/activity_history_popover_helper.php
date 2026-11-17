<?php
declare(strict_types=1);

/**
 * Compact activity history that is available from the main navigation.
 * Staff and teachers see the shared actions of their department; admins see
 * the system-wide history.  This keeps the shared-work audit close to the
 * screen where work is actually performed.
 */
function renderActivityHistoryPopover(PDO $pdo): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    $viewerId = (int) ($_SESSION['user_id'] ?? 0);
    $role = (string) ($_SESSION['role'] ?? '');
    $departmentId = 0;
    if (in_array($role, ['staff', 'teacher'], true) && $viewerId > 0) {
        $viewer = $pdo->prepare('SELECT department_id FROM employee WHERE user_id = ? LIMIT 1');
        $viewer->execute([$viewerId]);
        $departmentId = (int) $viewer->fetchColumn();
    }

    $where = '';
    $params = [];
    if ($departmentId > 0 && $role !== 'admin') {
        $where = ' WHERE actor.department_id = ? OR al.user_id = ?';
        $params = [$departmentId, $viewerId];
    }

    $sql = 'SELECT al.activity, al.detail, al.activity_time,
        COALESCE(CONCAT(
            CASE
                WHEN actor.employee_type = "teacher" AND COALESCE(prefixes.prefix_short, prefixes.prefix_name, "") IN ("", "นาย", "นาง", "นางสาว") THEN "อาจารย์"
                ELSE COALESCE(prefixes.prefix_short, prefixes.prefix_name, "")
            END,
            actor.firstname, " ", actor.lastname
        ), users.username, "ระบบ") AS actor_name
        FROM activity_log al
        LEFT JOIN users ON users.user_id = al.user_id
        LEFT JOIN employee actor ON actor.user_id = al.user_id
        LEFT JOIN prefixes ON prefixes.prefix_id = actor.prefix_id'
        . $where .
        ' ORDER BY al.activity_time DESC, al.log_id DESC LIMIT 50';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $items = [];
    }
    ?>
    <div class="relative" data-activity-history-menu>
        <button type="button" data-activity-history-toggle aria-expanded="false" aria-controls="activity-history-popover" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 transition hover:bg-red-50 hover:text-red-700" title="ประวัติการดำเนินการ" aria-label="ประวัติการดำเนินการ">
            <i class="fa-solid fa-clock-rotate-left text-lg"></i>
        </button>
        <section id="activity-history-popover" data-activity-history-popover hidden class="absolute right-0 top-full z-50 mt-3 w-[min(32rem,calc(100vw-2rem))] overflow-visible rounded-2xl border border-slate-200 bg-white shadow-2xl" aria-label="ประวัติการดำเนินการ">
            <span class="absolute -top-2 right-3 h-4 w-4 rotate-45 border-l border-t border-slate-200 bg-white"></span>
            <header class="relative flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-700"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">ประวัติการดำเนินการ</h2>
                    <p class="text-xs text-slate-500">แสดงรายการล่าสุด <?= count($items) ?> รายการ</p>
                </div>
            </header>
            <div class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto">
                <?php foreach ($items as $item): ?>
                    <article class="px-4 py-3 transition hover:bg-slate-50">
                        <div class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500"><i class="fa-solid fa-user-pen text-xs"></i></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-900"><?= htmlspecialchars((string) $item['actor_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-0.5 text-xs font-semibold text-red-700"><?= htmlspecialchars((string) $item['activity'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (trim((string) $item['detail']) !== ''): ?><p class="mt-1 text-xs leading-5 text-slate-600"><?= htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                <p class="mt-1 text-[11px] text-slate-400"><?= htmlspecialchars((string) $item['activity_time'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$items): ?><div class="px-5 py-9 text-center text-sm text-slate-500"><i class="fa-regular fa-clock mb-2 block text-2xl text-slate-300"></i>ยังไม่มีประวัติการดำเนินการ</div><?php endif; ?>
            </div>
        </section>
    </div>
    <script>
    (() => {
        const menu = document.querySelector('[data-activity-history-menu]');
        const toggle = document.querySelector('[data-activity-history-toggle]');
        const popover = document.querySelector('[data-activity-history-popover]');
        const close = () => { if (popover) { popover.hidden = true; toggle?.setAttribute('aria-expanded', 'false'); } };
        toggle?.addEventListener('click', () => {
            if (!popover) return;
            const opening = popover.hidden;
            popover.hidden = !opening;
            toggle.setAttribute('aria-expanded', String(opening));
        });
        document.addEventListener('click', event => { if (menu && !menu.contains(event.target)) close(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    })();
    </script>
    <?php
}
