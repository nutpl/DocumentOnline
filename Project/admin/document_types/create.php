<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$_SESSION['admin_error'] = 'ระบบปิดการนำเข้าแม่แบบเอกสารแล้ว เพื่อคงรูปแบบเอกสารเดิมไว้';
header('Location: index.php');
exit;

$pageTitle = 'นำเข้าแม่แบบเอกสาร';

$stmt = $pdo->query("
    SELECT document_type_id, document_code, document_name
    FROM document_type
    WHERE status = 'active'
    ORDER BY document_code
");
$documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>

<div class="mx-auto max-w-5xl space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 px-6 py-7 text-white shadow-xl sm:px-8">
        <div class="absolute -right-14 -top-20 h-52 w-52 rounded-full border-[32px] border-white/5"></div>
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-600/20 text-red-300 ring-1 ring-red-400/20">
                    <i class="fa-solid fa-file-arrow-up text-xl"></i>
                </span>
                <div>
                    <p class="mb-1 text-xs font-bold uppercase tracking-[0.2em] text-red-300">Import template</p>
                    <h1 class="text-2xl font-black sm:text-3xl">นำเข้าแม่แบบเอกสาร</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300">เพิ่มไฟล์ต้นฉบับเพื่อใช้สร้างเอกสารและกำหนดตำแหน่งช่องกรอกในขั้นตอนถัดไป</p>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$documentTypes): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
            <div>
                <h2 class="font-bold">ยังไม่มีประเภทเอกสารที่เปิดใช้งาน</h2>
                <p class="mt-1 text-sm text-amber-700">ต้องเพิ่มหรือเปิดใช้งานประเภทเอกสารก่อน จึงจะสามารถนำเข้าแม่แบบได้</p>
            </div>
        </div>
    <?php endif; ?>

    <form action="../ajax/upload_template.php" method="post" enctype="multipart/form-data" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?= csrf_field() ?>

        <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-pen-to-square"></i></span>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">ข้อมูลแม่แบบ</h2>
                    <p class="mt-0.5 text-sm text-slate-500">กรอกข้อมูลและเลือกไฟล์ที่ต้องการนำเข้าสู่ระบบ</p>
                </div>
            </div>
        </div>

        <div class="space-y-7 px-6 py-7 sm:px-8">
            <div class="grid gap-6 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 flex items-center gap-2 text-sm font-bold text-slate-700">
                        ประเภทเอกสาร <span class="text-red-600">*</span>
                    </span>
                    <select name="document_type_id" required <?= !$documentTypes ? 'disabled' : '' ?>
                            class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-red-300 focus:bg-white focus:ring-4 focus:ring-red-100 disabled:cursor-not-allowed disabled:opacity-60">
                        <option value="">เลือกประเภทเอกสาร</option>
                        <?php foreach ($documentTypes as $documentType): ?>
                            <option value="<?= (int) $documentType['document_type_id'] ?>">
                                <?= htmlspecialchars($documentType['document_code'] . ' — ' . $documentType['document_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="mt-2 block text-xs text-slate-400">ประเภทเอกสารจะกำหนดรหัสชื่อไฟล์ที่ระบบสร้างให้</span>
                </label>

                <label class="block">
                    <span class="mb-2 flex items-center gap-2 text-sm font-bold text-slate-700">
                        ชื่อแม่แบบ <span class="text-red-600">*</span>
                    </span>
                    <input type="text" name="template_name" required maxlength="255" placeholder="เช่น แบบคำร้องขอฝึกงาน รุ่นปี 2569"
                           class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-red-300 focus:bg-white focus:ring-4 focus:ring-red-100">
                    <span class="mt-2 block text-xs text-slate-400">ใช้ชื่อที่แยกเวอร์ชันหรือวัตถุประสงค์ได้ชัดเจน</span>
                </label>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <label class="group block cursor-pointer rounded-2xl border-2 border-dashed border-red-200 bg-red-50/50 p-5 transition hover:border-red-400 hover:bg-red-50">
                    <span class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 transition group-hover:bg-red-600 group-hover:text-white"><i class="fa-solid fa-file-pdf text-lg"></i></span>
                        <span>
                            <span class="block font-bold text-slate-800">PDF Template <span class="text-red-600">*</span></span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">ไฟล์พื้นหลังสำหรับสร้างตัวอย่างและเอกสาร PDF</span>
                        </span>
                    </span>
                    <input type="file" name="pdf_template" accept="application/pdf,.pdf" required
                           class="mt-5 block w-full text-sm text-slate-500 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-red-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white hover:file:bg-red-700">
                </label>

                <label class="group block cursor-pointer rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/40 p-5 transition hover:border-blue-400 hover:bg-blue-50">
                    <span class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700 transition group-hover:bg-blue-600 group-hover:text-white"><i class="fa-solid fa-file-word text-lg"></i></span>
                        <span>
                            <span class="block font-bold text-slate-800">DOCX Template <span class="font-normal text-slate-400">(ไม่บังคับ)</span></span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">ไฟล์ต้นฉบับสำหรับเก็บสำรองหรือแก้ไขภายหลัง</span>
                        </span>
                    </span>
                    <input type="file" name="docx_template" accept="application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.doc,.docx"
                           class="mt-5 block w-full text-sm text-slate-500 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white hover:file:bg-blue-700">
                </label>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-start gap-3 text-sm text-slate-600">
                    <i class="fa-solid fa-circle-info mt-0.5 text-blue-500"></i>
                    <p class="leading-6">หลังบันทึกแล้ว แม่แบบจะอยู่ในสถานะ <strong class="text-amber-700">ฉบับร่าง</strong> คุณต้องกำหนดตำแหน่งช่องกรอกก่อนจึงจะเปิดใช้งานได้</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-5 sm:flex-row sm:items-center sm:justify-end sm:px-8">
            <a href="index.php" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">ยกเลิก</a>
            <button type="submit" <?= !$documentTypes ? 'disabled' : '' ?>
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-600 to-red-700 px-6 text-sm font-bold text-white shadow-lg shadow-red-500/20 transition hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-red-100 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                บันทึกแม่แบบ
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
