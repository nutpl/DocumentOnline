<?php
require_once __DIR__ . '/../helpers/theme_helper.php';
renderThemeAssets();
$teacherCurrentPage = basename($_SERVER['PHP_SELF']);
$teacherCoordinatorProfile = null;
$coordinator_pending_count = 0;
if (isset($pdo, $_SESSION['user_id'])) {
    require_once __DIR__ . '/../helpers/teacher_coordinator_helper.php';
    $teacherCoordinatorProfile = teacherCoordinatorProfile($pdo, (int) $_SESSION['user_id']);
    if ($teacherCoordinatorProfile) {
        $coordinator_pending_count = teacherCoordinatorPendingCount($pdo, (int) $_SESSION['user_id']);
    }
}
$isCoordinatorReview = $teacherCurrentPage === 'coordinator_requests.php'
    || ($teacherCurrentPage === 'request_review.php' && ($_GET['from'] ?? '') === 'coordinator');
$teacherMenuClass = static function (array $pages) use ($teacherCurrentPage): string {
    return in_array($teacherCurrentPage, $pages, true)
        ? 'bg-red-600 text-white shadow-sm'
        : 'text-gray-600 hover:bg-red-50 hover:text-red-700';
};
?>
<link rel="stylesheet" href="/Project/assets/css/teacher.css?v=20261107-1">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20261107-1">
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20261107-1">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
<div id="teacher-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-gray-950/50 lg:hidden"></div>
<aside id="teacher-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0">
    <div class="teacher-sidebar-brand relative flex h-20 items-center gap-2 border-b px-6">
        <img src="../assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="teacher-university-seal h-16 w-12 shrink-0 object-contain">
        <img src="../assets/images/logo-transparent.png" alt="คณะวิศวกรรมศาสตร์และเทคโนโลยี" class="teacher-faculty-logo h-10 w-14 shrink-0 object-contain">
        <div class="teacher-sidebar-label min-w-0">
            <h2 class="text-base font-bold text-gray-900">อาจารย์</h2>
        </div>
        <button id="teacher-sidebar-collapse" type="button" class="teacher-sidebar-collapse hidden h-8 w-8 items-center justify-center rounded-full lg:inline-flex" title="ย่อแถบเมนู" aria-label="ย่อแถบเมนู" aria-expanded="true"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="teacher-sidebar-close" type="button" class="ml-auto inline-flex h-9 w-9 items-center justify-center text-gray-500 lg:hidden" title="ปิดเมนู">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto p-4" aria-label="เมนูอาจารย์">
        <p class="mb-2 px-3 text-xs font-semibold uppercase text-gray-400">เมนูหลัก</p>
        <a href="dashboard.php" class="<?= $teacherMenuClass(['dashboard.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-house w-5 text-center"></i><span>หน้าหลัก</span>
        </a>
        <a href="requests.php" class="<?= (!$isCoordinatorReview && in_array($teacherCurrentPage,['requests.php','request_review.php','process_review.php'],true)) ? 'bg-red-600 text-white shadow-sm' : 'text-gray-600 hover:bg-red-50 hover:text-red-700' ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3">
            <i class="fa-solid fa-clipboard-check w-5 text-center"></i><span class="leading-5">พิจารณาคำร้องเอกสาร R<br><small class="font-normal opacity-75">อาจารย์ที่ปรึกษา</small></span>
            <?php if (($pending_document_count ?? 0) > 0): ?><span class="teacher-menu-count ml-auto min-w-6 rounded-full bg-red-100 px-2 py-0.5 text-center text-xs font-bold text-red-700"><?= (int) $pending_document_count ?></span><?php endif; ?>
        </a>
        <?php if ($teacherCoordinatorProfile): ?>
        <a href="coordinator_requests.php" class="<?= $isCoordinatorReview ? 'bg-red-600 text-white shadow-sm' : 'text-gray-600 hover:bg-red-50 hover:text-red-700' ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3">
            <span class="teacher-menu-icon flex h-5 w-10 shrink-0 items-center justify-center" aria-hidden="true"><img src="../assets/images/coop-transparent.png" alt="" class="<?= $isCoordinatorReview ? 'teacher-coop-active ' : '' ?>h-5 w-10 object-contain"></span><span class="leading-5">คำร้องสหกิจ<br><small class="font-normal opacity-75">อาจารย์ประสานงาน</small></span>
            <?php if ($coordinator_pending_count > 0): ?><span class="teacher-menu-count ml-auto min-w-6 rounded-full bg-amber-100 px-2 py-0.5 text-center text-xs font-bold text-amber-700"><?= $coordinator_pending_count ?></span><?php endif; ?>
        </a>
        <a href="response_letters.php" class="<?= $teacherMenuClass(['response_letters.php', 'download_response_letter.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-envelope-open-text w-5 text-center"></i><span class="leading-5">ตรวจหนังสือตอบรับ<br><small class="font-normal opacity-75">อาจารย์ประสานงาน</small></span>
        </a>
        <a href="internship_tracking.php" class="<?= $teacherMenuClass(['internship_tracking.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-route w-5 text-center"></i><span class="leading-5">ติดตามสถานะสหกิจ<br><small class="font-normal opacity-75">อาจารย์ประสานงาน</small></span>
        </a>
        <a href="official_documents.php" class="<?= $teacherMenuClass(['official_documents.php', 'generate_official_pdf.php', 'generate_official_word.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-file-arrow-down w-5 text-center"></i><span class="leading-5">ดาวน์โหลดหนังสือ<br><small class="font-normal opacity-75">อาจารย์ประสานงาน</small></span>
        </a>
        <?php endif; ?>
        <a href="supervision.php" class="<?= $teacherMenuClass(['supervision.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-calendar-days w-5 text-center"></i><span>แผนนิเทศ</span>
        </a>
        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase text-gray-400">บัญชีของฉัน</p>
        <a href="profile.php" class="<?= $teacherMenuClass(['profile.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-user-pen w-5 text-center"></i><span>ข้อมูลส่วนตัว</span>
        </a>
        <a href="../auth/change_password.php" class="flex items-center gap-3 rounded-lg px-4 py-3 text-gray-600 transition hover:bg-red-50 hover:text-red-700">
            <i class="fa-solid fa-key w-5 text-center"></i><span>เปลี่ยนรหัสผ่าน</span>
        </a>
    </nav>

    <div class="teacher-sidebar-footer border-t p-4">
        <a href="../auth/logout.php" class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 py-3 font-semibold text-gray-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">
            <i class="fa-solid fa-right-from-bracket"></i><span>ออกจากระบบ</span>
        </a>
    </div>
</aside>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/success-alert.js"></script>
<script src="/Project/assets/js/form-confirm.js"></script>
<script src="/Project/assets/js/logout-confirm.js?v=20260911-2"></script>
