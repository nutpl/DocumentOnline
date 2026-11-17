<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/official_internship_pdf_helper.php';

$students = [
    ['prefix_short'=>'นาย','firstname'=>'กฤษณะ','lastname'=>'กงพีพ','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-06-15','end_date'=>'2026-10-02','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการบริษัท'],
    ['prefix_short'=>'นางสาว','firstname'=>'ศศิธร','lastname'=>'เกิดศรีทอง','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-06-15','end_date'=>'2026-10-02','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการบริษัท'],
];
if (($argv[1] ?? '') === 'one') {
    $students = [
        ['prefix_short'=>'นางสาว','firstname'=>'อรวรรณ','lastname'=>'กันหาเขียว','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-06-15','end_date'=>'2026-10-02','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการบริษัท'],
    ];
}

$document = [
    'document_type'=>'send',
    'document_number'=>'อว ๐๖๕๗.๑๗๐๐/ว.๐๙๗',
    'document_date'=>'2026-06-15',
    'company_name'=>'บริษัท คัสโตมิคซ์ จำกัด',
    'reference_text'=>'แบบตอบรับนักศึกษา',
    'signatory_name'=>'รองศาสตราจารย์ ดร.รตินันท์ เหลือมพล',
    'signatory_title_line1'=>'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'signatory_title_line2'=>'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];

$output = realpath(__DIR__ . '/../../output') . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . 'official-send-word-position-test.pdf';
createOfficialInternshipPdf($document, $students)->Output($output, 'F');
