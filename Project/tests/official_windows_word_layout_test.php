<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/official_internship_word_helper.php';

$requestedOutput = trim((string)getenv('OFFICIAL_LAYOUT_OUTPUT'));
$output = $requestedOutput !== ''
    ? $requestedOutput
    : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-windows-layout-' . bin2hex(random_bytes(6)) . '.docx';
$document = [
    'document_number' => 'อว ๐๖๕๗.๑๗๐๐/ว.๐๙๗',
    'document_date' => '2026-09-11',
    'company_name' => 'บริษัท ทดสอบ จำกัด',
    'signatory_name' => 'รองศาสตราจารย์ ดร.ทดสอบ ระบบ',
    'signatory_title_line1' => 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน',
    'signatory_title_line2' => 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี',
];
$students = [[
    'prefix_short' => 'นาย',
    'firstname' => 'ทดสอบ',
    'lastname' => 'ระยะห่าง',
    'semester' => '2',
    'academic_year' => '2569',
    'start_date' => '2026-11-02',
    'end_date' => '2027-02-19',
    'department_name' => 'วิศวกรรมคอมพิวเตอร์',
    'recipient_name' => 'ผู้จัดการ',
    'recipient_position' => '',
]];
if (officialInternshipCompanyDownloadFilename($document, 'pdf', 3) !== 'บริษัท ทดสอบ จำกัด (3 คน).pdf') {
    throw new RuntimeException('Download filename does not identify the student count.');
}

