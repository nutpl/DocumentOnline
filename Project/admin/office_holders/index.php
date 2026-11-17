<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../services/WorkflowService.php';

$service = new WorkflowService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        $service->assignOfficeHolder(
            (int) ($_POST['position_id'] ?? 0),
            (string) ($_POST['holder_name'] ?? ''),
            (int) ($_POST['department_id'] ?? 0),
            (int) $_SESSION['user_id']
        );
        $_SESSION['office_success'] = 'ปรับปรุงผู้ดำรงตำแหน่งเรียบร้อยแล้ว';
    } catch (Throwable $exception) {
        $_SESSION['office_error'] = $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'ไม่สามารถปรับปรุงผู้ดำรงตำแหน่งได้ กรุณาลองใหม่อีกครั้ง';
    }

    header('Location: index.php');
    exit;
}

$officePositions = $service->getOfficePositions();
$departments = $service->getDepartments();
$currentHolders = $service->getCurrentOfficeHolders();
$history = $service->getOfficeHolderHistory();
$pageTitle = 'จัดการผู้ดำรงตำแหน่ง';

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 px-6 py-7 text-white shadow-xl sm:px-8">
        <div class="absolute -right-14 -top-16 h-48 w-48 rounded-full border-[30px] border-white/5"></div>
        <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-600/20 text-red-300 ring-1 ring-red-400/20">
                    <i class="fa-solid fa-user-tie text-xl"></i>
                </span>
                <div>
                    <p class="mb-1 text-xs font-bold uppercase tracking-[0.2em] text-red-300">Office holders</p>
                    <h1 class="text-2xl font-black sm:text-3xl">ผู้ลงนามเอกสารสหกิจ</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">เปลี่ยนชื่อคณบดีหรือรองคณบดีเมื่อมีการเปลี่ยนแปลงบุคลากร เอกสารสหกิจที่สร้างใหม่จะใช้ข้อมูลปัจจุบันโดยอัตโนมัติ</p>
                </div>
            </div>
            <a href="../workflow/index.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/20">
                <i class="fa-solid fa-diagram-project"></i>กำหนดลำดับผู้พิจารณา
            </a>
        </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        <?php foreach ($currentHolders as $holder): ?>
            <?php $hasHolder = !empty($holder['office_holder_id']); ?>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">ตำแหน่ง</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900"><?= htmlspecialchars($holder['position_name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl <?= $hasHolder ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>">
                        <i class="fa-solid <?= $hasHolder ? 'fa-user-check' : 'fa-user-clock' ?>"></i>
                    </span>
                </div>
                <div class="p-6">
                    <?php if ($hasHolder): ?>
                        <p class="text-lg font-black text-slate-900"><?= htmlspecialchars((string) $holder['holder_name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($holder['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">
                            <i class="fa-regular fa-calendar-check"></i>เริ่มดำรงตำแหน่ง <?= date('d/m/Y', strtotime($holder['effective_from'])) ?>
                        </p>
                    <?php else: ?>
                        <p class="font-bold text-amber-700">ยังไม่ได้กำหนดผู้ดำรงตำแหน่ง</p>
                        <p class="mt-1 text-sm text-slate-500">กรุณาเลือกบุคลากรจากแบบฟอร์มด้านล่าง</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-lg font-black text-slate-900">เปลี่ยนผู้ดำรงตำแหน่ง</h2>
            <p class="mt-1 text-sm text-slate-500">การเปลี่ยนแปลงมีผลกับเอกสารที่สร้างใหม่ตั้งแต่วันที่บันทึก เอกสารเก่าจะคงชื่อเดิมไว้</p>
        </div>
        <form method="post" id="office-holder-form" class="grid gap-5 p-6 md:grid-cols-3 md:items-end">
            <?= csrf_field() ?>
            <label class="block">
                <span class="mb-2 block text-sm font-bold text-slate-700">ตำแหน่ง</span>
                <select name="position_id" required class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100">
                    <option value="">-- เลือกตำแหน่ง --</option>
                    <?php foreach ($officePositions as $position): ?>
                        <option value="<?= (int) $position['position_id'] ?>"><?= htmlspecialchars($position['position_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="mb-2 block text-sm font-bold text-slate-700">ชื่อผู้ดำรงตำแหน่งคนใหม่</span>
                <input type="text" name="holder_name" maxlength="255" required placeholder="เช่น รองศาสตราจารย์ ดร.ชื่อ นามสกุล" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none placeholder:text-slate-400 focus:border-red-300 focus:ring-4 focus:ring-red-100">
            </label>
            <div class="grid gap-3 sm:grid-cols-[1fr_auto] md:grid-cols-1 xl:grid-cols-[1fr_auto]">
                <label class="block">
                    <span class="mb-2 block text-sm font-bold text-slate-700">สาขา</span>
                    <select name="department_id" required class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100">
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['department_id'] ?>"><?= htmlspecialchars($department['faculty_name'] . ' — ' . $department['department_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-red-700 px-6 text-sm font-bold text-white shadow-lg shadow-red-700/20 transition hover:bg-red-600">
                    <i class="fa-solid fa-floppy-disk"></i>บันทึก
                </button>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-lg font-black text-slate-900">ประวัติผู้ดำรงตำแหน่ง</h2>
            <p class="mt-1 text-sm text-slate-500">ใช้ตรวจสอบว่าผู้ใดดำรงตำแหน่งในช่วงเวลาที่เอกสารถูกสร้าง</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-4 text-left">ตำแหน่ง</th>
                        <th class="px-6 py-4 text-left">ชื่อ–นามสกุล</th>
                        <th class="px-6 py-4 text-left">สาขา</th>
                        <th class="px-6 py-4 text-center">ช่วงเวลาดำรงตำแหน่ง</th>
                        <th class="px-6 py-4 text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!$history): ?>
                        <tr><td colspan="5" class="px-6 py-14 text-center text-slate-400">ยังไม่มีประวัติผู้ดำรงตำแหน่ง</td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $row): ?>
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($row['position_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= htmlspecialchars((string) $row['holder_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($row['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-center text-slate-600">
                                <?= date('d/m/Y', strtotime($row['effective_from'])) ?> – <?= $row['effective_to'] ? date('d/m/Y', strtotime($row['effective_to'])) : 'ปัจจุบัน' ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold <?= (int) $row['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                    <?= (int) $row['is_active'] === 1 ? 'ปัจจุบัน' : 'สิ้นสุดแล้ว' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if (!empty($_SESSION['office_success'])): ?>
    <div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="<?= htmlspecialchars($_SESSION['office_success'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php unset($_SESSION['office_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['office_error'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon: 'error', title: 'บันทึกไม่สำเร็จ', text: <?= json_encode($_SESSION['office_error'], JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#b91c1c'}));</script>
    <?php unset($_SESSION['office_error']); ?>
<?php endif; ?>

<script>
document.getElementById('office-holder-form').addEventListener('submit', event => {
    event.preventDefault();
    const form = event.currentTarget;
    const position = form.querySelector('[name="position_id"] option:checked')?.textContent.trim();
    const employee = form.querySelector('[name="holder_name"]')?.value.trim();
    if (!form.reportValidity()) return;
    Swal.fire({
        icon: 'question',
        title: 'ยืนยันการเปลี่ยนผู้ดำรงตำแหน่ง',
        html: `<strong>${position}</strong><br><span style="color:#64748b">${employee}</span>`,
        showCancelButton: true,
        confirmButtonText: 'ยืนยันและบันทึก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#b91c1c'
    }).then(result => { if (result.isConfirmed) form.submit(); });
});
</script>

<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
