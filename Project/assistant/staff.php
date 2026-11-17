<?php
session_start();
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';
requireAssistantWorkspace('staff');
require_once __DIR__ . '/layout.php';
assistantWorkspaceHeader('ศูนย์ช่วยงานสหกิจ', 'ช่วยดำเนินงานเอกสารสหกิจโดยใช้ข้อมูลชุดเดียวกับเจ้าหน้าที่ แต่มีพื้นที่ทำงานแยกต่างหาก', 'staff');
?>
<section>
    <div class="mb-4 flex items-end justify-between gap-4">
        <div><p class="text-sm font-semibold text-red-700">ขั้นตอนเอกสาร</p><h2 class="mt-1 text-xl font-bold text-slate-800">จัดการเอกสารสหกิจ</h2></div>
        <span class="hidden rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 sm:inline-flex">3 พื้นที่ทำงาน</span>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <?php assistantModuleCard('เอกสารสหกิจ', 'ตรวจสอบคำร้องฉบับลงนามและจัดการเอกสารที่เกี่ยวข้อง', 'staff-documents', 'fa-folder-open'); ?>
        <?php assistantModuleCard('หนังสือตอบรับ', 'ตรวจหนังสือตอบรับ บันทึกผล หรือส่งกลับแก้ไข', 'staff-response-letters', 'fa-envelope-open-text'); ?>
        <?php assistantModuleCard('หนังสือราชการ', 'จัดทำหนังสือขอความอนุเคราะห์และใบส่งตัว', 'staff-official-documents', 'fa-file-signature'); ?>
    </div>
</section>

<section class="mt-8">
    <div class="mb-4"><p class="text-sm font-semibold text-red-700">ติดตามและวางแผน</p><h2 class="mt-1 text-xl font-bold text-slate-800">งานต่อเนื่อง</h2></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php assistantModuleCard('แผนการนิเทศ', 'กำหนดอาจารย์นิเทศ วัน เวลา และรูปแบบการนิเทศ', 'staff-supervision', 'fa-calendar-check'); ?>
        <?php assistantModuleCard('ติดตามสถานะสหกิจ', 'บันทึกผลสมัครงานและสถานะการรับเข้าฝึกงาน', 'staff-tracking', 'fa-list-check'); ?>
        <?php assistantModuleCard('ประวัติการดำเนินการ', 'ดูประวัติการเปลี่ยนแปลงของผู้ปฏิบัติงาน', 'history', 'fa-clock-rotate-left'); ?>
    </div>
</section>
<?php assistantWorkspaceFooter(); ?>
