<?php
require_once __DIR__ . '/../helpers/theme_helper.php';
renderThemeAssets();
$studentCurrentPage = basename($_SERVER['PHP_SELF']);
$studentMenuClass = static function (array $pages) use ($studentCurrentPage): string {
    return in_array($studentCurrentPage, $pages, true)
        ? 'bg-red-600 text-white shadow-sm'
        : 'text-gray-600 hover:bg-red-50 hover:text-red-700';
};
$studentSidebarUser = is_array($user ?? null) ? $user : [];
if ((!isset($studentSidebarUser['student_number']) || !isset($studentSidebarUser['email'])) && isset($pdo, $_SESSION['user_id'])) {
    $studentSidebarStatement = $pdo->prepare('SELECT s.firstname,s.lastname,s.student_number,u.email FROM student s INNER JOIN users u ON u.user_id=s.user_id WHERE s.user_id=? LIMIT 1');
    $studentSidebarStatement->execute([$_SESSION['user_id']]);
    $studentSidebarUser = $studentSidebarStatement->fetch(PDO::FETCH_ASSOC) ?: $studentSidebarUser;
}
$studentSidebarName = trim((string) ($studentSidebarUser['firstname'] ?? '') . ' ' . (string) ($studentSidebarUser['lastname'] ?? ''));
$studentSidebarEmail = (string) ($studentSidebarUser['email'] ?? '');
$studentSidebarInitials = function_exists('mb_substr')
    ? mb_substr((string) ($studentSidebarUser['firstname'] ?? 'นศ'), 0, 1, 'UTF-8') . mb_substr((string) ($studentSidebarUser['lastname'] ?? ''), 0, 1, 'UTF-8')
    : 'นศ';
