<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../../helpers/pagination_helper.php';

$pageTitle = 'จัดการตำแหน่ง';
$adminNavbarActions = [
    ['href' => '#', 'target' => 'admin-position-create-modal', 'label' => 'เพิ่มตำแหน่ง', 'icon' => 'fa-plus'],
];
$error = '';
$editId = (int) ($_GET['edit'] ?? 0);
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? '');
    $positionId = (int) ($_POST['position_id'] ?? 0);
    $name = trim((string) ($_POST['position_name'] ?? ''));
    try {
        if ($action === 'save') {
            if ($name === '') throw new RuntimeException('กรุณาระบุชื่อตำแหน่ง');
            $check = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE position_name = ? AND position_id <> ?');
            $check->execute([$name, $positionId]);
            if ((int) $check->fetchColumn()) throw new RuntimeException('มีตำแหน่งนี้อยู่ในระบบแล้ว');
            $positionId > 0
                ? $pdo->prepare('UPDATE positions SET position_name = ? WHERE position_id = ?')->execute([$name, $positionId])
                : $pdo->prepare('INSERT INTO positions (position_name) VALUES (?)')->execute([$name]);
            $_SESSION['position_success'] = $positionId ? 'แก้ไขตำแหน่งเรียบร้อยแล้ว' : 'เพิ่มตำแหน่งเรียบร้อยแล้ว';
            header('Location: index.php'); exit;
        }
        if ($action === 'delete') {
            $used = $pdo->prepare('SELECT COUNT(*) FROM employee_position WHERE position_id = ?');
            $used->execute([$positionId]);
            if ((int) $used->fetchColumn()) throw new RuntimeException('ไม่สามารถลบตำแหน่งนี้ได้ เนื่องจากมีอาจารย์หรือบุคลากรใช้งานอยู่');
            $pdo->prepare('DELETE FROM positions WHERE position_id = ?')->execute([$positionId]);
            $_SESSION['position_success'] = 'ลบตำแหน่งเรียบร้อยแล้ว';
            header('Location: index.php'); exit;
        }
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    } catch (Throwable $exception) { $error = $exception->getMessage(); $editId = $positionId; }
}

$editing = ['position_id' => 0, 'position_name' => ''];
if ($editId) { $stmt = $pdo->prepare('SELECT position_id, position_name FROM positions WHERE position_id = ?'); $stmt->execute([$editId]); $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: $editing; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && $error !== '') $editing = ['position_id' => (int) ($_POST['position_id'] ?? 0), 'position_name' => (string) ($_POST['position_name'] ?? '')];

