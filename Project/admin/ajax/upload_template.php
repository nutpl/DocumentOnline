<?php

session_start();

require_once "../../config/database.php";
require_once "../../helpers/csrf_helper.php";

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$_SESSION['admin_error'] = 'ระบบปิดการนำเข้าแม่แบบเอกสารแล้ว เพื่อคงรูปแบบเอกสารเดิมไว้';
header('Location: ../document_types/index.php');
exit;

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../document_types/index.php");
    exit;
}

verify_csrf_or_abort();

$template_name = trim($_POST['template_name'] ?? '');
$document_type_id = (int)$_POST['document_type_id'];
$imported_by = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| ดึงรหัสเอกสาร
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT document_code
FROM document_type
WHERE document_type_id = ?
");

$stmt->execute([$document_type_id]);

$docType = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$docType) {
    die("ไม่พบประเภทเอกสาร");
}

$documentCode = $docType['document_code'];

/*
|--------------------------------------------------------------------------
| สร้างชื่อไฟล์
|--------------------------------------------------------------------------
*/

$timestamp = date("Ymd_His");
$random = rand(100,999);

/*
|--------------------------------------------------------------------------
| Folder Upload
|--------------------------------------------------------------------------
*/

$pdfDir = "../../uploads/pdf/";
$docxDir = "../../uploads/docx/";

if (!is_dir($pdfDir)) {
    mkdir($pdfDir,0777,true);
}

if (!is_dir($docxDir)) {
    mkdir($docxDir,0777,true);
}

$pdfName = null;
$docxName = null;
$originalFile = null;

/*
|--------------------------------------------------------------------------
| Upload PDF
|--------------------------------------------------------------------------
*/

if (!empty($_FILES['pdf_template']['name'])) {

    $ext = strtolower(pathinfo($_FILES['pdf_template']['name'],PATHINFO_EXTENSION));

    if ($ext != "pdf") {
        die("กรุณาอัปโหลดไฟล์ PDF");
    }

    $originalFile = $_FILES['pdf_template']['name'];

    $pdfName = $documentCode . "_" . $timestamp . "_" . $random . ".pdf";

    if(!move_uploaded_file(
        $_FILES['pdf_template']['tmp_name'],
        $pdfDir.$pdfName
    )){
        die("อัปโหลด PDF ไม่สำเร็จ");
    }

}

/*
|--------------------------------------------------------------------------
| Upload DOCX
|--------------------------------------------------------------------------
*/

if (!empty($_FILES['docx_template']['name'])) {

    $ext = strtolower(pathinfo($_FILES['docx_template']['name'],PATHINFO_EXTENSION));

    if(!in_array($ext,['doc','docx'])){
        die("กรุณาอัปโหลดไฟล์ DOC หรือ DOCX");
    }

    $docxName = $documentCode . "_" . $timestamp . "_" . $random . "." . $ext;

    if(!move_uploaded_file(
        $_FILES['docx_template']['tmp_name'],
        $docxDir.$docxName
    )){
        die("อัปโหลด DOCX ไม่สำเร็จ");
    }

}

/*
|--------------------------------------------------------------------------
| Save Database
|--------------------------------------------------------------------------
*/

try{

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
    INSERT INTO document_template(

        document_type_id,
        template_name,

        pdf_template,
        docx_template,

        original_file,

        version,
        status,

        imported_by

    )

    VALUES(

        ?,
        ?,
        ?,
        ?,
        ?,
        '1.0',
        'draft',
        ?

    )
    ");

    $stmt->execute([

        $document_type_id,
        $template_name,

        $pdfName,
        $docxName,

        $originalFile,

        $imported_by

    ]);

    $pdo->commit();

    header("Location: ../document_types/index.php?success=1");
    exit;

}catch(Exception $e){

    $pdo->rollBack();

    if($pdfName && file_exists($pdfDir.$pdfName)){
        unlink($pdfDir.$pdfName);
    }

    if($docxName && file_exists($docxDir.$docxName)){
        unlink($docxDir.$docxName);
    }

    die($e->getMessage());

}
