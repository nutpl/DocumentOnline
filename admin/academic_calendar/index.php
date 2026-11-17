<?php
require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$pageTitle = 'ปฏิทินการศึกษา';
$adminNavbarActions = [
    ['href' => '#', 'target' => 'academic-calendar-modal', 'label' => 'เพิ่มปฏิทิน', 'icon' => 'fa-calendar-plus'],
];
$error = '';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $action = (string) ($_POST['action'] ?? '');
    $calendarId = (int) ($_POST['calendar_id'] ?? 0);
    try {
        if ($action === 'save') {
            $year = (int) ($_POST['academic_year'] ?? 0);
            $semester = (int) ($_POST['semester'] ?? 0);
            $startDate = (string) ($_POST['start_date'] ?? '');
            $endDate = (string) ($_POST['end_date'] ?? '');
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            if ($year < 2500 || !in_array($semester, [1, 2, 3], true) || !$startDate || !$endDate) {
                throw new RuntimeException('กรุณากรอกปีการศึกษา ภาคเรียน และวันเปิด–ปิดภาคให้ครบถ้วน');
            }
            if ($startDate > $endDate) throw new RuntimeException('วันเปิดภาคต้องไม่เกินวันปิดภาค');
            if ($calendarId > 0) {
                $stmt = $pdo->prepare('UPDATE internship_calendar SET academic_year=?,semester=?,start_date=?,end_date=?,status=? WHERE calendar_id=?');
                $stmt->execute([$year, $semester, $startDate, $endDate, $status, $calendarId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO internship_calendar(academic_year,semester,start_date,end_date,status,created_by) VALUES(?,?,?,?,?,?)');
                $stmt->execute([$year, $semester, $startDate, $endDate, $status, $_SESSION['user_id']]);
            }
            $_SESSION['calendar_success'] = 'บันทึกปฏิทินการศึกษาเรียบร้อยแล้ว';
            header('Location: index.php'); exit;
        }
        if ($action === 'toggle') {
            $pdo->prepare("UPDATE internship_calendar SET status=IF(status='active','inactive','active') WHERE calendar_id=?")->execute([$calendarId]);
            $_SESSION['calendar_success'] = 'ปรับสถานะปฏิทินการศึกษาเรียบร้อยแล้ว';
            header('Location: index.php'); exit;
        }
        if ($action === 'delete') {
            $usage = $pdo->prepare('SELECT COUNT(*) FROM internship_profile WHERE calendar_id=?');
            $usage->execute([$calendarId]);
            if ((int) $usage->fetchColumn() > 0) throw new RuntimeException('ไม่สามารถลบได้ เพราะมีข้อมูลนักศึกษาอ้างอิงปฏิทินนี้อยู่');
            $pdo->prepare('DELETE FROM internship_calendar WHERE calendar_id=?')->execute([$calendarId]);
            $_SESSION['calendar_success'] = 'ลบปฏิทินการศึกษาเรียบร้อยแล้ว';
            header('Location: index.php'); exit;
        }
        throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    } catch (Throwable $exception) {
        $error = str_contains($exception->getMessage(), 'uq_internship_calendar_term') || str_contains($exception->getMessage(), 'Duplicate')
            ? 'มีปฏิทินของปีการศึกษาและภาคเรียนนี้แล้ว' : $exception->getMessage();
        $editId = $calendarId;
    }
}

$editing = ['calendar_id'=>0,'academic_year'=>'','semester'=>1,'start_date'=>'','end_date'=>'','status'=>'active'];
if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM internship_calendar WHERE calendar_id=?');
    $editStmt->execute([$editId]);
    $editing = $editStmt->fetch(PDO::FETCH_ASSOC) ?: $editing;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && $error) {
    $editing = array_merge($editing, [
        'calendar_id'=>(int)($_POST['calendar_id'] ?? 0), 'academic_year'=>$_POST['academic_year'] ?? '', 'semester'=>$_POST['semester'] ?? 1,
        'start_date'=>$_POST['start_date'] ?? '', 'end_date'=>$_POST['end_date'] ?? '', 'status'=>$_POST['status'] ?? 'active',
    ]);
}
$calendars = $pdo->query('SELECT ic.*,COUNT(ip.internship_profile_id) AS used_count FROM internship_calendar ic LEFT JOIN internship_profile ip ON ip.calendar_id=ic.calendar_id GROUP BY ic.calendar_id ORDER BY ic.academic_year DESC,ic.semester')->fetchAll(PDO::FETCH_ASSOC);
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function termName(int $term): string { return $term === 3 ? 'ภาคฤดูร้อน' : 'ภาคเรียนที่ ' . $term; }

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>
<style>.admin-academic-calendar-table th,.admin-academic-calendar-table td{text-align:center!important}</style>
<div class="admin-academic-calendar-table mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <?php if (!empty($_SESSION['calendar_success'])): ?><div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="<?= h($_SESSION['calendar_success']) ?>"></div><?php unset($_SESSION['calendar_success']); endif; ?>
    <?php if ($error): ?><div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= h($error) ?></div><?php endif; ?>
    <section class="management-hero">
        <div class="management-hero__icon"><i class="fa-solid fa-calendar-days"></i></div>
        <div>
            <p class="management-hero__eyebrow">กำหนดการกลางของมหาวิทยาลัย</p>
            <h1>ปฏิทินการศึกษา</h1>
            <p>กำหนดปีการศึกษา ภาคเรียน และวันเปิด–ปิดภาค เพื่อให้เจ้าหน้าที่และนักศึกษาเลือกใช้ข้อมูลเดียวกันในการดำเนินงานสหกิจ</p>
        </div>
    </section>
    <div id="academic-calendar-modal" class="fixed inset-0 z-[80] <?= ((int)$editing['calendar_id'] || $error) ? '' : 'hidden' ?> overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="calendar-modal-title"><div class="flex min-h-full items-center justify-center"><section class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl"><header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4"><div><p class="text-sm font-bold text-red-700">กำหนดการกลางของมหาวิทยาลัย</p><h2 id="calendar-modal-title" class="mt-1 text-xl font-black text-slate-900"><?= (int)$editing['calendar_id'] ? 'แก้ไขปฏิทินการศึกษา' : 'เพิ่มปฏิทินการศึกษา' ?></h2><p class="mt-1 text-sm text-slate-500">เจ้าหน้าที่เลือกใช้ได้เฉพาะรายการที่เปิดใช้งาน</p></div><button type="button" data-close-calendar-modal class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark"></i></button></header><form method="post" class="p-5 sm:p-6"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="calendar_id" data-calendar-id value="<?= (int)$editing['calendar_id'] ?>"><?php if ($error): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= h($error) ?></div><?php endif; ?><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold text-slate-700">ปีการศึกษา (พ.ศ.)<input type="number" name="academic_year" data-calendar-year min="2500" required value="<?= h($editing['academic_year']) ?>" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label><label class="text-sm font-bold text-slate-700">ภาคเรียน<select name="semester" data-calendar-semester class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="1" <?= (int)$editing['semester']===1?'selected':'' ?>>ภาคเรียนที่ 1</option><option value="2" <?= (int)$editing['semester']===2?'selected':'' ?>>ภาคเรียนที่ 2</option><option value="3" <?= (int)$editing['semester']===3?'selected':'' ?>>ภาคฤดูร้อน</option></select></label><label class="text-sm font-bold text-slate-700">วันเปิดภาค<input type="date" name="start_date" data-calendar-start required value="<?= h($editing['start_date']) ?>" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label><label class="text-sm font-bold text-slate-700">วันปิดภาค<input type="date" name="end_date" data-calendar-end required value="<?= h($editing['end_date']) ?>" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label><label class="text-sm font-bold text-slate-700 sm:col-span-2">สถานะ<select name="status" data-calendar-status class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5"><option value="active" <?= $editing['status']==='active'?'selected':'' ?>>เปิดใช้งาน</option><option value="inactive" <?= $editing['status']==='inactive'?'selected':'' ?>>ปิดใช้งาน</option></select></label></div><div class="mt-6 flex justify-end"><button class="rounded-xl bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button></div></form></section></div></div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-6 py-5"><h3 class="text-lg font-black">รายการปฏิทินการศึกษา</h3></div><div class="overflow-x-auto"><table class="w-full min-w-[850px] text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th class="px-6 py-4 text-left">ปีการศึกษา</th><th class="px-6 py-4 text-left">ภาคเรียน</th><th class="px-6 py-4 text-left">วันเปิดภาค</th><th class="px-6 py-4 text-left">วันปิดภาค</th><th class="px-6 py-4 text-center">สถานะ</th><th class="px-6 py-4 text-center">การใช้งาน</th><th class="px-6 py-4 text-center">จัดการ</th></tr></thead><tbody class="divide-y divide-slate-100"><?php foreach($calendars as $calendar): ?><tr><td class="px-6 py-4 font-bold"><?= h($calendar['academic_year']) ?></td><td class="px-6 py-4"><?= h(termName((int)$calendar['semester'])) ?></td><td class="px-6 py-4"><?= h(date('d/m/Y',strtotime($calendar['start_date']))) ?></td><td class="px-6 py-4"><?= h(date('d/m/Y',strtotime($calendar['end_date']))) ?></td><td class="px-6 py-4 text-center"><span class="rounded-full px-3 py-1 text-xs font-bold <?= $calendar['status']==='active'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' ?>"><?= $calendar['status']==='active'?'เปิดใช้งาน':'ปิดใช้งาน' ?></span></td><td class="px-6 py-4 text-center"><?= (int)$calendar['used_count'] ?> รายการ</td><td class="px-6 py-4 text-center"><div class="inline-flex gap-2"><a href="?edit=<?= (int)$calendar['calendar_id'] ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white" title="แก้ไข"><i class="fa-solid fa-pen"></i></a><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="calendar_id" value="<?= (int)$calendar['calendar_id'] ?>"><button class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-500 hover:text-white" title="เปิด/ปิดใช้งาน"><i class="fa-solid fa-power-off"></i></button></form><form method="post" class="inline" onsubmit="return confirm('ยืนยันการลบปฏิทินการศึกษานี้?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="calendar_id" value="<?= (int)$calendar['calendar_id'] ?>"><button <?= (int)$calendar['used_count']>0?'disabled title="มีข้อมูลใช้งานอยู่"':'title="ลบ"' ?> class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 hover:bg-red-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"><i class="fa-solid fa-trash"></i></button></form></div></td></tr><?php endforeach; ?><?php if(!$calendars): ?><tr><td colspan="7" class="px-6 py-14 text-center text-slate-400">ยังไม่มีปฏิทินการศึกษา</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<script>