$perPage = selected_per_page(10);
$totalPositions = (int) $pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn();
$totalPages = max(1, (int) ceil($totalPositions / $perPage));
$page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;
$positions = $pdo->query("SELECT p.position_id, p.position_name, COUNT(ep.employee_id) AS used_count FROM positions p LEFT JOIN employee_position ep ON ep.position_id = p.position_id GROUP BY p.position_id, p.position_name ORDER BY p.position_name LIMIT {$perPage} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>
<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
<?php if (!empty($_SESSION['position_success'])): ?><div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= h($_SESSION['position_success']) ?>"></div><?php unset($_SESSION['position_success']); endif; ?>
<?php if ($error): ?><div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= h($error) ?></div><?php endif; ?>
    <section class="management-hero"><div class="management-hero__icon"><i class="fa-solid fa-id-badge"></i></div><div><p class="management-hero__eyebrow">ข้อมูลพื้นฐานของระบบ</p><h1>จัดการตำแหน่ง</h1><p>กำหนดตำแหน่งที่ใช้กับอาจารย์และบุคลากร เพื่อให้ข้อมูลในระบบเป็นมาตรฐานเดียวกัน</p></div></section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-black text-slate-900"><?= (int) $editing['position_id'] ? 'แก้ไขตำแหน่ง' : 'เพิ่มตำแหน่ง' ?></h2><p class="mt-1 text-sm text-slate-500">เพิ่มหรือปรับชื่อที่ใช้แสดงในระบบ</p></div><?php if ((int) $editing['position_id']): ?><a href="index.php" class="text-sm font-bold text-slate-600 hover:text-red-700"><i class="fa-solid fa-xmark mr-1"></i>ยกเลิกการแก้ไข</a><?php endif; ?></div><form method="post" class="grid gap-3 sm:grid-cols-[1fr_auto]"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="position_id" value="<?= (int) $editing['position_id'] ?>"><input name="position_name" required value="<?= h($editing['position_name']) ?>" placeholder="เช่น อาจารย์ประจำสาขา" class="min-w-0 rounded-xl border border-slate-300 px-4 py-3"><button class="rounded-xl bg-red-700 px-5 py-3 font-bold text-white shadow-sm hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button></form></section>
    <section id="position-list" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-5"><div><h2 class="text-lg font-black text-slate-900">รายการตำแหน่ง</h2><p class="mt-1 text-sm text-slate-500">ตำแหน่งทั้งหมด <?= $totalPositions ?> รายการ</p></div><span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700"><i class="fa-solid fa-id-badge"></i><?= $totalPositions ?> ตำแหน่ง</span></div><div class="overflow-x-auto"><table class="w-full min-w-[650px] text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4 text-left">ตำแหน่ง</th><th class="px-6 py-4 text-center">จำนวนผู้ใช้งาน</th><th class="px-6 py-4 text-center">จัดการ</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach ($positions as $position): ?><tr class="transition hover:bg-slate-50"><td class="px-6 py-4 font-semibold text-slate-800"><?= h($position['position_name']) ?></td><td class="px-6 py-4 text-center"><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600"><?= (int) $position['used_count'] ?> คน</span></td><td class="px-6 py-4 text-center"><div class="inline-flex gap-2"><a href="?edit=<?= (int) $position['position_id'] ?>#position-list" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white" title="แก้ไข"><i class="fa-solid fa-pen"></i></a><form method="post" onsubmit="return confirm('ยืนยันการลบตำแหน่งนี้?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="position_id" value="<?= (int) $position['position_id'] ?>"><button <?= (int) $position['used_count'] ? 'disabled title="มีผู้ใช้งานอยู่"' : 'title="ลบ"' ?> class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 hover:bg-red-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"><i class="fa-solid fa-trash"></i></button></form></div></td></tr><?php endforeach; ?><?php if (!$positions): ?><tr><td colspan="3" class="px-6 py-14 text-center text-slate-400">ยังไม่มีตำแหน่ง</td></tr><?php endif; ?></tbody></table></div><?php render_pagination($totalPositions, $page, $perPage, 'page', 'position-list'); ?></section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editLinks = document.querySelectorAll('#position-list a[href*="?edit="]');
    const hero = document.querySelector('.management-hero');
    const legacyEditor = hero?.nextElementSibling;
    const legacyForm = legacyEditor?.querySelector('form');
    const legacyId = legacyForm?.querySelector('[name="position_id"]')?.value || '0';
    const token = legacyForm?.querySelector('input[name="csrf_token"]')?.value || '';

    const modal = document.createElement('dialog');
    modal.id = 'admin-position-create-modal';
    modal.className = 'w-[calc(100%_-_1.5rem)] max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55';
    modal.innerHTML = '<form method="post" class="p-5 sm:p-6"><input type="hidden" name="csrf_token"><input type="hidden" name="action" value="save"><input type="hidden" name="position_id"><div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4"><div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><i class="fa-solid fa-pen"></i></span><div><h2 class="font-black text-slate-900">แก้ไขตำแหน่ง</h2><p class="mt-1 text-sm text-slate-500">ปรับชื่อที่ใช้แสดงในระบบ</p></div></div><button type="button" data-close-position-modal class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></div><label class="mt-5 block text-sm font-bold text-slate-700">ชื่อตำแหน่ง<input required name="position_name" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"></label><div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between"><button type="button" data-close-position-modal class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50">ยกเลิก</button><button class="rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการแก้ไข</button></div></form>';
    document.body.appendChild(modal);
    const form = modal.querySelector('form');
    const closeModal = () => modal.close();
    modal.querySelectorAll('[data-close-position-modal]').forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    const openEditor = (positionId, positionName) => {
        const isEdit = Number(positionId) > 0;
        form.querySelector('[name="csrf_token"]').value = token;
        form.querySelector('[name="position_id"]').value = positionId;
        form.querySelector('[name="position_name"]').value = positionName;
        modal.querySelector('h2').textContent = isEdit ? 'แก้ไขตำแหน่ง' : 'เพิ่มตำแหน่งใหม่';
        modal.querySelector('h2').nextElementSibling.textContent = isEdit ? 'ปรับชื่อที่ใช้แสดงในระบบ' : 'ระบุชื่อตำแหน่งที่ต้องการใช้งานในระบบ';
        modal.querySelector('button:not([type]), button[type="submit"]').innerHTML = '<i class="fa-solid fa-floppy-disk mr-2"></i>' + (isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มตำแหน่ง');
        modal.showModal();
        form.querySelector('[name="position_name"]').focus();
    };

    document.querySelectorAll('[data-admin-panel="admin-position-create-modal"]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openEditor('0', '');
        });
    });

    if (legacyId !== '0') {
        legacyEditor.hidden = true;
        openEditor(legacyId, legacyForm.querySelector('[name="position_name"]')?.value || '');
    } else if (legacyEditor) {
        legacyEditor.hidden = true;
    }

    editLinks.forEach((link) => link.addEventListener('click', (event) => {
        event.preventDefault();
        const url = new URL(link.href);
        const row = link.closest('tr');
        openEditor(url.searchParams.get('edit') || '', row?.querySelector('td:first-child')?.textContent?.trim() || '');
    }));
});
</script>
<script src="/Project/assets/js/success-alert.js" defer></script>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
