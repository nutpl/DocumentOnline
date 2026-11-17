<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../middleware/staff.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/official_template_settings_helper.php';
require_once __DIR__ . '/../helpers/alert_response_helper.php';

$staffStatement = $pdo->prepare('SELECT department_id FROM employee WHERE user_id=? AND employee_type IN ("staff","teacher") LIMIT 1');
$staffStatement->execute([(int)$_SESSION['user_id']]);
$departmentId = (int)$staffStatement->fetchColumn();
if ($departmentId < 1) renderPlainErrorAlert('บัญชีนี้ไม่มีสิทธิ์จัดการแม่แบบหนังสือ');

$defaults = officialTemplateSettingDefaults();
$imageKeys = ['assessment_qr_image', 'steps_qr_image'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $save = $pdo->prepare('INSERT INTO internship_official_template_setting
            (department_id, setting_key, setting_value, updated_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)');
        $currentSettings = officialTemplateSettings($pdo, $departmentId);
        $uploadedImages = [];
        foreach ($imageKeys as $imageKey) {
            $upload = $_FILES[$imageKey] ?? null;
            if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if ((int)($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int)($upload['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('กรุณาอัปโหลด QR Code แบบ PNG ขนาดไม่เกิน 2 MB');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
            if ($mime !== 'image/png') throw new RuntimeException('QR Code ต้องเป็นไฟล์ PNG เท่านั้น');
            $imageInfo = @getimagesize((string)$upload['tmp_name']);
            if (!$imageInfo || $imageInfo[0] < 100 || $imageInfo[1] < 100 || $imageInfo[0] !== $imageInfo[1]) throw new RuntimeException('QR Code ต้องเป็นรูป PNG สี่เหลี่ยมจัตุรัส ขนาดอย่างน้อย 100 x 100 พิกเซล');
            $directory = dirname(__DIR__) . '/uploads/official_templates';
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('ไม่สามารถเตรียมพื้นที่เก็บรูป QR Code ได้');
            $filename = $imageKey . '-d' . $departmentId . '-' . bin2hex(random_bytes(6)) . '.png';
            $target = $directory . '/' . $filename;
            if (!move_uploaded_file((string)$upload['tmp_name'], $target)) throw new RuntimeException('บันทึกรูป QR Code ไม่สำเร็จ');
            $uploadedImages[$imageKey] = 'uploads/official_templates/' . $filename;
        }
        foreach ($defaults as $key => $defaultValue) {
            if (in_array($key, $imageKeys, true) && !isset($uploadedImages[$key])) continue;
            $value = trim((string)($_POST[$key] ?? ''));
            if (isset($uploadedImages[$key])) $value = $uploadedImages[$key];
            if ($value === '') $value = $defaultValue;
            $save->execute([$departmentId, $key, $value, (int)$_SESSION['user_id']]);
        }
        $pdo->commit();
        $_SESSION['app_sweet_alert'] = ['title'=>'บันทึกแม่แบบแล้ว','text'=>'เอกสารที่สร้างหลังจากนี้จะใช้ข้อความที่ตั้งค่าไว้','icon'=>'success'];
        header('Location: official_template_settings.php');
        exit;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMessage = 'บันทึกการตั้งค่าไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
    }
}
$settings = officialTemplateSettings($pdo, $departmentId);
$pageHeading = 'จัดการแม่แบบหนังสือ';
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>จัดการแม่แบบหนังสือ</title><script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head><body class="min-h-screen bg-slate-100 text-slate-900"><?php include __DIR__.'/sidebar.php'; ?>
<div class="min-h-screen lg:ml-72"><?php include __DIR__.'/navbar.php'; ?><main class="p-4 sm:p-6 lg:p-8"><div class="mx-auto max-w-5xl">
<div class="mb-6"><h1 class="text-2xl font-black">จัดการแม่แบบหนังสือ</h1><p class="mt-1 text-slate-500">แก้เฉพาะข้อความที่กำหนดได้ ข้อมูลนักศึกษา บริษัท และรูปแบบหน้าเอกสารถูกควบคุมโดยระบบ</p></div>
<?php if (!empty($errorMessage)): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="space-y-5">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="font-bold text-lg"><i class="fa-solid fa-address-card mr-2 text-red-600"></i>ข้อมูลติดต่อท้ายหนังสือ</h2><label class="mt-4 block"><span class="mb-1 block text-sm font-semibold">ข้อความติดต่อ 1 บรรทัด</span><input required name="footer_contact" value="<?= htmlspecialchars($settings['footer_contact']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><small class="mt-1 block text-slate-500">ควรเป็นข้อความสั้น 1 บรรทัด เพื่อคงเอกสารหน้าเดียว</small></label></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="font-bold text-lg"><i class="fa-solid fa-qrcode mr-2 text-red-600"></i>ข้อความใต้ QR Code</h2><p class="mt-1 text-sm text-slate-500">แต่ละช่องควรมี 4 บรรทัดตามรูปแบบที่อนุมัติแล้ว</p><div class="mt-4 grid gap-4 md:grid-cols-2">
<label><span class="mb-1 block text-sm font-semibold">QR ประเมินผล</span><textarea required rows="4" name="assessment_qr_caption" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 leading-6"><?= htmlspecialchars($settings['assessment_qr_caption']) ?></textarea></label>
<label><span class="mb-1 block text-sm font-semibold">QR ขั้นตอนประเมินผล</span><textarea required rows="4" name="steps_qr_caption" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 leading-6"><?= htmlspecialchars($settings['steps_qr_caption']) ?></textarea></label>
</div></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="font-bold text-lg"><i class="fa-solid fa-image mr-2 text-red-600"></i>รูป QR Code</h2><p class="mt-1 text-sm text-slate-500">อัปโหลด PNG สี่เหลี่ยมจัตุรัส ขนาดไม่เกิน 2 MB ระบบจะคงขนาดและตำแหน่งในเอกสารให้เอง</p><div class="mt-4 grid gap-4 md:grid-cols-2">
<?php foreach (['assessment_qr_image'=>'QR สำหรับประเมินผล', 'steps_qr_image'=>'QR ขั้นตอนประเมินผล'] as $key=>$label): ?><label class="rounded-xl border border-dashed border-slate-300 p-4"><span class="block text-sm font-semibold"><?= $label ?></span><?php if ($settings[$key] !== ''): ?><span class="mt-1 block text-xs text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>มีรูปที่กำหนดไว้แล้ว</span><?php else: ?><span class="mt-1 block text-xs text-slate-500">ยังใช้ QR มาตรฐานของระบบ</span><?php endif; ?><input type="file" name="<?= $key ?>" accept="image/png" class="mt-3 block w-full text-sm"></label><?php endforeach; ?>
</div></section>
<div class="flex justify-end"><button class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 font-bold text-white shadow-sm hover:bg-red-700"><i class="fa-solid fa-floppy-disk"></i>บันทึกการตั้งค่า</button></div>
</form></div></main></div><?php renderQueuedSweetAlert(); ?></body></html>
