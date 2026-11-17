<?php
require_once __DIR__ . '/../helpers/theme_helper.php';
renderThemeAssets();
$staffCurrentPage = basename($_SERVER['PHP_SELF']);
$staffOfficialType = $_GET['type'] ?? 'request';
$staffMenuClass = static function (array $pages) use ($staffCurrentPage): string {
    return in_array($staffCurrentPage, $pages, true)
        ? 'bg-red-600 text-white shadow-sm'
        : 'text-gray-600 hover:bg-red-50 hover:text-red-700';
};
?>
<link rel="stylesheet" href="/Project/assets/css/staff.css?v=20261021-1">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20261107-1">
<link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260918-1">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>
<script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>
<div id="staff-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-gray-950/50 lg:hidden"></div>
<aside id="staff-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0">
    <div class="staff-sidebar-brand relative flex h-20 items-center gap-2 border-b px-6">
        <img src="../assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="staff-university-seal h-16 w-12 shrink-0 object-contain">
        <img src="../assets/images/logo-transparent.png" alt="คณะวิศวกรรมศาสตร์และเทคโนโลยี" class="staff-faculty-logo h-10 w-14 shrink-0 object-contain">
        <div class="staff-sidebar-label min-w-0">
            <h2 class="text-base font-bold text-gray-900">เจ้าหน้าที่สาขา</h2>
        </div>
        <button id="staff-sidebar-collapse" type="button" class="staff-sidebar-collapse hidden h-8 w-8 items-center justify-center rounded-full lg:inline-flex" title="ย่อแถบเมนู" aria-label="ย่อแถบเมนู" aria-expanded="true"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="staff-sidebar-close" type="button" class="ml-auto inline-flex h-9 w-9 items-center justify-center text-gray-500 lg:hidden" title="ปิดเมนู">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto p-4" aria-label="เมนูเจ้าหน้าที่">
        <p class="mb-2 px-3 text-xs font-semibold uppercase text-gray-400">เมนูหลัก</p>
        <a href="dashboard.php" class="<?= $staffMenuClass(['dashboard.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-house w-5 text-center"></i><span>หน้าหลัก</span>
        </a>
        <a href="documents.php" class="<?= $staffMenuClass(['documents.php', 'export_documents.php', 'export_internship_excel.php', 'download_signed_internship.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <span class="staff-menu-icon flex h-5 w-10 shrink-0 items-center justify-center" aria-hidden="true"><img src="../assets/images/coop-transparent.png" alt="" class="h-5 w-10 object-contain"></span><span>เอกสารสหกิจ</span>
            <?php if (($document_count ?? 0) > 0): ?><span class="staff-menu-count ml-auto min-w-6 rounded-full bg-red-100 px-2 py-0.5 text-center text-xs font-bold text-red-700"><?= (int) $document_count ?></span><?php endif; ?>
        </a>
        <a href="supervision.php" class="<?= $staffMenuClass(['supervision.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-clipboard-check w-5 text-center"></i><span>แผนนิเทศ</span>
        </a>
        <a href="internship_tracking.php" class="<?= $staffMenuClass(['internship_tracking.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-route w-5 text-center"></i><span>ติดตามสถานะสหกิจ</span>
        </a>
        <a href="response_letters.php" class="<?= $staffMenuClass(['response_letters.php', 'download_response_letter.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-envelope-open-text w-5 text-center"></i><span>ตรวจหนังสือตอบรับ</span>
        </a>
        <a href="internship_calendar.php" class="<?= $staffMenuClass(['internship_calendar.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-calendar-days w-5 text-center"></i><span>ปฏิทินการศึกษา</span>
        </a>
        <a href="official_documents.php?type=request" class="<?= $staffCurrentPage==='official_documents.php' && $staffOfficialType!=='send'?'bg-red-600 text-white shadow-sm':'text-gray-600 hover:bg-red-50 hover:text-red-700' ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-file-signature w-5 text-center"></i><span>หนังสือขอความอนุเคราะห์</span>
        </a>
        <a href="official_documents.php?type=send" class="<?= in_array($staffCurrentPage,['official_documents.php','generate_official_pdf.php'],true) && $staffOfficialType==='send'?'bg-red-600 text-white shadow-sm':'text-gray-600 hover:bg-red-50 hover:text-red-700' ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-file-export w-5 text-center"></i><span>ใบขอส่งตัวนักศึกษา</span>
        </a>
        <a href="import_internship_excel.php" class="<?= $staffMenuClass(['import_internship_excel.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-file-arrow-up w-5 text-center"></i><span>นำเข้าข้อมูลฝึกงาน</span>
        </a>
        <a href="official_history.php" class="<?= $staffMenuClass(['official_history.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i><span>ประวัติหนังสือที่สร้าง</span>
        </a>
        <a href="official_template_settings.php" class="<?= $staffMenuClass(['official_template_settings.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-file-pen w-5 text-center"></i><span>จัดการแม่แบบหนังสือ</span>
        </a>
        <a href="teachers.php" class="<?= $staffMenuClass(['teachers.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-chalkboard-user w-5 text-center"></i><span>รายชื่ออาจารย์</span>
        </a>
        <a href="group_classes.php" class="<?= $staffMenuClass(['group_classes.php', 'group_class_edit.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-people-group w-5 text-center"></i><span>จัดการกลุ่มเรียน</span>
        </a>
        <a href="group_advisors.php" class="<?= $staffMenuClass(['group_advisors.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-user-check w-5 text-center"></i><span>กำหนดอาจารย์ที่ปรึกษา</span>
        </a>

        <p class="mb-2 mt-6 px-3 text-xs font-semibold uppercase text-gray-400">บัญชีของฉัน</p>
        <a href="profile.php" class="<?= $staffMenuClass(['profile.php']) ?> mb-1 flex items-center gap-3 rounded-lg px-4 py-3 transition">
            <i class="fa-solid fa-user w-5 text-center"></i><span>ข้อมูลส่วนตัว</span>
        </a>
        <a href="../auth/change_password.php" class="flex items-center gap-3 rounded-lg px-4 py-3 text-gray-600 transition hover:bg-red-50 hover:text-red-700">
            <i class="fa-solid fa-key w-5 text-center"></i><span>เปลี่ยนรหัสผ่าน</span>
        </a>
    </nav>

    <div class="staff-sidebar-footer border-t p-4">
        <a href="../auth/logout.php" class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 py-3 font-semibold text-gray-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">
            <i class="fa-solid fa-right-from-bracket"></i><span>ออกจากระบบ</span>
        </a>
    </div>
</aside>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/success-alert.js?v=20260909-2"></script>
<script src="/Project/assets/js/form-confirm.js"></script>
<script src="/Project/assets/js/logout-confirm.js?v=20260911-2"></script>