try {
    saveOfficialInternshipWordFile($document, $students, 'send', $output);
    $zip = new ZipArchive();
    if ($zip->open($output) !== true) throw new RuntimeException('Cannot open generated DOCX.');
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!is_string($xml) || $xml === '') throw new RuntimeException('Missing document XML.');

    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
    $visibleText = '';
    foreach ($xpath->query('//w:t') as $node) $visibleText .= $node->textContent;

    $paragraphs = $xpath->query('//w:body/w:p');
    $narrative = null;
    $narrativeIndex = null;
    foreach ($paragraphs as $index => $paragraph) {
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
        if (str_contains($text, 'ตามหนังสือที่อ้างถึง')) {
            $narrative = $paragraph;
            $narrativeIndex = $index;
            break;
        }
    }
    if (!$narrative instanceof DOMElement || $narrativeIndex === null) throw new RuntimeException('Narrative paragraph was not generated.');
    $dateParagraph = null;
    foreach ($paragraphs as $paragraph) {
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
        if (str_contains($text, 'กันยายน') && str_contains($text, '๒๕๖๙')) {
            $dateParagraph = $paragraph;
            break;
        }
    }
    if (!$dateParagraph instanceof DOMElement) throw new RuntimeException('Date paragraph was not generated.');

    $checks = [
        'staff_number_is_used' => str_contains($visibleText, 'อว ๐๖๕๗.๑๗๐๐/ว.๐๙๗'),
        'single_student_name_is_bold' => $xpath->query('.//w:r[w:rPr/w:b][.//w:t[contains(., "ทดสอบ ระยะห่าง")]]', $narrative)->length === 1,
        'student_name_run_contains_only_the_name' => $xpath->query('.//w:r[w:rPr/w:b]/w:t[normalize-space()="นายทดสอบ ระยะห่าง"]', $narrative)->length === 1,
        'single_student_sentence_does_not_add_a_list_introduction' => !str_contains($narrative->textContent, 'ดังรายชื่อต่อไปนี้'),
        'narrative_has_no_inherited_manual_breaks' => $xpath->query('./w:r/w:br', $narrative)->length === 0,
        'single_student_narrative_keeps_reference_distribution' => $xpath->query('./w:pPr/w:jc[@w:val="thaiDistribute"]', $narrative)->length === 1,
        'reference_narrative_uses_natural_spacing_adjustment' => $xpath->query('./w:pPr/w:rPr/w:spacing[@w:val="-4"]', $narrative)->length === 1,
        'follow_up_narrative_uses_natural_spacing_adjustment' => $xpath->query('//w:body/w:p[starts-with(normalize-space(string(.)), "ทั้งนี้ ขอให้หน่วยงาน")]/w:pPr/w:rPr/w:spacing[@w:val="-4"]')->length === 1,
        'one_blank_line_before_narrative' => $narrativeIndex > 0 && $xpath->query('.//w:t[normalize-space()]', $paragraphs->item($narrativeIndex - 1))->length === 0,
        'filled_date_is_not_red_placeholder_text' => $xpath->query('.//w:rPr/w:color[@w:val="FF0000"]', $dateParagraph)->length === 0,
        'single_student_template_keeps_both_reference_qrs' => $xpath->query('//v:group[.//v:imagedata]//v:shape[v:imagedata]', $dom)->length >= 2,
        'single_student_qr_group_matches_the_approved_footer_baseline' => $xpath->query('//v:group[.//v:imagedata][contains(@style, "margin-top:678pt")]')->length === 1,
        'single_student_qr_drawing_is_pinned_to_the_page' => $xpath->query('//wp:anchor[wp:docPr/@name="Group 81"]/wp:positionV[@relativeFrom="page"]/wp:posOffset[normalize-space()="8610600"]')->length === 1,
        'single_student_contact_matches_the_approved_footer_baseline' => $xpath->query('//v:shape[starts-with(@id, "Text Box")][.//w:t[contains(., "แผนกงาน")]][contains(@style, "margin-top:780pt")]')->length === 1,
    ];
    foreach ($checks as $label => $passed) {
        if (!$passed) throw new RuntimeException("Check failed: {$label}");
    }

    // The one-student footer must be deterministic: a longer company name
    // may reflow the body, but it must never change the fixed QR/contact
    // coordinates inherited from the same approved template.
    $alternateOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-windows-layout-alternate-' . bin2hex(random_bytes(6)) . '.docx';
    $alternateDocument = $document;
    $alternateDocument['company_name'] = 'บริษัท ชื่อสถานประกอบการทดสอบที่มีความยาวมากกว่าปกติ จำกัด';
    saveOfficialInternshipWordFile($alternateDocument, $students, 'send', $alternateOutput);
    $alternateZip = new ZipArchive();
    if ($alternateZip->open($alternateOutput) !== true) throw new RuntimeException('Cannot open alternate one-student DOCX.');
    $alternateXml = $alternateZip->getFromName('word/document.xml');
    $alternateZip->close();
    if (!is_string($alternateXml) || $alternateXml === '') throw new RuntimeException('Missing alternate one-student document XML.');
    $alternateDom = new DOMDocument();
    $alternateDom->loadXML($alternateXml);
    $alternateXpath = new DOMXPath($alternateDom);
    $alternateXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $alternateXpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    if ($alternateXpath->query('//v:group[.//v:imagedata][contains(@style, "margin-top:678pt")]')->length !== 1
        || $alternateXpath->query('//v:shape[starts-with(@id, "Text Box")][.//w:t[contains(., "แผนกงาน")]][contains(@style, "margin-top:780pt")]')->length !== 1) {
        throw new RuntimeException('One-student footer position changes with company data.');
    }
    if (is_file($alternateOutput)) unlink($alternateOutput);

    $multiOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-windows-layout-multi-' . bin2hex(random_bytes(6)) . '.docx';
    $multiStudents = array_map(static function (int $index) use ($students): array {
        $student = $students[0];
        $student['firstname'] = 'ทดสอบ' . $index;
        $student['lastname'] = 'หลายคน';
        return $student;
    }, [1, 2, 3]);
    saveOfficialInternshipWordFile($document, $multiStudents, 'send', $multiOutput);
    $multiZip = new ZipArchive();
    if ($multiZip->open($multiOutput) !== true) throw new RuntimeException('Cannot open multi-student DOCX.');
    $multiXml = $multiZip->getFromName('word/document.xml');
    $multiZip->close();
    if (!is_string($multiXml) || $multiXml === '') throw new RuntimeException('Missing multi-student document XML.');
    $multiDom = new DOMDocument();
    $multiDom->loadXML($multiXml);
    $multiXpath = new DOMXPath($multiDom);
    $multiXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $multiXpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    $multiXpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
    $multiNarratives = $multiXpath->query('//w:body/w:p[starts-with(normalize-space(string(.)), "ตามหนังสือที่อ้างถึง") or starts-with(normalize-space(string(.)), "ทั้งนี้ ขอให้หน่วยงาน")]');
    if ($multiNarratives->length !== 2) throw new RuntimeException('Multi-student narratives were not generated.');
    $multiList = $multiXpath->query('//w:body/w:p[.//w:t[contains(., "หลายคน")]]');
    if ($multiList->length !== 3) throw new RuntimeException('Three student rows were not generated from the approved template.');
    if ($multiXpath->query('.//w:rPr/w:b|.//w:rPr/w:bCs', $multiList->item(0))->length !== 0) {
        throw new RuntimeException('Multi-student names must remain regular weight.');
    }
    if ($multiXpath->query('./w:r/w:br', $multiNarratives->item(0))->length !== 0
        || $multiXpath->query('./w:r/w:br', $multiNarratives->item(1))->length !== 0) {
        throw new RuntimeException('Multi-student narrative retains inherited manual line breaks.');
    }
    if ($multiXpath->query('./w:pPr/w:rPr/w:spacing[@w:val="-4"]', $multiNarratives->item(0))->length !== 1
        || $multiXpath->query('./w:pPr/w:rPr/w:spacing[@w:val="-4"]', $multiNarratives->item(1))->length !== 1) {
        throw new RuntimeException('Multi-student narratives do not use the approved natural-flow spacing adjustment.');
    }
    $multiQrGroup = $multiXpath->query('//v:group[.//v:imagedata]')->item(0);
    if (!$multiQrGroup instanceof DOMElement || !str_contains((string)$multiQrGroup->getAttribute('style'), 'margin-top:660pt')) {
        throw new RuntimeException('Multi-student QR group is not pinned to the approved footer baseline.');
    }
    if ($multiXpath->query('//wp:anchor[wp:docPr/@name="Group 69"]/wp:positionV[@relativeFrom="page"]/wp:posOffset[normalize-space()="8382000"]')->length !== 1) {
        throw new RuntimeException('Multi-student QR drawing is not pinned to the shared page baseline.');
    }
    $multiContact = $multiXpath->query('//v:shape[starts-with(@id, "Text Box")][.//w:t[contains(., "แผนกงาน")]]')->item(0);
    if (!$multiContact instanceof DOMElement || !str_contains((string)$multiContact->getAttribute('style'), 'margin-top:755pt')) {
        throw new RuntimeException('Multi-student contact line is not positioned directly below its QR captions.');
    }
    $multiContactParagraph = $multiXpath->query('//w:body/w:p[.//w:t[contains(., "แผนกงานวิชาการและวิจัย")]]')->item(0);
    if (!$multiContactParagraph instanceof DOMElement
        || $multiXpath->query('./w:pPr/w:spacing[@w:before="0"]', $multiContactParagraph)->length !== 1) {
        throw new RuntimeException('Multi-student contact line retains extra before-spacing.');
    }
    $blankRowsBeforeContact = 0;
    for ($node = $multiContactParagraph->previousSibling; $node instanceof DOMElement && $node->localName === 'p'; $node = $node->previousSibling) {
        if ($multiXpath->query('.//w:t[normalize-space()]|.//w:pict|.//w:drawing', $node)->length > 0) break;
        $blankRowsBeforeContact++;
    }
    if ($blankRowsBeforeContact !== 2) {
        throw new RuntimeException('Multi-student contact line does not match the approved two-row Word gap.');
    }
    if (is_file($multiOutput)) unlink($multiOutput);

    $fiveOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'official-windows-layout-five-' . bin2hex(random_bytes(6)) . '.docx';
    $fiveStudents = array_map(static function (int $index) use ($students): array {
        $student = $students[0];
        $student['firstname'] = 'ทดสอบ' . $index;
        $student['lastname'] = 'ห้าคน';
        return $student;
    }, [1, 2, 3, 4, 5]);
    saveOfficialInternshipWordFile($document, $fiveStudents, 'send', $fiveOutput);
    $fiveZip = new ZipArchive();
    if ($fiveZip->open($fiveOutput) !== true) throw new RuntimeException('Cannot open five-student DOCX.');
    $fiveXml = $fiveZip->getFromName('word/document.xml');
    $fiveZip->close();
    if (!is_string($fiveXml) || $fiveXml === '') throw new RuntimeException('Missing five-student document XML.');
    $fiveDom = new DOMDocument();
    $fiveDom->loadXML($fiveXml);
    $fiveXpath = new DOMXPath($fiveDom);
    $fiveXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $fiveList = $fiveXpath->query('//w:body/w:p[.//w:t[contains(., "ห้าคน")]]');
    if ($fiveList->length !== 5) throw new RuntimeException('Five student rows were not generated.');
    foreach ($fiveList as $paragraph) {
        if ($fiveXpath->query('./w:pPr/w:spacing[@w:line="240"][@w:lineRule="auto"]', $paragraph)->length !== 1) {
            throw new RuntimeException('Five-student layout does not use the approved 1.0 line spacing.');
        }
    }
    if ($fiveXpath->query('//w:body/w:p/w:pPr/w:pageBreakBefore')->length !== 0) {
        throw new RuntimeException('Five-student layout contains a forced page break.');
    }
    if (is_file($fiveOutput)) unlink($fiveOutput);
    echo "Official Windows Word layout checks passed.\n";
} finally {
    if ($requestedOutput === '' && is_file($output)) unlink($output);
}
