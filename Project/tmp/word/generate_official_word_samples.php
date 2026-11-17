<?php
require_once __DIR__ . '/../../helpers/official_internship_word_helper.php';

$document = [
    'document_number' => 'อว ๐๖๕๗.๑๗๐๐/ว.๐๙๗',
    'document_date' => '2026-08-21',
    'company_name' => 'บริษัท นีสเทิร์น อินดัสทรี จำกัด',
    'recipient_name' => 'ผู้จัดการฝ่ายทรัพยากรบุคคล บริษัท นีสเทิร์น อินดัสทรี จำกัด',
    'reference_text' => 'แบบตอบรับนักศึกษา',
    'response_due_date' => '2026-09-15',
    'signatory_name' => 'รองศาสตราจารย์ ดร.รตินันท์ เหลือมพล',
    'signatory_title_line1' => 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'signatory_title_line2' => 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];
$students = [[
    'department_name' => 'สาขาวิชาวิศวกรรมคอมพิวเตอร์', 'semester' => '1', 'academic_year' => '2569',
    'start_date' => '2026-06-15', 'end_date' => '2026-10-02', 'prefix_short' => 'นาย',
    'firstname' => 'ทดสอบ', 'lastname' => 'ระบบ',
]];
$directory = __DIR__ . '/official-word-samples';
if (!is_dir($directory)) mkdir($directory, 0777, true);
foreach (['request', 'send', 'response'] as $type) {
    $target = $directory . '/'.$type.'.docx';
    if (!writeOfficialInternshipTemplateWord($document, $students, $type, $target)) {
        $word = createOfficialInternshipWord($document, $students, $type);
        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($target);
    }
}
