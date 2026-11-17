<?php
declare(strict_types=1);

require __DIR__ . '/../../helpers/official_internship_word_helper.php';

$document = [
    'document_number' => 'อว ๐๖๕๗.๑๓๐๐/๑๒๓',
    'document_date' => '2026-09-15',
    'company_name' => 'บริษัทตัวอย่างเพื่อทดสอบรูปแบบเอกสาร จำกัด',
    'recipient_name' => 'ผู้จัดการบริษัทตัวอย่างเพื่อทดสอบรูปแบบเอกสาร จำกัด',
    'semester' => '2',
    'academic_year' => '2569',
    'internship_start_date' => '2026-11-02',
    'internship_end_date' => '2027-02-19',
];

$students = [
    ['prefix_short' => 'นาย', 'firstname' => 'กิตติพงษ์', 'lastname' => 'ทดสอบ', 'department_name' => 'วิศวกรรมคอมพิวเตอร์'],
    ['prefix_short' => 'นางสาว', 'firstname' => 'กานต์พิชชา', 'lastname' => 'ตัวอย่าง', 'department_name' => 'วิศวกรรมคอมพิวเตอร์'],
    ['prefix_short' => 'นาย', 'firstname' => 'ธีรภัทร', 'lastname' => 'พัฒนาระบบ', 'department_name' => 'วิศวกรรมคอมพิวเตอร์'],
    ['prefix_short' => 'นางสาว', 'firstname' => 'พิมพ์ชนก', 'lastname' => 'ตรวจเอกสาร', 'department_name' => 'วิศวกรรมคอมพิวเตอร์'],
    ['prefix_short' => 'นาย', 'firstname' => 'ณัฐวุฒิ', 'lastname' => 'จัดรูปแบบ', 'department_name' => 'วิศวกรรมคอมพิวเตอร์'],
];

saveOfficialInternshipWordFile($document, $students, 'send', __DIR__ . '/send-five-students-one-page-test.docx');
saveOfficialInternshipWordFile($document, $students, 'request', __DIR__ . '/request-five-students-one-page-test.docx');
