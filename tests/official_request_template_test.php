<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/official_internship_word_helper.php';

$document = [
    'document_date' => '2026-10-02',
    'company_name' => 'บริษัท ทดสอบ จำกัด',
    'response_due_date' => '2026-10-16',
    'signatory_name' => 'รองศาสตราจารย์ ดร.ทดสอบ ระบบ',
    'signatory_title_line1' => 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'signatory_title_line2' => 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];
$baseStudent = [
    'prefix_short' => 'นาย', 'firstname' => 'ทดสอบ', 'lastname' => 'แม่แบบ',
    'semester' => '2', 'academic_year' => '2569',
    'start_date' => '2026-11-02', 'end_date' => '2027-02-19',
    'department_name' => 'วิศวกรรมคอมพิวเตอร์',
    'recipient_name' => 'ผู้จัดการ', 'recipient_position' => '',
];

$readText = static function (string $path): string {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Cannot open generated request document.');
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!is_string($xml)) throw new RuntimeException('Request document XML is missing.');
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $text = '';
    foreach ($xpath->query('//w:t') as $node) $text .= $node->textContent;
    return $text;
};

$onePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-request-one-' . bin2hex(random_bytes(6)) . '.docx';
$twoPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-request-two-' . bin2hex(random_bytes(6)) . '.docx';
$threePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-request-three-' . bin2hex(random_bytes(6)) . '.docx';
try {
    $multiTemplate = officialInternshipWordTemplatePath('request', false, 2);
    if (hash_file('sha256', $multiTemplate) !== 'edd51476f6eae78ee95fe03c6ffd83ef606353974d70a9630bc4c411fc9d9902') {
        throw new RuntimeException('The approved multi-student request template must remain unchanged.');
    }
    if (!str_ends_with(officialInternshipWordTemplatePath('request', false, 1), 'request_letter_template.docx')) {
        throw new RuntimeException('One-student request template was not selected.');
    }
    if (!str_ends_with(officialInternshipWordTemplatePath('request', false, 2), 'request_letter_multi_template.docx')) {
        throw new RuntimeException('Multi-student request template was not selected.');
    }
    saveOfficialInternshipWordFile($document, [$baseStudent], 'request', $onePath);
    $oneText = $readText($onePath);
    if (!str_contains($oneText, 'ชั้นปีที่ ๔') || !str_contains($oneText, 'ราย นายทดสอบ แม่แบบ') || !str_contains($oneText, 'ภายในวันที่ ๑๖ ตุลาคม ๒๕๖๙')) {
        throw new RuntimeException('One-student request content does not follow the approved template.');
    }
    $zip = new ZipArchive();
    if ($zip->open($onePath) !== true) throw new RuntimeException('Cannot inspect one-student request layout.');
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $dom = new DOMDocument();
    $dom->loadXML((string)$xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $secondStudent = $baseStudent;
    $secondStudent['firstname'] = 'อีกคน';
    saveOfficialInternshipWordFile($document, [$baseStudent, $secondStudent], 'request', $twoPath);
    $twoText = $readText($twoPath);
    if (!str_contains($twoText, 'จำนวน ๒ คน ได้แก่') || !str_contains($twoText, '๑. นายทดสอบ แม่แบบ') || !str_contains($twoText, '๒. นายอีกคน แม่แบบ')) {
        throw new RuntimeException('Multi-student request content does not follow the approved template.');
    }
    $zip = new ZipArchive();
    if ($zip->open($twoPath) !== true) throw new RuntimeException('Cannot inspect multi-student request layout.');
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $dom = new DOMDocument();
    $dom->loadXML((string)$xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $date = $xpath->query('//w:body/w:p[.//w:t[contains(., "ตุลาคม")]]')->item(0);
    $firstBody = $xpath->query('//w:body/w:p[.//w:t[contains(., "ด้วยคณะวิศวกรรมศาสตร์")]]')->item(0);
    if (!$date instanceof DOMElement || !$firstBody instanceof DOMElement
        || $xpath->query('./w:pPr/w:spacing', $date)->length !== 0
        || $xpath->query('./w:pPr/w:spacing', $firstBody)->length !== 0) {
        throw new RuntimeException('Two-student request layout must preserve the approved source spacing.');
    }
    if ($xpath->query('./w:r/w:tab', $date)->length === 0
        || $xpath->query('.//w:r[w:t[contains(., "๑๖ ตุลาคม ๒๕๖๙")]]/w:rPr/w:b', $xpath->query('//w:body/w:p[.//w:t[contains(., "ภายในวันที่")]]')->item(0))->length !== 0) {
        throw new RuntimeException('Request date must retain its source tab and the response deadline must not be bold.');
    }
    $firstStudentRow = $xpath->query('//w:body/w:p[.//w:t[contains(., "๑. นาย")]]')->item(0);
    if (!$firstStudentRow instanceof DOMElement
        || $xpath->query('./w:r/w:tab', $firstStudentRow)->length < 2
        || $xpath->query('./w:pPr/w:tabs/w:tab[@w:pos="3600"]', $firstStudentRow)->length !== 1) {
        throw new RuntimeException('Request student rows must retain the source tab layout and one surname column.');
    }
    foreach (['คณะเห็นว่า', 'ทั้งนี้ โปรด', 'จึงเรียนมาเพื่อโปรดพิจารณา'] as $needle) {
        $paragraph = $xpath->query('//w:body/w:p[.//w:t[contains(., "' . $needle . '")]]')->item(0);
        $spacing = $paragraph instanceof DOMElement ? $xpath->query('./w:pPr/w:spacing', $paragraph)->item(0) : null;
        if (!$spacing instanceof DOMElement
            || $spacing->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'before') !== '120'
            || $spacing->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'after') !== '0') {
            throw new RuntimeException('Request narrative paragraph spacing must be consistent.');
        }
    }
    $reply = $xpath->query('//w:body/w:p[.//w:t[contains(., "ทั้งนี้ โปรด")]]')->item(0);
    $closing = $xpath->query('//w:body/w:p[.//w:t[contains(., "จึงเรียนมาเพื่อโปรดพิจารณา")]]')->item(0);
    foreach ([$reply, $closing] as $paragraph) {
        if (!$paragraph instanceof DOMElement
            || $xpath->query('./w:pPr/w:ind[@w:firstLine="1418"]', $paragraph)->length !== 1
            || $xpath->query('./w:r[.//w:tab]', $paragraph)->length !== 0) {
            throw new RuntimeException('Multi-student request reply and closing must share one first-line position.');
        }
    }
    $thirdStudent = $baseStudent;
    $thirdStudent['firstname'] = 'คนที่สาม';
    saveOfficialInternshipWordFile($document, [$baseStudent, $secondStudent, $thirdStudent], 'request', $threePath);
    $threeText = $readText($threePath);
    foreach (['นายทดสอบ แม่แบบ', 'นายอีกคน แม่แบบ', 'นายคนที่สาม แม่แบบ'] as $name) {
        if (substr_count($threeText, $name) !== 1) {
            throw new RuntimeException('Three-student request contains a duplicated or missing student row.');
        }
    }
    $zip = new ZipArchive();
    if ($zip->open($threePath) !== true) throw new RuntimeException('Cannot inspect three-student request footer layout.');
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $dom = new DOMDocument();
    $dom->loadXML((string)$xml);
    $xpath = new DOMXPath($dom);
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $xpath->registerNamespace('w', $namespace);
    // The template splits this label across Word runs, so match its stable
    // first run instead of expecting the entire phrase in one text node.
    $contact = $xpath->query('//w:body/w:p[.//w:t[contains(., "แผนกงาน")]]')->item(0);
    if (!$contact instanceof DOMElement) {
        throw new RuntimeException('Three-student request contact line is missing.');
    }
    $blankLines = 0;
    for ($node = $contact->previousSibling; $node instanceof DOMElement && $node->localName === 'p'; $node = $node->previousSibling) {
        if ($node->namespaceURI !== $namespace) break;
        $text = '';
        foreach ($xpath->query('.//w:t', $node) as $textNode) $text .= $textNode->textContent;
        if (trim($text) !== '' || $xpath->query('.//w:drawing|.//w:pict|./w:pPr/w:sectPr', $node)->length > 0) break;
        $blankLines++;
    }
    if ($blankLines !== 2) {
        throw new RuntimeException('Three-student request contact line must remain two blank lines below the signature block.');
    }
    echo "Official request template checks passed.\n";
} finally {
    foreach ([$onePath, $twoPath, $threePath] as $path) if (is_file($path)) unlink($path);
}
