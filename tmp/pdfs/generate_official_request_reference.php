<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/official_internship_pdf_helper.php';

$students = [
    ['prefix_short'=>'นางสาว','firstname'=>'เบญจา','lastname'=>'กุสิทธิพุฒิชา','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-06-15','end_date'=>'2026-10-02','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการข้อมูล'],
    ['prefix_short'=>'นางสาว','firstname'=>'ตติยาพร','lastname'=>'บุญรัตน์','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-06-15','end_date'=>'2026-10-02','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการข้อมูล'],
];

$document = [
    'document_type'=>'request',
    'document_number'=>'อว ๐๖๕๗.๑๗๐๐/ว.๔๖๐',
    'document_date'=>'2026-05-07',
    'company_name'=>'บริษัท นิสเทริน อินดัสทรี จำกัด',
    'response_due_date'=>'2026-05-22',
    'signatory_name'=>'รองศาสตราจารย์ ดร.รตินันท์ เหลือมพล',
    'signatory_title_line1'=>'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'signatory_title_line2'=>'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];

$output = realpath(__DIR__ . '/../../output') . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . 'official-request-word-position-test.pdf';
createOfficialInternshipPdf($document, $students)->Output($output, 'F');
