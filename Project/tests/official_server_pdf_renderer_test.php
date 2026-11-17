<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/official_internship_pdf_helper.php';

$students = [[
    'prefix_short' => 'นางสาว',
    'firstname' => 'ทดสอบ',
    'lastname' => 'ระบบ',
    'semester' => '1',
    'academic_year' => '2569',
    'start_date' => '2026-06-15',
    'end_date' => '2026-10-02',
    'department_name' => 'วิศวกรรมคอมพิวเตอร์',
    'recipient_position' => 'ผู้จัดการ',
]];

foreach (['request', 'send'] as $type) {
    $document = [
        'document_type' => $type,
        'document_number' => 'อว ๐๖๕๗.๑๗๐๐/ว.๐๙๗',
        'document_date' => '2026-09-11',
        'company_name' => 'บริษัท ทดสอบ จำกัด',
        'response_due_date' => '2026-09-18',
        'reference_text' => 'แบบตอบรับนักศึกษา',
        'signatory_name' => 'รองศาสตราจารย์ ดร.ทดสอบ ระบบ',
        'signatory_title_line1' => 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
        'signatory_title_line2' => 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
    ];
    foreach ([1, 5] as $studentCount) {
        $letterStudents = array_map(static function (int $index) use ($students): array {
            $student = $students[0];
            $student['firstname'] = 'ทดสอบ' . $index;
            $student['lastname'] = 'ระบบ';
            return $student;
        }, range(1, $studentCount));
        $pdf = createOfficialInternshipPdf($document, $letterStudents)->Output('', 'S');
        if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) < 1000) {
            fwrite(STDERR, "Failed to render {$type} official PDF for {$studentCount} students.\n");
            exit(1);
        }
        $pageCount = preg_match_all('/\/Type\s*\/Page\b/', $pdf);
        if ($pageCount !== 1) {
            fwrite(STDERR, "{$type} official PDF for {$studentCount} students is not one page.\n");
            exit(1);
        }
    }
}

echo "Official server-PDF renderer tests passed.\n";
