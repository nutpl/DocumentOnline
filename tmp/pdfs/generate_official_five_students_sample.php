<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/official_internship_pdf_helper.php';

$students = [
    ['prefix_short'=>'นาย','firstname'=>'กิตติพงษ์','lastname'=>'วิศวกรรมดี','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-11-02','end_date'=>'2027-02-19','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการฝ่ายทรัพยากรบุคคล'],
    ['prefix_short'=>'นางสาว','firstname'=>'พรรณวรา','lastname'=>'ทิพย์นางรอง','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-11-02','end_date'=>'2027-02-19','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการฝ่ายทรัพยากรบุคคล'],
    ['prefix_short'=>'นาย','firstname'=>'ณัฐพล','lastname'=>'อาคเดช','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-11-02','end_date'=>'2027-02-19','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการฝ่ายทรัพยากรบุคคล'],
    ['prefix_short'=>'นางสาว','firstname'=>'อรวรรณ','lastname'=>'กันหาเขียว','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-11-02','end_date'=>'2027-02-19','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการฝ่ายทรัพยากรบุคคล'],
    ['prefix_short'=>'นาย','firstname'=>'ธนวัฒน์','lastname'=>'ช่องนอก','semester'=>'1','academic_year'=>'2569','start_date'=>'2026-11-02','end_date'=>'2027-02-19','department_name'=>'วิศวกรรมคอมพิวเตอร์','recipient_position'=>'ผู้จัดการฝ่ายทรัพยากรบุคคล'],
];

$base = [
    'document_number'=>'อว ๐๖๕๗.๑๗๐๐/ว.๐๙๗',
    'document_date'=>'2026-08-21',
    'company_name'=>'บริษัท นีสเทิร์น อินดัสทรี จำกัด',
    'response_due_date'=>'2026-09-15',
    'reference_text'=>'หนังสือตอบรับนักศึกษาฝึกประสบการณ์วิชาชีพ',
    'signatory_name'=>'รองศาสตราจารย์ ดร.รตินันท์ เหลือมพล',
    'signatory_title_line1'=>'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'signatory_title_line2'=>'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];

$outputDirectory = realpath(__DIR__ . '/../../output') . DIRECTORY_SEPARATOR . 'pdf';
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ output/pdf');
}

$documents = ['request'=>'official-request-5-students-sample.pdf','send'=>'official-send-5-students-sample.pdf'];
if (($argv[1] ?? '') === 'send-balanced') {
    $documents = ['send'=>'official-send-5-students-balanced.pdf'];
} elseif (($argv[1] ?? '') === 'production-five') {
    $documents = [
        'request'=>'official-request-5-students-production-test.pdf',
        'send'=>'official-send-5-students-production-test.pdf',
    ];
}

foreach ($documents as $type=>$filename) {
    $document = $base;
    $document['document_type'] = $type;
    if ($type === 'send') {
        $document['document_number'] = 'อว ๐๖๕๗.๑๗๐๐/ว.๔๖๐';
    }
    createOfficialInternshipPdf($document, $students)->Output($outputDirectory . DIRECTORY_SEPARATOR . $filename, 'F');
}