(() => {
    const modal = document.getElementById('academic-calendar-modal');
    if (!modal) return;
    const id = modal.querySelector('[data-calendar-id]'), year = modal.querySelector('[data-calendar-year]'), semester = modal.querySelector('[data-calendar-semester]'), start = modal.querySelector('[data-calendar-start]'), end = modal.querySelector('[data-calendar-end]'), status = modal.querySelector('[data-calendar-status]');
    const title = document.getElementById('calendar-modal-title');
    const toIso = (thaiDate) => { const [day, month, yearValue] = thaiDate.trim().split('/'); return day && month && yearValue ? `${yearValue}-${month}-${day}` : ''; };
    const open = () => { modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); year.focus(); };
    const close = () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
    document.querySelectorAll('[data-admin-panel="academic-calendar-modal"]').forEach((button) => button.addEventListener('click', (event) => { event.preventDefault(); id.value=''; year.value=''; semester.value='1'; start.value=''; end.value=''; status.value='active'; title.textContent='เพิ่มปฏิทินการศึกษา'; open(); }));
    document.querySelectorAll('a[href^="?edit="]').forEach((button) => button.addEventListener('click', (event) => { event.preventDefault(); const cells = button.closest('tr').querySelectorAll('td'); id.value = new URL(button.href).searchParams.get('edit') || ''; year.value = cells[0]?.textContent.trim() || ''; semester.value = (cells[1]?.textContent.includes('2') ? '2' : (cells[1]?.textContent.includes('ร้อน') ? '3' : '1')); start.value = toIso(cells[2]?.textContent || ''); end.value = toIso(cells[3]?.textContent || ''); status.value = cells[4]?.textContent.includes('เปิด') ? 'active' : 'inactive'; title.textContent='แก้ไขปฏิทินการศึกษา'; open(); }));
    modal.querySelectorAll('[data-close-calendar-modal]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    if (!modal.classList.contains('hidden')) document.body.classList.add('overflow-hidden');
})();
</script>
<script src="/Project/assets/js/success-alert.js" defer></script>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
