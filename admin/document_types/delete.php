<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

$_SESSION['admin_error'] = 'ระบบไม่อนุญาตให้ลบแม่แบบเอกสาร';
header('Location: index.php');
exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf_or_abort();

$templateId = filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT);
if (!$templateId) {
    header('Location: index.php?delete_error=invalid');
    exit;
}

$stmt = $pdo->prepare('SELECT template_id, pdf_template, docx_template FROM document_template WHERE template_id = ? LIMIT 1');
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    header('Location: index.php?delete_error=not_found');
    exit;
}

$usedStmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE template_id = ?');
$usedStmt->execute([$templateId]);
if ((int) $usedStmt->fetchColumn() > 0) {
    header('Location: index.php?delete_error=in_use');
    exit;
}

try {
    $pdo->beginTransaction();
    $deleteStmt = $pdo->prepare('DELETE FROM document_template WHERE template_id = ?');
    $deleteStmt->execute([$templateId]);
    $pdo->commit();

    $files = [
        [__DIR__ . '/../../uploads/pdf', $template['pdf_template']],
        [__DIR__ . '/../../uploads/docx', $template['docx_template']],
    ];

    foreach ($files as [$directory, $filename]) {
        if (!$filename) {
            continue;
        }

        $base = realpath($directory);
        $path = $base ? realpath($base . DIRECTORY_SEPARATOR . basename($filename)) : false;
        if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }

    header('Location: index.php?deleted=1');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: index.php?delete_error=failed');
    exit;
}
