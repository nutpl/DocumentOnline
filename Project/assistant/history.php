<?php
session_start();
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';
requireAssistantWorkspace((string) ($_SESSION['role'] ?? ''));
require_once __DIR__ . '/layout.php';

$statement = $pdo->query('SELECT al.log_id,al.activity,al.detail,al.activity_time,u.username,
    COALESCE(CONCAT(
        CASE
            WHEN e.employee_type="teacher" AND COALESCE(p.prefix_short,p.prefix_name,"") IN ("","นาย","นาง","นางสาว") THEN "อาจารย์"
            ELSE COALESCE(p.prefix_short,p.prefix_name,"")
        END,
        e.firstname," ",e.lastname
    ),u.username,"ระบบ") AS actor_name
    FROM activity_log al
    LEFT JOIN users u ON u.user_id=al.user_id
    LEFT JOIN employee e ON e.user_id=u.user_id
    LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id
    ORDER BY al.activity_time DESC,al.log_id DESC LIMIT 200');
$history = $statement->fetchAll(PDO::FETCH_ASSOC);
$role = (string) $_SESSION['role'];
assistantWorkspaceHeader('ประวัติการดำเนินการ', 'ตรวจสอบผู้ดำเนินการ บทบาท รายการที่ทำ และเวลาที่เกิดการเปลี่ยนแปลง', $role);
?>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">วันเวลา</th><th class="px-5 py-3">ผู้ดำเนินการ</th><th class="px-5 py-3">การดำเนินการ</th><th class="px-5 py-3">รายละเอียด</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($history as $row): ?>
                <tr class="align-top hover:bg-slate-50/80"><td class="whitespace-nowrap px-5 py-4 text-slate-500"><?= htmlspecialchars((string) $row['activity_time'], ENT_QUOTES, 'UTF-8') ?></td><td class="whitespace-nowrap px-5 py-4 font-bold text-slate-800"><?= htmlspecialchars((string) $row['actor_name'], ENT_QUOTES, 'UTF-8') ?></td><td class="px-5 py-4"><span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700"><?= htmlspecialchars((string) $row['activity'], ENT_QUOTES, 'UTF-8') ?></span></td><td class="min-w-80 px-5 py-4 leading-6 text-slate-600"><?= htmlspecialchars((string) ($row['detail'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">ยังไม่มีประวัติการดำเนินการ</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php assistantWorkspaceFooter(); ?>
