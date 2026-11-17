<?php
session_start();
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';
requireAssistantWorkspace('admin');
require_once __DIR__ . '/layout.php';
assistantWorkspaceHeader('ศูนย์ช่วยจัดการระบบ', 'จัดการข้อมูลโครงสร้าง ผู้ใช้ และข้อมูลผู้เกี่ยวข้อง โดยแยกจากหน้าผู้ดูแลระบบหลัก', 'admin');
?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php assistantModuleCard('บัญชีและผู้ใช้งาน', 'ตรวจสอบและจัดการบัญชีผู้ใช้ในระบบ', 'admin-users', 'fa-users-gear'); ?>
    <?php assistantModuleCard('ข้อมูลอาจารย์และเจ้าหน้าที่', 'จัดการข้อมูลบุคลากร ตำแหน่ง และผู้เกี่ยวข้อง', 'admin-teachers', 'fa-chalkboard-user'); ?>
    <?php assistantModuleCard('โครงสร้างการศึกษา', 'จัดการคณะ สาขา กลุ่มเรียน และปฏิทินการศึกษา', 'admin-structure', 'fa-building-columns'); ?>
    <?php assistantModuleCard('ตำแหน่งและผู้ดำรงตำแหน่ง', 'กำหนดตำแหน่งและผู้ลงนามในเอกสาร', 'admin-positions', 'fa-id-badge'); ?>
    <?php assistantModuleCard('ประวัติการดำเนินการ', 'ตรวจสอบว่าใครทำอะไร เมื่อใด และจากพื้นที่ใด', 'history', 'fa-clock-rotate-left'); ?>
</div>
<?php assistantWorkspaceFooter(); ?>