?>
<link rel="stylesheet" href="/Project/assets/css/student.css?v=20261108-5">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20261107-1">
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260918-1">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
<div id="student-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-gray-950/50 lg:hidden"></div>
<aside id="student-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0">
    <div class="student-sidebar-brand relative flex h-20 items-center gap-1 border-b px-4">
        <img src="../assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="student-university-seal h-16 w-12 shrink-0 object-contain">
        <img src="../assets/images/logo-transparent.png" alt="คณะวิศวกรรมศาสตร์และเทคโนโลยี" class="student-faculty-logo h-10 w-14 shrink-0 object-contain">
        <div class="student-sidebar-label min-w-0"><p class="student-portal-badge">STUDENT PORTAL</p></div>
        <button id="student-sidebar-collapse" type="button" class="student-sidebar-collapse hidden h-8 w-8 items-center justify-center rounded-full lg:inline-flex" title="ย่อแถบเมนู" aria-label="ย่อแถบเมนู" aria-expanded="true"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="student-sidebar-close" type="button" class="ml-auto inline-flex h-9 w-9 items-center justify-center text-gray-500 lg:hidden" title="ปิดเมนู"><i class="fa-solid fa-xmark text-xl"></i></button>
    </div>
    <nav class="flex-1 overflow-y-auto p-4" aria-label="เมนูนักศึกษา">
        <p class="mb-2 px-3 text-xs font-semibold uppercase text-gray-400">เมนูหลัก</p>
        <a href="dashboard.php" title="หน้าหลัก" class="<?= $studentMenuClass(['dashboard.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-house w-5 text-center"></i><span>หน้าหลัก</span></a>
        <a href="internship.php" title="เอกสารคำร้องสหกิจ" class="<?= $studentMenuClass(['internship.php','internship_form.php','save_internship.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-briefcase w-5 text-center"></i><span>เอกสารคำร้องสหกิจ</span></a>
        <a href="internship_submissions.php" title="ส่งเอกสารให้เจ้าหน้าที่" class="<?= $studentMenuClass(['internship_submissions.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-file-arrow-up w-5 text-center"></i><span>ส่งเอกสารให้เจ้าหน้าที่</span></a>
        <a href="response_letter.php" title="อัปโหลดหนังสือตอบรับ" class="<?= $studentMenuClass(['response_letter.php','upload_response_letter.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-file-circle-check w-5 text-center"></i><span>อัปโหลดหนังสือตอบรับ</span></a>
        <a href="documents.php" title="เอกสารคำร้อง (R)" class="<?= $studentMenuClass(['documents.php','document_create.php','document_form.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-file-pen w-5 text-center"></i><span>เอกสารคำร้อง (R)</span></a>
        <a href="tracking.php" title="ติดตามสถานะ" class="<?= $studentMenuClass(['tracking.php','document_status.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-route w-5 text-center"></i><span>ติดตามสถานะ</span></a>
        <a href="history.php" title="ประวัติเอกสาร" class="<?= $studentMenuClass(['history.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-clock-rotate-left w-5 text-center"></i><span>ประวัติเอกสาร</span><?php if (($student_document_count ?? 0) > 0): ?><span class="student-menu-count ml-auto min-w-6 rounded-full bg-gray-100 px-2 py-0.5 text-center text-xs font-bold text-gray-600"><?= (int) $student_document_count ?></span><?php endif; ?></a>
        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase text-gray-400">บัญชีของฉัน</p>
        <a href="profile.php" title="ข้อมูลส่วนตัว" class="<?= $studentMenuClass(['profile.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition"><i class="fa-solid fa-user w-5 text-center"></i><span>ข้อมูลส่วนตัว</span></a>
        <a href="../auth/change_password.php" title="เปลี่ยนรหัสผ่าน" class="flex items-center gap-3 rounded-lg px-4 py-3 text-gray-600 transition hover:bg-red-50 hover:text-red-700"><i class="fa-solid fa-key w-5 text-center"></i><span>เปลี่ยนรหัสผ่าน</span></a>
    </nav>
    <div class="student-sidebar-footer border-t p-4">
        <div class="student-account relative">
            <button id="student-account-toggle" type="button" class="student-account-toggle flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left" aria-expanded="false" aria-controls="student-account-menu">
                <span class="student-account-avatar inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl font-extrabold"><?= htmlspecialchars($studentSidebarInitials) ?></span>
                <span class="student-account-summary min-w-0 flex-1"><strong class="student-account-name block truncate"><?= htmlspecialchars($studentSidebarName ?: 'นักศึกษา') ?></strong><span class="student-account-email block truncate"><?= htmlspecialchars($studentSidebarEmail ?: ($studentSidebarUser['student_number'] ?? '-')) ?></span></span>
                <i class="student-account-chevron fa-solid fa-chevron-up text-xs" aria-hidden="true"></i>
            </button>
            <div id="student-account-menu" class="student-account-menu absolute bottom-full left-0 z-50 mb-3 hidden w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center gap-3 p-4">
                    <span class="student-account-avatar inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl font-extrabold"><?= htmlspecialchars($studentSidebarInitials) ?></span>
                    <div class="min-w-0"><strong class="block truncate text-sm text-slate-900"><?= htmlspecialchars($studentSidebarName ?: 'นักศึกษา') ?></strong><span class="block truncate text-xs text-slate-500"><?= htmlspecialchars($studentSidebarEmail ?: ($studentSidebarUser['student_number'] ?? '-')) ?></span></div>
                </div>
                <div class="border-y border-slate-100 px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">ข้อมูลบัญชี</p><p class="mt-2 flex items-center gap-2 text-sm font-bold text-slate-800"><i class="fa-solid fa-user-graduate text-red-600"></i>นักศึกษา</p><p class="mt-1 flex items-center gap-2 text-xs text-slate-500"><i class="fa-regular fa-id-card w-4 text-center"></i>รหัส <?= htmlspecialchars((string) ($studentSidebarUser['student_number'] ?? '-')) ?></p></div>
                <a href="../auth/logout.php" title="ออกจากระบบ" class="student-account-logout flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-600"><i class="fa-solid fa-right-from-bracket"></i>ออกจากระบบ</a>
            </div>
        </div>
    </div>
</aside>
<script>
(() => {
    const toggle = document.getElementById('student-account-toggle');
    const menu = document.getElementById('student-account-menu');
    if (!toggle || !menu) return;
    const close = () => { menu.classList.add('hidden'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', (event) => { event.stopPropagation(); const isOpen = !menu.classList.contains('hidden'); menu.classList.toggle('hidden', isOpen); toggle.setAttribute('aria-expanded', String(!isOpen)); });
    document.addEventListener('click', (event) => { if (!menu.contains(event.target) && !toggle.contains(event.target)) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/success-alert.js"></script>
<script src="/Project/assets/js/form-confirm.js"></script>
<script src="/Project/assets/js/logout-confirm.js?v=20260911-2"></script>
