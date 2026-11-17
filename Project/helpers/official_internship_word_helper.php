<?php
declare(strict_types=1);

require_once __DIR__ . '/official_internship_pdf_helper.php';
require_once __DIR__ . '/official_template_settings_helper.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Writes a Word file from the official source template without rebuilding its
 * layout.  Send and request letters therefore retain the original margins,
 * table geometry, fonts, and paragraph layout supplied by the faculty.
 */
/**
 * Word downloads always use the faculty-approved Microsoft Word templates.
 * Only Linux PDF conversion opts into a separate LibreOffice copy, so PDF
 * layout work can never alter a document opened in Microsoft Word.
 */
function officialInternshipWordTemplatePath(string $outputType, bool $useLibreOfficeTemplate = false, int $studentCount = 1): string
{
    $filename = $outputType === 'send'
        ? ($studentCount > 1 ? 'send_letter_multi_template.docx' : 'send_letter_template.docx')
        : ($outputType === 'request'
            ? ($studentCount > 1 ? 'request_letter_multi_template.docx' : 'request_letter_template.docx')
            : '');
    if ($filename === '') return '';

    $baseDirectory = __DIR__ . '/../templates/official_internship';
    if ($useLibreOfficeTemplate && PHP_OS_FAMILY !== 'Windows') {
        $libreOfficeTemplate = $baseDirectory . '/libreoffice/' . $filename;
        if (is_file($libreOfficeTemplate)) return $libreOfficeTemplate;
    }
    return $baseDirectory . '/' . $filename;
}

/** Build a safe, company-based download filename for an individual letter. */
function officialInternshipCompanyDownloadFilename(array $document, string $extension, int $studentCount = 0): string
{
    $company = trim((string)($document['company_name'] ?? ''));
    $company = preg_replace('/[<>:"\\\\\/|?*\\x00-\\x1F]/u', ' ', $company) ?? '';
    $company = preg_replace('/\\s+/u', ' ', $company) ?? '';
    $company = rtrim(trim($company), '. ');
    if ($company === '') $company = 'หนังสือฝึกประสบการณ์วิชาชีพ';

    $countSuffix = $studentCount > 0 ? ' (' . $studentCount . ' คน)' : '';
    return $company . $countSuffix . '.' . ltrim($extension, '.');
}

function writeOfficialInternshipTemplateWord(array $document, array $students, string $outputType, string $outputPath, bool $forPdf = false, string $fontOption = 'psk'): bool
{
    // PDF generation must start from the same faculty-approved DOCX as the
    // Windows Word export.  The former LibreOffice-only template and its
    // accumulated layout overrides made the Server document drift away from
    // the official reference.  Font availability is handled by the Docker
    // deployment, not by swapping the document's layout source.
    $useLibreOfficeTemplate = false;
    $template = officialInternshipWordTemplatePath($outputType, $useLibreOfficeTemplate, count($students));
    if ($template === '' || !is_file($template) || !copy($template, $outputPath)) return false;

    $zip = new ZipArchive();
    if ($zip->open($outputPath) !== true) return false;
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) { $zip->close(); return false; }
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    if (!@$dom->loadXML($xml)) { $zip->close(); return false; }
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $company = officialThaiDigits((string)($document['company_name'] ?? ''));
    $department = officialThaiDigits((string)($students[0]['department_name'] ?? 'สาขาวิชา'));
    $semester = officialThaiDigits((string)($students[0]['semester'] ?? ''));
    $academic = officialThaiDigits((string)($students[0]['academic_year'] ?? ''));
    $start = officialThaiDate((string)($students[0]['start_date'] ?? ''));
    $end = officialThaiDate((string)($students[0]['end_date'] ?? ''));
    $count = count($students);
    $countThai = officialThaiDigits((string)$count);
    $names = officialStudentNames($students, true);
    $number = officialFormatDocumentNumber((string)($document['document_number'] ?? ''));
    $date = officialThaiDate((string)($document['document_date'] ?? date('Y-m-d')));
    // ยึดชื่อบุคคลและตำแหน่งจากส่วนที่ 2 ของใบคำร้องเสมอ เพื่อให้
    // เอกสารที่เคยออกไปแล้วดาวน์โหลดใหม่ด้วยข้อมูลผู้รับที่ถูกต้องได้.
    $applicationRecipient = trim(implode(' ', array_filter([
        $students[0]['recipient_name'] ?? '',
        $students[0]['recipient_position'] ?? '',
        $document['company_name'] ?? '',
    ])));
    $recipient = officialThaiDigits($applicationRecipient !== ''
        ? $applicationRecipient
        : (string)($document['recipient_name'] ?? ''));
    $signatory = officialThaiDigits((string)($document['signatory_name'] ?? ''));
    $title1 = officialThaiDigits((string)($document['signatory_title_line1'] ?? ''));
    $title2 = officialThaiDigits((string)($document['signatory_title_line2'] ?? ''));

    if ($outputType === 'send') {
        // Preserve the real send-letter template's tabs, QR block, and footer.
        // Only the individual data runs are changed in the document heading.
        officialWordTemplateReplaceParagraph($xpath, 'ที่  ', 'ที่  '.$number);
        officialWordTemplateReplaceFirstThaiDateParagraph($xpath, $date);
        // The recipient placeholder in the faculty template is split across
        // multiple Word runs. Replacing one text node leaves the old company
        // name visible in exported PDFs, so replace the whole paragraph.
        officialWordTemplateReplaceParagraph($xpath, 'เรียน', 'เรียน   '.$recipient);
        // Follow the newly approved wording exactly. The response form is a
        // two-page attachment for every company, independent of headcount.
        officialWordTemplateReplaceParagraph($xpath, 'อ้างถึง', 'อ้างถึง  แบบตอบรับนักศึกษา จำนวน ๒ แผ่น');
        officialWordTemplateRemoveParagraph($xpath, 'สิ่งที่ส่งมาด้วย  ขั้นตอนการประเมินนักศึกษา');
        officialWordTemplateReplaceTextNode($xpath, '3100', '3194');
        $departmentLabel = str_starts_with($department, 'สาขาวิชา') ? $department : 'สาขาวิชา'.$department;
        $studentText = officialStudentNames($students, true);
        // Keep normal word spaces so Word can reflow the sentence naturally,
        // just as it does in the faculty-approved one-student reference.
        $studentNameSpace = ' ';
        $studentClause = $count === 1
            ? "คณะขอส่งนักศึกษา ราย{$studentNameSpace}{$studentText}{$studentNameSpace}มารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ"
            : 'คณะขอส่งนักศึกษามารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ ดังรายชื่อต่อไปนี้';
        $first = "ตามหนังสือที่อ้างถึง $company ได้ตอบรับนักศึกษา คณะวิศวกรรมศาสตร์และเทคโนโลยี หลักสูตรวิศวกรรมศาสตรบัณฑิต $departmentLabel เข้าฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end จำนวน $countThai คน นั้น $studentClause";
        $second = 'ทั้งนี้ ขอให้หน่วยงานของท่านประเมินผลการปฏิบัติงานนักศึกษา ตาม  QR - Code ด้านล่าง หลังจากที่นักศึกษาปฏิบัติงานเสร็จสิ้นตามระยะเวลาที่กำหนด  (โดยรหัสผ่านในการเข้าระบบจะแจ้งไปยังอีเมลพนักงานที่ปรึกษาของนักศึกษา เพื่อดำเนินการประเมินผลปฏิบัติงานนักศึกษาตามขั้นตอนการประเมินนักศึกษาดังเอกสารแนบท้าย) และขอความอนุเคราะห์หน่วยงานของท่านออกหนังสือรับรองการปฏิบัติงานให้แก่นักศึกษา เพื่อใช้เป็นหลักฐานแสดงการฝึกประสบการณ์วิชาชีพต่อไป';
        officialWordTemplateRemoveParagraphLineBreaks($xpath, 'ตามหนังสือที่อ้างถึง');
        officialWordTemplateReplaceParagraph($xpath, 'ตามหนังสือที่อ้างถึง', $first);
        // Match the approved flowing body text while opening this paragraph by
        // two twips.  Word then carries the end-date “๑๙” to the next line
        // naturally instead of using a fixed line break.
        officialWordTemplateSetReferenceNarrativeCharacterSpacing($xpath, '-4');
        officialWordTemplateReplaceNumberedStudentList($xpath, 'นายกฤษณะ กงทัพ', 'นางสาวศศิธร เกิดศรีทอง', $students);
        officialWordTemplateReplaceParagraph($xpath, 'ทั้งนี้ ขอให้หน่วยงาน', $second);
        // Preserve the template's one-paragraph flow.  Opening its original
        // -6 twip Thai character spacing by only 2 twips lets Word naturally
        // carry “ดัง” and “เพื่อ” onto the next visual lines without adding
        // a hard break, a new paragraph, or a changed indent.
        officialWordTemplateSetFollowUpNarrativeCharacterSpacing($xpath, '-4');
        if ($count === 1 && $studentText !== '') {
            // Keep the name as the only bold run. The surrounding spaces and
            // following words must remain regular text so Word can keep Thai
            // words together when it reflows the distributed paragraph.
            officialWordTemplateBoldTextOccurrence($xpath, 'ตามหนังสือที่อ้างถึง', $studentText);
        }
        officialWordTemplateReplaceTextNode($xpath, 'รองศาสตราจารย์ ดร.รตินันท์ เหลือมพล', $signatory);
        officialWordTemplateReplaceTextNode($xpath, 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน', $title1);
        officialWordTemplateReplaceTextNode($xpath, 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี', $title2);
    } else {
        // The approved request-letter references do not show an outgoing
        // number. Preserve that layout and replace only the visible date.
        officialWordTemplateReplaceFirstThaiDateParagraph($xpath, $date);
        $requestSubject = !empty($document['is_supplemental'])
            ? 'ขอความอนุเคราะห์รับนักศึกษาฝึกประสบการณ์วิชาชีพเพิ่มเติม'
            : 'ขอความอนุเคราะห์รับนักศึกษาฝึกประสบการณ์วิชาชีพ';
        officialWordTemplateReplaceParagraph($xpath, 'เรื่อง', 'เรื่อง   ' . $requestSubject);
        officialWordTemplateReplaceParagraph($xpath, 'เรียน', 'เรียน   '.$recipient);
        // ช่องเอกสารแนบในต้นฉบับกำหนดด้วย tab stop ไม่ใช่ช่องว่างธรรมดา
        // เปลี่ยนเฉพาะเลขจำนวน จึงคงแนวของเลขลำดับและคอลัมน์ “จำนวน” ไว้พอดี
        officialWordTemplateReplaceLastTextValue($xpath, 'สิ่งที่ส่งมาด้วย', '1', $countThai);
        officialWordTemplateReplaceLastTextValue($xpath, 'ใบแสดงผลการเรียน', '1', $countThai);
        // The response form remains two sheets regardless of student count.
        officialWordTemplateReplaceLastTextValue($xpath, '๓. แบบตอบรับนักศึกษา', '2', '๒');
        $departmentLabel = str_starts_with($department, 'สาขาวิชา') ? $department : 'สาขาวิชา'.$department;
        $bodyFirst = "ด้วยคณะวิศวกรรมศาสตร์และเทคโนโลยี ได้กำหนดให้นักศึกษาชั้นปีที่ ๔ มีการออกฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end เพื่อให้นักศึกษามีประสบการณ์ในการปฏิบัติงานและเตรียมตัวออกไปปฏิบัติงานหลังจากสำเร็จการศึกษา";
        $bodySecondPrefix = "คณะเห็นว่า $company เป็นหน่วยงานที่มีการดำเนินงานเกี่ยวข้องกับหลักสูตรวิศวกรรมศาสตรบัณฑิต $departmentLabel ดังนั้น เพื่อเป็นการเสริมสร้างความรู้และประสบการณ์วิชาชีพให้แก่นักศึกษา จึงขอความอนุเคราะห์รับนักศึกษาเข้าฝึกประสบการณ์วิชาชีพ จำนวน $countThai คน";
        $responseDue = !empty($document['response_due_date'])
            ? 'ภายในวันที่ '.officialThaiDate((string)$document['response_due_date'])
            : '';
        $reply = 'ทั้งนี้ โปรดส่งแบบตอบรับนักศึกษาตามสิ่งที่ส่งมาด้วย' . ($responseDue !== '' ? ' '.$responseDue : '');
        officialWordTemplateReplaceParagraph($xpath, 'ด้วยคณะวิศวกรรมศาสตร์', $bodyFirst);
        if ($count === 1) {
            $studentName = officialStudentNames($students, true);
            officialWordTemplateReplaceParagraph($xpath, 'คณะเห็นว่า', $bodySecondPrefix.' ราย '.$studentName.' '.$reply);
        } else {
            $requestStudentParagraph = officialWordTemplateReplaceParagraphWithLineBreaks($xpath, 'คณะเห็นว่า', $bodySecondPrefix.' ได้แก่');
            if ($requestStudentParagraph instanceof DOMElement) {
                // Replace the real numbered rows in the approved template.
                // Inserting a separate list left the original sample rows
                // behind, duplicating names and forcing a second page.
                officialWordTemplateReplaceRequestStudentList($xpath, $requestStudentParagraph, $students);
            }
            officialWordTemplateReplaceParagraph($xpath, 'ทั้งนี้ โปรด', $reply);
        }
        // วันที่กำหนดตอบกลับเป็นส่วนหนึ่งของข้อความเนื้อหา ไม่ใช่ชื่อ
        // นักศึกษา จึงคงน้ำหนักตัวอักษรปกติตามแม่แบบ Word
        officialWordTemplateReplaceParagraph($xpath, 'จึงเรียนมาเพื่อโปรดพิจารณา', 'จึงเรียนมาเพื่อโปรดพิจารณา หวังเป็นอย่างยิ่งว่าคงได้รับความอนุเคราะห์จากหน่วยงานด้วยดี และขอขอบคุณมา ณ โอกาสนี้');
        officialWordTemplateReplaceParagraph($xpath, '(รองศาสตราจารย์', '('.$signatory.')');
        officialWordTemplateReplaceParagraph($xpath, 'รองคณบดีฝ่ายวิชาการ', $title1);
        officialWordTemplateReplaceParagraph($xpath, 'คณบดีคณะวิศวกรรมศาสตร์', $title2);
        // Keep the visible gaps between the three narrative paragraphs equal.
        // Explicitly disable Word's automatic spacing so it cannot vary with
        // the number of student rows or the length of replacement text.
        officialWordTemplateNormalizeRequestNarrativeSpacing($xpath);
        // In the multi-student source template these two paragraphs use
        // different mixtures of spaces and tab stops.  Use one first-line
        // indentation instead, so their opening text starts at the same
        // horizontal position without changing their normal text flow.
        officialWordTemplateAlignRequestReplyAndClosingParagraphs($xpath);
    }
    // Word treats the final blank paragraph in the supplied send template as
    // a real paragraph.  When page one is full (notably with five students),
    // that paragraph flows alone onto a completely blank second page.
    officialWordTemplateRemoveTrailingBlankParagraphs($xpath);
    // Some values in the faculty template (for example the last three digits
    // of the outgoing number) are split across Word runs.  A targeted
    // replacement may therefore preserve an Arabic digit from the sample
    // template.  Normalize every visible text run after all substitutions so
    // official documents always use Thai numerals, regardless of the source
    // template or whether the output is generated on Windows or Linux.
    officialWordTemplateConvertAllTextDigitsToThai($xpath);
    // A request letter can include up to five students.  Keep the official
    // 16 pt type size, but remove only inherited paragraph gaps so the Word
    // document (and its Word-generated PDF) remains on one A4 page.
    // The approved two-student request letter already contains the exact
    // line rhythm and date geometry. Preserve it for two and three students;
    // compact only four or five rows when needed to retain one A4 page.
    if ($outputType === 'request' && $count >= 3) {
        // The approved multi-student request template reserves three blank
        // lines above the contact details.  A third name (or more) makes
        // those layout-only lines push the contact block onto page two.
        // Tighten only that unused footer gap; text size, signature spacing
        // and all visible content remain exactly as in the Word template.
        officialWordTemplateTightenMultiStudentRequestFooter($xpath, $count);
    }
    if ($outputType === 'request' && $count > 3) {
        officialWordTemplateCompactMultiStudentRequestLayout($xpath);
    }
    if ($outputType === 'send' && $count > 1) {
        // The faculty-approved three-student reference already fits one A4
        // page with the source template's own signature and footer spacing.
        // Preserve that layout for two and three students; compact only four
        // or more names, where the extra rows genuinely require it.
        officialWordTemplateCompactMultiStudentSendLayout($xpath, !$forPdf, !$forPdf && $count <= 3);
    }
    if ($outputType === 'send' && $count > 3) {
        // The approved two- and three-student references fit with the
        // original body spacing. Compact the visible body only for four or
        // more students, where it is required to retain one A4 page.
        officialWordTemplateCompactMultiStudentSendBody($xpath);
    }
    $isServerPdf = $forPdf && PHP_OS_FAMILY !== 'Windows';
    // LibreOffice on Linux expands the Thai runs from the faculty DOCX much
    // more than Word does. The Server measurement confirms that 52 half-
    // points renders at 26 pt, so 22 half-points renders at the requested
    // 11 pt size. The downloadable DOCX is never changed; QR captions are
    // reset to 10 pt separately below.
    if ($isServerPdf) {
        officialWordTemplateApplyLibreOfficeFontSize($xpath, 22);
    }
    // The Server converter also retains blank template paragraphs above the
    // date and the signature/QR paragraph.  Word (the approved reference)
    // collapses those spaces. Apply this only to the Server PDF so its lower
    // block and contact line occupy the same one-page positions as Word.
    if ($isServerPdf && $outputType === 'send') {
        officialWordTemplateApplyServerPdfReferencePageSettings($xpath);
        officialWordTemplateApplyLibreOfficeCompactLayout($xpath);
        // The faculty-approved Word references use the template's native
        // Thai distributed paragraphs.  Preserve that source formatting
        // instead of imposing PDF-only line breaks or character spacing.
        officialWordTemplateMoveServerPdfLetterTextDown($xpath);
        officialWordTemplateRemoveServerPdfDateGap($xpath);
        officialWordTemplateMoveServerPdfAddressDown($xpath);
        officialWordTemplateCenterServerPdfSignatureBlock($xpath, $count > 1);
        officialWordTemplateApplyServerPdfReferenceLineSpacing($xpath);
        officialWordTemplateRemoveServerPdfNarrativeGap($xpath);
        officialWordTemplateAddServerPdfClosingGap($xpath);
        officialWordTemplateShiftServerPdfSignatureTextDown($xpath);
    }
    // Keep all letter text at Word's standard 1.0 line spacing. This is
    // applied after PDF-only safeguards so the same readable rhythm is used
    // by send/request letters, for one student and for multiple students.
    officialWordTemplateSetBodyLineSpacingOnePointZero($xpath);
    if ($outputType === 'send') {
        officialWordTemplateAddEvaluationStepsQr($zip, $xpath, $document, $count > 1, $useLibreOfficeTemplate, $isServerPdf);
        officialWordTemplateClearQrFooter($xpath, $document, $count > 1, !$forPdf, $useLibreOfficeTemplate, $isServerPdf);
        if (!$forPdf) {
            officialWordTemplatePinQrGroupToPageFooter($xpath, $count > 1);
            officialWordTemplatePinContactLineToPageFooter($xpath, $count > 1);
        }
        if ($useLibreOfficeTemplate && $count > 1) {
            officialWordTemplateCompactLibreOfficeMultiStudentSendPdf($xpath);
        }
    }
    $zip->addFromString('word/document.xml', $dom->saveXML());
    officialWordRegisterThaiFontFallback($zip);
    $zip->close();
    return true;
}

/**
 * Keep the paired QR component at the approved absolute baseline.  Its source
 * paragraph moves when company names or student names use a different number
 * of wrapped lines; leaving the VML group relative to that paragraph made the
 * QR captions collide with the contact line in otherwise one-page letters.
 */
function officialWordTemplatePinQrGroupToPageFooter(DOMXPath $xpath, bool $hasMultipleStudents = false): void
{
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    $drawingNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    $xpath->registerNamespace('wp', $drawingNamespace);
    // Word renders DrawingML while some converters render the VML fallback.
    // Pin both to the page, otherwise a reflowed company name moves only the
    // DrawingML QR block and creates a different footer gap.
    $topPoints = $hasMultipleStudents ? 660 : 678;
    foreach ($xpath->query('//v:group[.//v:imagedata]') as $group) {
        if (!$group instanceof DOMElement) continue;
        $style = (string)$group->getAttribute('style');
        $style = preg_replace('/margin-top:[^;]*/', 'margin-top:'.$topPoints.'pt', $style) ?? $style;
        if (!str_contains($style, 'mso-position-vertical-relative:')) {
            $style .= ';mso-position-vertical:absolute;mso-position-vertical-relative:page';
        } else {
            $style = preg_replace('/mso-position-vertical-relative:[^;]*;?/', 'mso-position-vertical-relative:page;', $style) ?? $style;
        }
        $group->setAttribute('style', trim($style, ';'));
        break;
    }
    foreach ($xpath->query('//wp:anchor[wp:docPr/@name="Group 81" or wp:docPr/@name="Group 69"]') as $anchor) {
        if (!$anchor instanceof DOMElement || !$anchor->ownerDocument instanceof DOMDocument) continue;
        $position = $xpath->query('./wp:positionV', $anchor)->item(0);
        if (!$position instanceof DOMElement) continue;
        $position->setAttribute('relativeFrom', 'page');
        $offset = $xpath->query('./wp:posOffset', $position)->item(0);
        if (!$offset instanceof DOMElement) {
            $offset = $anchor->ownerDocument->createElementNS($drawingNamespace, 'wp:posOffset');
            $position->appendChild($offset);
        }
        $offset->nodeValue = (string)($topPoints * 12700);
        break;
    }
}

/** Apply standard 1.0 leading to visible body text without moving floating blocks. */
function officialWordTemplateSetBodyLineSpacingOnePointZero(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        // QR captions and the contact line live in floating VML/DrawingML
        // paragraphs. Their geometry is page-relative, so preserve it.
        if ($xpath->query('.//w:pict|.//w:drawing', $paragraph)->length > 0) continue;
        if ($xpath->query('.//w:t[normalize-space()]', $paragraph)->length === 0) continue;

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $properties->appendChild($spacing);
        }
        $spacing->setAttributeNS($namespace, 'w:line', '240');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'auto');
    }
}

/** Keep the floating contact-line textbox at its approved page-footer baseline. */
function officialWordTemplatePinContactLineToPageFooter(DOMXPath $xpath, bool $hasMultipleStudents = false): void
{
    $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $drawingNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    $xpath->registerNamespace('wp', $drawingNamespace);
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

    foreach ($xpath->query('//w:body/w:p[.//w:t[contains(., "แผนกงาน")]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        foreach ($xpath->query('.//wp:anchor', $paragraph) as $anchor) {
            if (!$anchor instanceof DOMElement || !$anchor->ownerDocument instanceof DOMDocument) continue;
            $name = $xpath->query('./wp:docPr/@name', $anchor)->item(0)?->nodeValue ?? '';
            if (!str_starts_with($name, 'Text Box')) continue;
            $position = $xpath->query('./wp:positionV', $anchor)->item(0);
            if (!$position instanceof DOMElement) continue;
            $position->setAttribute('relativeFrom', 'page');
            $offset = $xpath->query('./wp:posOffset', $position)->item(0);
            if (!$offset instanceof DOMElement) {
                $offset = $anchor->ownerDocument->createElementNS($drawingNamespace, 'wp:posOffset');
                $position->appendChild($offset);
            }
            // The one-student floating textbox needs a lower page offset than
            // the multi-student template to clear the QR URLs.  This renders
            // its visible glyphs at the 784.9 pt faculty-reference baseline.
            $contactTopPoints = $hasMultipleStudents ? 755 : 780;
            $offset->nodeValue = (string)($contactTopPoints * 12700);
        }
        foreach ($xpath->query('.//v:shape[starts-with(@id, "Text Box")]', $paragraph) as $shape) {
            if (!$shape instanceof DOMElement) continue;
            $style = (string)$shape->getAttribute('style');
            $style = preg_replace('/mso-position-vertical-relative:[^;]*;?/', 'mso-position-vertical-relative:page;', $style) ?? $style;
            $contactTopPoints = $hasMultipleStudents ? 755 : 780;
            $style = preg_replace('/margin-top:[^;]*/', 'margin-top:'.$contactTopPoints.'pt', $style) ?? $style;
            $shape->setAttribute('style', $style);
        }
        return;
    }
}

/**
 * Register compatibility aliases without changing any run in the approved
 * template.  Replacing TH SarabunIT๙ with PSK/New changes glyph metrics and
 * can move a previously one-page letter onto page two.  Keeping the original
 * run font preserves the faculty layout; these aliases are consulted only if
 * the opening computer does not have that original family installed.
 */
function officialWordRegisterThaiFontFallback(ZipArchive $zip): void
{
    $xml = $zip->getFromName('word/fontTable.xml');
    if (!is_string($xml) || $xml === '') return;
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) return;
    $xpath = new DOMXPath($dom);
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $xpath->registerNamespace('w', $namespace);
    $root = $dom->documentElement;
    if (!$root instanceof DOMElement) return;

    foreach ([
        'TH SarabunIT๙' => 'TH Sarabun PSK',
        'TH Sarabun PSK' => 'TH Sarabun New',
        'TH Sarabun New' => 'TH Sarabun PSK',
    ] as $fontName => $fallback) {
        $font = null;
        foreach ($xpath->query('//w:font') as $candidate) {
            if ($candidate instanceof DOMElement && strcasecmp($candidate->getAttributeNS($namespace, 'name'), $fontName) === 0) {
                $font = $candidate;
                break;
            }
        }
        if (!$font instanceof DOMElement) {
            $font = $dom->createElementNS($namespace, 'w:font');
            $font->setAttributeNS($namespace, 'w:name', $fontName);
            $root->appendChild($font);
        }
        $alternate = $xpath->query('./w:altName', $font)->item(0);
        if (!$alternate instanceof DOMElement) {
            $alternate = $dom->createElementNS($namespace, 'w:altName');
            $font->appendChild($alternate);
        }
        $alternate->setAttributeNS($namespace, 'w:val', $fallback);
    }
    $zip->addFromString('word/fontTable.xml', $dom->saveXML());
}

/**
 * Add the second approved QR code to a send-letter copy without rebuilding
 * the faculty template.  Both QR blocks remain floating in the existing
 * signature area, so the letter body and its one-page layout are unchanged.
 */
function officialWordTemplateAddEvaluationStepsQr(ZipArchive $zip, DOMXPath $xpath, array $document = [], bool $hasMultipleStudents = false, bool $isLibreOfficePdf = false, bool $isServerPdf = false): void
{
    $assetPath = officialWordTemplateConfiguredQrPath(
        (string)($document['_template_steps_qr_image'] ?? ''),
        __DIR__ . '/../templates/official_internship/evaluation_steps_qr.png'
    );
    $asset = is_file($assetPath) ? file_get_contents($assetPath) : false;
    if (!is_string($asset) || $asset === '') return;

    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
    // The faculty-approved one- and multi-student DOCX templates already
    // contain both QR codes and their own anchored captions. Preserve that
    // complete component instead of cloning a third QR block into it.
    $existingGroup = $xpath->query('//v:group[.//v:imagedata]')?->item(0);
    if ($existingGroup instanceof DOMElement
        && $xpath->query('./v:shape[v:imagedata]', $existingGroup)->length >= 2
        && $xpath->query('./v:shape[.//w:txbxContent]', $existingGroup)->length >= 2) {
        return;
    }

    $relationshipXml = $zip->getFromName('word/_rels/document.xml.rels');
    if (!is_string($relationshipXml) || $relationshipXml === '') return;
    $relationshipDom = new DOMDocument();
    if (!@$relationshipDom->loadXML($relationshipXml)) return;
    $relationshipRoot = $relationshipDom->documentElement;
    if (!$relationshipRoot instanceof DOMElement) return;

    $highestId = 0;
    foreach ($relationshipRoot->childNodes as $relationship) {
        if (!$relationship instanceof DOMElement) continue;
        if (preg_match('/^rId(\d+)$/', $relationship->getAttribute('Id'), $match)) {
            $highestId = max($highestId, (int)$match[1]);
        }
    }
    $relationshipId = 'rId' . ($highestId + 1);
    $relationship = $relationshipDom->createElementNS(
        'http://schemas.openxmlformats.org/package/2006/relationships',
        'Relationship'
    );
    $relationship->setAttribute('Id', $relationshipId);
    $relationship->setAttribute(
        'Type',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image'
    );
    $relationship->setAttribute('Target', 'media/evaluation_steps_qr.png');
    $relationshipRoot->appendChild($relationship);
    $zip->addFromString('word/_rels/document.xml.rels', $relationshipDom->saveXML());
    $zip->addFromString('word/media/evaluation_steps_qr.png', $asset);

    $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    // The faculty template splits “QR-Code” across several Word text runs,
    // so identify its sole image-containing VML group instead of matching a
    // single text node.
    $groups = $xpath->query('//v:group[.//v:imagedata]');
    $group = $groups?->item(0);
    if (!$group instanceof DOMElement) return;
    $caption = $xpath->query('./v:shape[.//w:txbxContent]', $group)?->item(0);
    $image = $xpath->query('./v:shape[v:imagedata]', $group)?->item(0);
    if (!$caption instanceof DOMElement || !$image instanceof DOMElement) return;

    $assessmentPath = officialWordTemplateConfiguredQrPath((string)($document['_template_assessment_qr_image'] ?? ''), '');
    if ($assessmentPath !== '' && is_file($assessmentPath)) {
        // image2.png is the assessment QR in the approved send-letter DOCX.
        // Replacing its bytes retains the faculty template's proven geometry.
        $assessmentData = file_get_contents($assessmentPath);
        if (is_string($assessmentData) && $assessmentData !== '') $zip->addFromString('word/media/image2.png', $assessmentData);
    }

    // Keep the source template's coordinate origin.  The two blocks sit
    // beneath the text (as in the supplied official sample), to the left of
    // the existing signature—not beside it or over the footer.
    // Multiple student names extend the body lower. Move the complete QR
    // group upward only for that case, preserving the approved single-student
    // layout and leaving enough room for the contact line on page one.
    // The approved multi-student letter uses the template's original QR
    // anchor.  A former 3 pt override lifted the QR pair into the signature
    // column and no longer matched the signed faculty reference.
    $groupTopMargin = '12.75pt';
    if (PHP_OS_FAMILY === 'Windows' && !$hasMultipleStudents) {
        // In the approved one-student Windows reference, the QR pair sits
        // below the full signature block, immediately above the contact line.
        // It is a floating VML group, so this does not alter paragraph flow.
        $groupTopMargin = '55pt';
    }
    if ($isServerPdf) {
        // In the approved Word layout, the visible QR tops sit just below
        // the parenthesised signatory name. LibreOffice anchors VML from the
        // paragraph's top edge, so compensate only the Server PDF group.
        $groupTopMargin = '22pt';
    }
    if ($isLibreOfficePdf) {
        // Align the visible QR tops with the signatory-name line, exactly as
        // in the approved source.  This changes only the floating QR group.
        $groupTopMargin = '59pt';
    }
    if ($isServerPdf && $hasMultipleStudents) {
        // Align the multi-student QR tops with the parenthesised signatory
        // line, leaving the approved signature block unchanged.
        $groupTopMargin = '40pt';
    }
    // Word automatically lets the VML caption run past its nominal group
    // boundary. LibreOffice clips that boundary instead. Expand only the
    // Server PDF group's drawable area while preserving the 0.05 pt/unit
    // coordinate scale, so neither QR image moves.
    // LibreOffice honours the VML group's boundaries.  Reserve enough width
    // for the second QR caption and enough height for the contact line on the
    // same page, without moving either QR image or changing Word downloads.
    $captionWidth = $isLibreOfficePdf ? '3000' : ($isServerPdf ? '2400' : '1900');
    $groupWidth = $isLibreOfficePdf ? '320pt' : ($isServerPdf ? '300pt' : '220pt');
    $groupCoordWidth = $isLibreOfficePdf ? '6400' : ($isServerPdf ? '6000' : '4400');
    // Keep the coordinate scale identical horizontally and vertically. The
    // previous 300 x 120 pt group scaled a square QR into a rectangle.
    $groupHeight = '150pt';
    $group->setAttribute(
        'style',
        // The approved example keeps a clear gutter between the second QR
        // caption and the signature block.  Anchor the paired QR block a
        // little farther left, without changing any text or signature tabs.
        'position:absolute;margin-left:8pt;margin-top:' . $groupTopMargin . ';width:' . $groupWidth . ';height:' . $groupHeight . ';z-index:251646976'
    );
    $group->setAttribute('coordorigin', '1695,13604');
    $group->setAttribute('coordsize', $groupCoordWidth . ',3000');
    $image->setAttribute('style', 'position:absolute;left:1525;top:13604;width:1050;height:1050');
    $caption->setAttribute('style', 'position:absolute;left:1545;top:14760;width:' . $captionWidth . ';height:1200');
    $defaults = officialTemplateSettingDefaults();
    $assessmentCaption = officialWordTemplateCaptionLines(
        (string)($document['_template_assessment_qr_caption'] ?? $defaults['assessment_qr_caption']),
        $defaults['assessment_qr_caption']
    );
    $stepsCaption = officialWordTemplateCaptionLines(
        (string)($document['_template_steps_qr_caption'] ?? $defaults['steps_qr_caption']),
        $defaults['steps_qr_caption']
    );
    // Keep QR captions at their approved Server size while the rest of the
    // document uses the slightly larger Server body size.
    $captionHalfPoints = $isLibreOfficePdf ? 14 : ($isServerPdf ? 16 : 20);
    officialWordTemplateSetQrCaption($xpath, $caption, $assessmentCaption, $captionHalfPoints, $isLibreOfficePdf, $isServerPdf);

    $secondCaption = $caption->cloneNode(true);
    $secondImage = $image->cloneNode(true);
    if (!$secondCaption instanceof DOMElement || !$secondImage instanceof DOMElement) return;
    $secondCaption->setAttribute('id', '_x0000_s2150');
    // Align both caption blocks on the same baseline.  The second image is
    // compensated for its built-in quiet zone, but its visible QR area ends
    // at the same level as the first QR.
    $secondCaption->setAttribute('style', 'position:absolute;left:3600;top:14760;width:' . $captionWidth . ';height:1200');
    $secondImage->setAttribute('id', '_x0000_s2151');
    // The generated steps QR contains the standard quiet-zone margin while
    // the faculty's existing QR image is tightly cropped.  Scale only this
    // image so their visible black QR areas are the same size.
    // Increase both dimensions by the same ratio.  This offsets the second
    // asset's built-in quiet zone without stretching the QR code.
    $secondImage->setAttribute('style', 'position:absolute;left:3442;top:13526;width:1167;height:1167');
    $secondImageData = $xpath->query('./v:imagedata', $secondImage)?->item(0);
    if ($secondImageData instanceof DOMElement) {
        $secondImageData->setAttributeNS($relationshipNamespace, 'r:id', $relationshipId);
        $secondImageData->setAttribute('o:title', 'QR-code evaluation steps');
    }
    officialWordTemplateSetQrCaption($xpath, $secondCaption, $stepsCaption, $captionHalfPoints, $isLibreOfficePdf, $isServerPdf);
    $group->appendChild($secondCaption);
    $group->appendChild($secondImage);
}

/**
 * Use the alignment behavior from the approved Word template for the first
 * narrative paragraph, while keeping ordinary two-sided justification for
 * the follow-up paragraph.  The reference paragraph must distribute its last
 * visible line as well; LibreOffice only does that reliably with Word's
 * Thai-distributed value.  `distribute` is deliberately avoided because it
 * expands individual Thai characters.
 * This deliberately runs only for the Server PDF copy.
 */
function officialWordTemplateJustifyServerPdfNarrative(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $trimmed = trim($text);
        $isReferenceNarrative = str_starts_with($trimmed, 'ตามหนังสือที่อ้างถึง');
        $isFollowUpNarrative = str_starts_with($trimmed, 'ทั้งนี้ ขอให้หน่วยงาน');
        if (!$isReferenceNarrative && !$isFollowUpNarrative) {
            continue;
        }
        // Keep the same 11 pt type as the reference paragraph. A small
        // follow-up-only tightening lets its approved four lines fit without
        // the horizontal-scale behavior that changes the right edge.
        $characterSpacingValue = $isFollowUpNarrative ? '-4' : '-6';

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        if (!$isReferenceNarrative) {
            $indent = $xpath->query('./w:ind', $properties)->item(0);
            if (!$indent instanceof DOMElement) {
                $indent = $paragraph->ownerDocument->createElementNS($namespace, 'w:ind');
                $properties->appendChild($indent);
            }
            // Preserve the template's tab-based first-line position.  Only
            // its original first-line indentation is specified here; adding
            // explicit left/right values changes the usable line width.
            $indent->removeAttributeNS($namespace, 'left');
            $indent->removeAttributeNS($namespace, 'right');
            $indent->setAttributeNS($namespace, 'w:firstLine', '1418');
        }
        $alignment = $xpath->query('./w:jc', $properties)->item(0);
        if (!$alignment instanceof DOMElement) {
            $alignment = $paragraph->ownerDocument->createElementNS($namespace, 'w:jc');
            $properties->appendChild($alignment);
        }
        // Distribute the follow-up paragraph so every visible line reaches
        // the right text margin, matching the requested Word alignment.
        $alignment->setAttributeNS($namespace, 'w:val', 'distribute');

        // Mirror the faculty Word template's -6 twip character spacing.
        // LibreOffice otherwise expands Thai-to-Latin transitions differently
        // from Word, which is especially visible around “ตาม QR - Code”.
        $paragraphRunProperties = $xpath->query('./w:rPr', $properties)->item(0);
        if (!$paragraphRunProperties instanceof DOMElement) {
            $paragraphRunProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
            $properties->appendChild($paragraphRunProperties);
        }
        $paragraphCharacterSpacing = $xpath->query('./w:spacing', $paragraphRunProperties)->item(0);
        if (!$paragraphCharacterSpacing instanceof DOMElement) {
            $paragraphCharacterSpacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $paragraphRunProperties->appendChild($paragraphCharacterSpacing);
        }
        $paragraphCharacterSpacing->setAttributeNS($namespace, 'w:val', $characterSpacingValue);

        // Both source paragraphs use their template tab runs to establish the
        // intended first-line position; leave those runs intact.
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
            $textNode->nodeValue = preg_replace('/ {2,}/u', ' ', $textNode->textContent) ?? $textNode->textContent;
        }
        if ($isReferenceNarrative) {
            officialWordTemplateApplyReferenceNarrativeBreaks($xpath, $paragraph);
        } else {
            // Keep the naturally short final sentence out of Distributed
            // alignment.  The preceding four visual lines remain distributed
            // to the right margin; this tail stays readable at normal width.
            officialWordTemplateSplitFollowUpNarrativeTail($xpath, $paragraph);
            officialWordTemplateApplyFollowUpNarrativeBreaks($xpath, $paragraph);
        }
        // The source DOCX splits “ตาม QR - Code” across runs. LibreOffice
        // can drop the inter-run space, so keep the official wording intact.
        $paragraphText = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $paragraphText .= $textNode->textContent;
        if (preg_match('/ตาม\s*QR\s*-\s*Code/u', $paragraphText) === 1) {
            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
                $textNode->nodeValue = preg_replace('/ตาม\s*QR\s*-\s*Code/u', 'ตาม  QR - Code', $textNode->textContent) ?? $textNode->textContent;
            }
        }
        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $runProperties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$runProperties instanceof DOMElement) {
                $runProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($runProperties, $run->firstChild);
            }
            $characterSpacing = $xpath->query('./w:spacing', $runProperties)->item(0);
            if (!$characterSpacing instanceof DOMElement) {
                $characterSpacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
                $runProperties->appendChild($characterSpacing);
            }
            $runText = '';
            foreach ($xpath->query('.//w:t', $run) as $textNode) $runText .= $textNode->textContent;
            // The last two fixed distributed lines are a little wider in
            // LibreOffice than the first two. Tighten only those lines by one
            // twip so all four right endpoints share the same visual column.
            $runSpacingValue = $characterSpacingValue;
            if ($isFollowUpNarrative && str_starts_with($runText, 'พนักงานที่ปรึกษาของนักศึกษา')) {
                $runSpacingValue = '-5';
            } elseif ($isFollowUpNarrative && str_starts_with($runText, 'ดังเอกสารแนบท้าย)')) {
                // The fourth line is now shorter after moving “นักศึกษา เพื่อ”
                // to the final line, so open its spacing to reach the same
                // right edge as the reference paragraph.
                $runSpacingValue = '-1';
            }
            $characterSpacing->setAttributeNS($namespace, 'w:val', $runSpacingValue);
        }
    }
}

/**
 * Move the final clause of the follow-up narrative into an adjacent, ordinary
 * paragraph.  Beginning it with “นักศึกษา เพื่อ” keeps the fourth line short
 * enough to distribute cleanly and avoids stretching the final line.
 */
function officialWordTemplateSplitFollowUpNarrativeTail(DOMXPath $xpath, DOMElement $paragraph): void
{
    if (!$paragraph->ownerDocument instanceof DOMDocument || !$paragraph->parentNode instanceof DOMNode) return;
    $tail = 'นักศึกษา เพื่อใช้เป็นหลักฐานแสดงการฝึกประสบการณ์วิชาชีพต่อไป';
    $text = '';
    foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
    $tailOffset = mb_strpos($text, $tail, 0, 'UTF-8');
    if ($tailOffset === false) return;

    $head = rtrim(mb_substr($text, 0, $tailOffset, 'UTF-8'));
    $headTextNodes = $xpath->query('.//w:t', $paragraph);
    if ($headTextNodes->length === 0) return;
    $headTextNodes->item(0)->nodeValue = $head;
    for ($index = 1; $index < $headTextNodes->length; $index++) $headTextNodes->item($index)->nodeValue = '';

    $tailParagraph = $paragraph->cloneNode(true);
    if (!$tailParagraph instanceof DOMElement) return;
    $tailTextNodes = $xpath->query('.//w:t', $tailParagraph);
    if ($tailTextNodes->length === 0) return;
    $tailTextNodes->item(0)->nodeValue = $tail;
    for ($index = 1; $index < $tailTextNodes->length; $index++) $tailTextNodes->item($index)->nodeValue = '';

    foreach ($xpath->query('.//w:r[w:tab and not(w:t)]', $tailParagraph) as $tabRun) {
        $tabRun->parentNode?->removeChild($tabRun);
    }
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $properties = $xpath->query('./w:pPr', $tailParagraph)->item(0);
    if ($properties instanceof DOMElement) {
        $alignment = $xpath->query('./w:jc', $properties)->item(0);
        if (!$alignment instanceof DOMElement) {
            $alignment = $paragraph->ownerDocument->createElementNS($namespace, 'w:jc');
            $properties->appendChild($alignment);
        }
        $alignment->setAttributeNS($namespace, 'w:val', 'left');
        $indent = $xpath->query('./w:ind', $properties)->item(0);
        if ($indent instanceof DOMElement) {
            $indent->removeAttributeNS($namespace, 'firstLine');
            $indent->removeAttributeNS($namespace, 'left');
            $indent->removeAttributeNS($namespace, 'right');
        }
    }
    $paragraph->parentNode->insertBefore($tailParagraph, $paragraph->nextSibling);
}

/**
 * Keep the four distributed lines of the follow-up narrative at the approved
 * clause boundaries. The fifth line begins “นักศึกษา เพื่อ” as a normal
 * paragraph, added by officialWordTemplateSplitFollowUpNarrativeTail().
 */
function officialWordTemplateApplyFollowUpNarrativeBreaks(DOMXPath $xpath, DOMElement $paragraph): void
{
    if (!$paragraph->ownerDocument instanceof DOMDocument) return;
    $lines = [
        'ทั้งนี้ ขอให้หน่วยงานของท่านประเมินผลการปฏิบัติงานนักศึกษา ตาม  QR - Code ด้านล่าง',
        'หลังจากที่นักศึกษาปฏิบัติงานเสร็จสิ้นตามระยะเวลาที่กำหนด  (โดยรหัสผ่านในการเข้าระบบจะแจ้งไปยังอีเมล',
        'พนักงานที่ปรึกษาของนักศึกษา เพื่อดำเนินการประเมินผลปฏิบัติงานนักศึกษาตามขั้นตอนการประเมินนักศึกษา',
        'ดังเอกสารแนบท้าย) และขอความอนุเคราะห์หน่วยงานของท่านออกหนังสือรับรองการปฏิบัติงานให้แก่',
    ];
    $textNodes = $xpath->query('.//w:t', $paragraph);
    if ($textNodes->length === 0) return;
    $firstTextNode = $textNodes->item(0);
    $sourceRun = $firstTextNode?->parentNode;
    if (!$firstTextNode instanceof DOMElement || !$sourceRun instanceof DOMElement) return;

    $firstTextNode->nodeValue = array_shift($lines) ?? '';
    for ($index = 1; $index < $textNodes->length; $index++) $textNodes->item($index)->nodeValue = '';

    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $anchor = $sourceRun->nextSibling;
    foreach ($lines as $line) {
        $breakRun = $paragraph->ownerDocument->createElementNS($namespace, 'w:r');
        $breakRun->appendChild($paragraph->ownerDocument->createElementNS($namespace, 'w:br'));
        $lineRun = $paragraph->ownerDocument->createElementNS($namespace, 'w:r');
        $sourceProperties = $xpath->query('./w:rPr', $sourceRun)->item(0);
        if ($sourceProperties instanceof DOMElement) $lineRun->appendChild($sourceProperties->cloneNode(true));
        $lineText = $paragraph->ownerDocument->createElementNS($namespace, 'w:t');
        $lineText->nodeValue = $line;
        $lineRun->appendChild($lineText);
        $paragraph->insertBefore($breakRun, $anchor);
        $paragraph->insertBefore($lineRun, $anchor);
    }
}

/**
 * Keep the three required clause boundaries inside the original paragraph.
 * Preserving one paragraph keeps its template width and page flow unchanged.
 */
function officialWordTemplateApplyReferenceNarrativeBreaks(DOMXPath $xpath, DOMElement $paragraph): void
{
    if (!$paragraph->ownerDocument instanceof DOMDocument) return;
    $text = '';
    foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
    $text = preg_replace('/คณะวิศวกรรมศาสตร์\s*และเทคโนโลยี/u', "คณะวิศวกรรมศาสตร์\nและเทคโนโลยี", $text) ?? $text;
    $text = preg_replace('/วิชาชีพ\s*ภาคการศึกษาที่/u', "วิชาชีพ\nภาคการศึกษาที่", $text) ?? $text;
    $text = preg_replace('/\s+จำนวน\s+/u', "\nจำนวน ", $text) ?? $text;
    $lines = preg_split('/\R/u', $text) ?: [$text];
    if (count($lines) !== 4) return;
    $textNodes = $xpath->query('.//w:t', $paragraph);
    if ($textNodes->length === 0) return;
    $firstTextNode = $textNodes->item(0);
    $sourceRun = $firstTextNode?->parentNode;
    if (!$firstTextNode instanceof DOMElement || !$sourceRun instanceof DOMElement) return;
    $firstTextNode->nodeValue = array_shift($lines) ?? '';
    for ($index = 1; $index < $textNodes->length; $index++) $textNodes->item($index)->nodeValue = '';

    $anchor = $sourceRun->nextSibling;
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($lines as $line) {
        $breakRun = $paragraph->ownerDocument->createElementNS($namespace, 'w:r');
        $breakRun->appendChild($paragraph->ownerDocument->createElementNS($namespace, 'w:br'));
        $lineRun = $paragraph->ownerDocument->createElementNS($namespace, 'w:r');
        $sourceProperties = $xpath->query('./w:rPr', $sourceRun)->item(0);
        if ($sourceProperties instanceof DOMElement) $lineRun->appendChild($sourceProperties->cloneNode(true));
        $lineText = $paragraph->ownerDocument->createElementNS($namespace, 'w:t');
        $lineText->nodeValue = $line;
        $lineRun->appendChild($lineText);
        $paragraph->insertBefore($breakRun, $anchor);
        $paragraph->insertBefore($lineRun, $anchor);
    }
}

/**
 * Lock the Server conversion to the section geometry in the approved Word
 * letter: A4 (210 x 297 mm), 3 cm left, 2 cm right, and the original header
 * and footer distances.  The template already contains these values, but
 * writing them explicitly prevents LibreOffice from applying a fallback page
 * style when it opens the generated DOCX.
 */
function officialWordTemplateApplyServerPdfReferencePageSettings(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $section = $xpath->query('//w:body/w:sectPr')->item(0);
    if (!$section instanceof DOMElement || !$section->ownerDocument instanceof DOMDocument) return;

    $pageSize = $xpath->query('./w:pgSz', $section)->item(0);
    if (!$pageSize instanceof DOMElement) {
        $pageSize = $section->ownerDocument->createElementNS($namespace, 'w:pgSz');
        $section->appendChild($pageSize);
    }
    $pageSize->setAttributeNS($namespace, 'w:w', '11907');
    $pageSize->setAttributeNS($namespace, 'w:h', '16840');
    $pageSize->setAttributeNS($namespace, 'w:code', '9');

    $margins = $xpath->query('./w:pgMar', $section)->item(0);
    if (!$margins instanceof DOMElement) {
        $margins = $section->ownerDocument->createElementNS($namespace, 'w:pgMar');
        $section->appendChild($margins);
    }
    foreach ([
        // Keep the official top/side geometry.  The last contact line is a
        // server-PDF-only element below the floating QR captions, so release
        // the template's 0.41 cm bottom reserve only in this conversion copy.
        'top' => '142', 'right' => '1134', 'bottom' => '0', 'left' => '1701',
        'header' => '720', 'footer' => '720', 'gutter' => '0',
    ] as $name => $value) {
        $margins->setAttributeNS($namespace, 'w:' . $name, $value);
    }
}

/** Move the letter text down slightly while leaving the Garuda/header table fixed. */
function officialWordTemplateMoveServerPdfLetterTextDown(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $dateParagraph = $xpath->query('//w:body/w:p[.//w:t[normalize-space()]]')->item(0);
    if (!$dateParagraph instanceof DOMElement || !$dateParagraph->ownerDocument instanceof DOMDocument) return;
    $properties = $xpath->query('./w:pPr', $dateParagraph)->item(0);
    if (!$properties instanceof DOMElement) {
        $properties = $dateParagraph->ownerDocument->createElementNS($namespace, 'w:pPr');
        $dateParagraph->insertBefore($properties, $dateParagraph->firstChild);
    }
    $spacing = $xpath->query('./w:spacing', $properties)->item(0);
    if (!$spacing instanceof DOMElement) {
        $spacing = $dateParagraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $properties->appendChild($spacing);
    }
    // Use the source header's top edge; no additional gap is needed.
    $spacing->setAttributeNS($namespace, 'w:before', '0');
}

/** Remove the one blank paragraph that LibreOffice retains between date and subject. */
function officialWordTemplateRemoveServerPdfDateGap(DOMXPath $xpath): void
{
    $dateParagraph = $xpath->query('//w:body/w:p[.//w:t[normalize-space()]]')->item(0);
    if (!$dateParagraph instanceof DOMElement) return;
    $next = $dateParagraph->nextSibling;
    if (!$next instanceof DOMElement || $next->localName !== 'p') return;
    $text = '';
    foreach ($xpath->query('.//w:t', $next) as $textNode) $text .= $textNode->textContent;
    if (trim($text) === '' && $xpath->query('.//w:pict|.//w:drawing', $next)->length === 0) {
        $next->parentNode?->removeChild($next);
    }
}

/** Align the first line of the right-side address with the outgoing-number line. */
function officialWordTemplateMoveServerPdfAddressDown(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $addressParagraph = $xpath->query('//w:body/w:tbl[1]/w:tr[1]/w:tc[3]/w:p[.//w:t[contains(., "คณะวิศวกรรมศาสตร์")]]')->item(0);
    if (!$addressParagraph instanceof DOMElement || !$addressParagraph->ownerDocument instanceof DOMDocument) return;
    $properties = $xpath->query('./w:pPr', $addressParagraph)->item(0);
    if (!$properties instanceof DOMElement) {
        $properties = $addressParagraph->ownerDocument->createElementNS($namespace, 'w:pPr');
        $addressParagraph->insertBefore($properties, $addressParagraph->firstChild);
    }
    $spacing = $xpath->query('./w:spacing', $properties)->item(0);
    if (!$spacing instanceof DOMElement) {
        $spacing = $addressParagraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $properties->appendChild($spacing);
    }
    // PDF measurement shows this cell begins 21 pt above the outgoing-number
    // line. Keep the Garuda anchored and align only the address text to it.
    $spacing->setAttributeNS($namespace, 'w:before', '420');
    $spacing->setAttributeNS($namespace, 'w:after', '0');
}

/** Center the closing and every signatory line in the same indented column. */
function officialWordTemplateCenterServerPdfSignatureBlock(DOMXPath $xpath, bool $hasMultipleStudents = false): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $trimmed = trim($text);
        $displayText = '';
        if (str_starts_with($trimmed, 'ขอแสดงความนับถือ')) {
            $displayText = 'ขอแสดงความนับถือ';
        } elseif (preg_match('/\(รองศาสตราจารย์[^)]*\)/u', $text, $matches) === 1) {
            // This name shares the paragraph that anchors the floating QR
            // drawings. Keep the drawing run and rebuild only its text run.
            $displayText = $matches[0];
        } elseif (str_starts_with($trimmed, 'รองคณบดีฝ่ายวิชาการและวิจัย')) {
            $displayText = 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน';
        } elseif (str_starts_with($trimmed, 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี')) {
            $displayText = 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี';
        }
        if ($displayText === '') continue;

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $indent = $xpath->query('./w:ind', $properties)->item(0);
        if (!$indent instanceof DOMElement) {
            $indent = $paragraph->ownerDocument->createElementNS($namespace, 'w:ind');
            $properties->appendChild($indent);
        }
        // 3153 twips is the source Word template's signature-column offset.
        $indent->setAttributeNS($namespace, 'w:left', '3153');
        $indent->setAttributeNS($namespace, 'w:right', '0');
        $indent->setAttributeNS($namespace, 'w:firstLine', '0');
        $alignment = $xpath->query('./w:jc', $properties)->item(0);
        if (!$alignment instanceof DOMElement) {
            $alignment = $paragraph->ownerDocument->createElementNS($namespace, 'w:jc');
            $properties->appendChild($alignment);
        }
        $alignment->setAttributeNS($namespace, 'w:val', 'center');

        // Word's original signature column is created with tabs plus many
        // literal spaces. LibreOffice applies both, which shifts and wraps
        // the signer lines. Retain any picture/drawing run for QR, remove
        // only the direct text/tab runs, then add one clean centred text run.
        foreach ($xpath->query('./w:tabs', $properties) as $tabs) $properties->removeChild($tabs);
        $fontSource = $xpath->query('./w:r[.//w:t and not(.//w:pict) and not(.//w:drawing)]', $paragraph)->item(0);
        $runProperties = $xpath->query('./w:rPr', $fontSource instanceof DOMElement ? $fontSource : $paragraph)->item(0);
        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            if ($xpath->query('.//w:pict|.//w:drawing', $run)->length > 0) continue;
            $paragraph->removeChild($run);
        }
        $run = $paragraph->ownerDocument->createElementNS($namespace, 'w:r');
        if ($runProperties instanceof DOMElement) $run->appendChild($runProperties->cloneNode(true));
        $runProperties = $xpath->query('./w:rPr', $run)->item(0);
        if (!$runProperties instanceof DOMElement) {
            $runProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
            $run->appendChild($runProperties);
        }
        // Match the approved source's visible vertical positions without
        // changing the paragraph flow that keeps QR and the contact line on
        // page one. Negative values lower a run in half-point units.
        $position = $xpath->query('./w:position', $runProperties)->item(0);
        if (!$position instanceof DOMElement) {
            $position = $paragraph->ownerDocument->createElementNS($namespace, 'w:position');
            // LibreOffice ignores run properties that are appended after the
            // font-size elements. Keep OOXML's required run-property order.
            $size = $xpath->query('./w:sz|./w:szCs', $runProperties)->item(0);
            if ($size instanceof DOMNode) {
                $runProperties->insertBefore($position, $size);
            } else {
                $runProperties->appendChild($position);
            }
        }
        // A multi-student name list consumes the lower page area.  Raise the
        // complete signature column visually in that case, while leaving its
        // paragraph flow unchanged so the document remains one page.
        $positionValue = $displayText === 'ขอแสดงความนับถือ' ? '-5' : '-18';
        if (preg_match('/^\(รองศาสตราจารย์/u', $displayText) === 1) {
            // Lower only the parenthesised signatory name, not its QR anchor.
            // Paragraph spacing below moves this name with the full signature
            // block. Do not apply a run offset: LibreOffice ignores it for
            // this mixed VML/text paragraph.
            $positionValue = '0';
        }
        if ($hasMultipleStudents) {
            // Keep the closing below the narrative in a multi-student
            // letter; only the signer lines need the compact uplift.
            // Reserve a signing gap beneath the closing before the
            // parenthesised signatory name and the appointment lines.
            $positionValue = $displayText === 'ขอแสดงความนับถือ' ? '6' : '9';
        }
        $position->setAttributeNS(
            $namespace,
            'w:val',
            $positionValue
        );
        $textNode = $paragraph->ownerDocument->createElementNS($namespace, 'w:t');
        $textNode->nodeValue = $displayText;
        $run->appendChild($textNode);
        $paragraph->appendChild($run);
    }
}

/**
 * Align the direct Word 2–3 student letter to the faculty-approved reference.
 *
 * The current DOCX template reserves additional flow height above the body,
 * while its floating QR group ignores that flow.  Moving text glyphs and VML
 * anchors independently reproduces the reference without adding/removing
 * paragraphs, so the document remains one page.
 */
function officialWordTemplateApplyApprovedMultiStudentReferencePositions(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

    $setRunPosition = static function (DOMXPath $xpath, DOMElement $run, string $value) use ($namespace): void {
        if (!$run->ownerDocument instanceof DOMDocument) return;
        $properties = $xpath->query('./w:rPr', $run)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $run->ownerDocument->createElementNS($namespace, 'w:rPr');
            $run->insertBefore($properties, $run->firstChild);
        }
        $position = $xpath->query('./w:position', $properties)->item(0);
        if (!$position instanceof DOMElement) {
            $position = $run->ownerDocument->createElementNS($namespace, 'w:position');
            $size = $xpath->query('./w:sz|./w:szCs', $properties)->item(0);
            if ($size instanceof DOMNode) {
                $properties->insertBefore($position, $size);
            } else {
                $properties->appendChild($position);
            }
        }
        $position->setAttributeNS($namespace, 'w:val', $value);
    };

    // The reference header (Garuda, outgoing number and address) is 45 pt
    // higher than the current generated document.
    foreach ($xpath->query('//w:body/w:tbl[1]//w:r[.//w:t]') as $run) {
        if ($run instanceof DOMElement) $setRunPosition($xpath, $run, '90');
    }
    foreach ($xpath->query('//v:shape[@id="Picture 3"]') as $shape) {
        if (!$shape instanceof DOMElement) continue;
        $style = (string)$shape->getAttribute('style');
        $shape->setAttribute('style', preg_replace('/margin-top:[^;]*/', 'margin-top:-49.35pt', $style) ?? $style);
    }

    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $trimmed = trim($text);
        if ($trimmed === '') continue;

        if (str_contains($trimmed, 'แผนกงาน')) {
            // The reference contact line sits 26 pt lower than the template.
            foreach ($xpath->query('./w:r[.//w:t]', $paragraph) as $run) {
                if ($run instanceof DOMElement) $setRunPosition($xpath, $run, '-52');
            }
            continue;
        }

        // The closing has a smaller 30 pt uplift; every preceding visible
        // body line and the signature text use the 45 pt reference baseline.
        $position = str_starts_with($trimmed, 'จึงเรียนมาเพื่อ') ? '60' : '90';
        foreach ($xpath->query('./w:r[.//w:t]', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            // QR drawings remain anchored independently. Shift only the
            // surrounding signature text in their shared paragraph.
            if ($xpath->query('.//w:pict|.//w:drawing', $run)->length > 0) continue;
            $setRunPosition($xpath, $run, $position);
        }
    }

    // QR images must sit below the signature column, not beside it. The
    // measured reference offset is 25 pt from the QR anchor paragraph.
    foreach ($xpath->query('//v:group[.//v:imagedata]') as $group) {
        if (!$group instanceof DOMElement) continue;
        $style = (string)$group->getAttribute('style');
        $group->setAttribute('style', preg_replace('/margin-top:[^;]*/', 'margin-top:25pt', $style) ?? $style);
        break;
    }
}

/** Apply the approved Word template's readable leading to the two narratives. */
function officialWordTemplateApplyWordNarrativeLineSpacing(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $trimmed = trim($text);
        if (!str_starts_with($trimmed, 'ตามหนังสือที่อ้างถึง')
            && !str_starts_with($trimmed, 'ทั้งนี้ ขอให้หน่วยงาน')) {
            continue;
        }

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $properties->appendChild($spacing);
        }
        $spacing->setAttributeNS($namespace, 'w:line', '320');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'atLeast');
    }
}

/** Move only the signature text flow down 14 pt, while QR is compensated separately. */
function officialWordTemplateShiftServerPdfSignatureTextDown(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (preg_match('/\(รองศาสตราจารย์[^)]*\)/u', $text) !== 1) continue;
        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) continue;
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) continue;
        $spacing->setAttributeNS($namespace, 'w:before', (string)((int)$spacing->getAttributeNS($namespace, 'before') + 280));
        break;
    }
}

/** Match the baseline spacing in the approved Word PDF. */
function officialWordTemplateApplyServerPdfReferenceLineSpacing(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $paragraphs = [];
    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) $paragraphs[] = $paragraph;
    foreach ($xpath->query('//w:body/w:tbl[1]//w:p[.//w:t[normalize-space()]]') as $paragraph) $paragraphs[] = $paragraph;

    foreach ($paragraphs as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $isNarrative = str_starts_with(trim($text), 'ตามหนังสือที่อ้างถึง')
            || str_starts_with(trim($text), 'ทั้งนี้ ขอให้หน่วยงาน');
        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $properties->appendChild($spacing);
        }
        // Preserve the two narrative paragraphs exactly as in the approved
        // Word template; other PDF paragraphs retain the compact Server rule.
        $spacing->setAttributeNS($namespace, 'w:line', $isNarrative ? '320' : '362');
        $spacing->setAttributeNS($namespace, 'w:lineRule', $isNarrative ? 'atLeast' : 'exact');
    }
}

/** Preserve each intentional blank spacer from the approved Word layout. */
function officialWordTemplateApplyServerPdfReferenceBlankLineHeight(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (trim($text) !== '' || $xpath->query('.//w:pict|.//w:drawing|./w:r/w:tab', $paragraph)->length > 0) continue;

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $properties->appendChild($spacing);
        }
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        $spacing->setAttributeNS($namespace, 'w:after', '0');
        $spacing->setAttributeNS($namespace, 'w:line', '362');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'exact');
    }
}

/** Remove only the blank spacer that LibreOffice retains before the first narrative. */
function officialWordTemplateRemoveServerPdfNarrativeGap(DOMXPath $xpath): void
{
    foreach ($xpath->query('//w:body/w:p[.//w:t[starts-with(normalize-space(), "ตามหนังสือที่อ้างถึง")]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        for ($previous = $paragraph->previousSibling; $previous instanceof DOMElement && $previous->localName === 'p';) {
            $before = $previous->previousSibling;
            $text = '';
            foreach ($xpath->query('.//w:t', $previous) as $textNode) $text .= $textNode->textContent;
            if (trim($text) !== '' || $xpath->query('.//w:pict|.//w:drawing', $previous)->length > 0) break;
            $previous->parentNode?->removeChild($previous);
            $previous = $before;
        }
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $properties->appendChild($spacing);
        }
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        break;
    }
}

/** Restore the 8 pt breathing room before the formal closing sentence. */
function officialWordTemplateAddServerPdfClosingGap(DOMXPath $xpath): void
{
    $paragraph = $xpath->query('//w:body/w:p[.//w:t[starts-with(normalize-space(), "จึงเรียนมาเพื่อ")]]')->item(0);
    if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) return;
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
    if (!$properties instanceof DOMElement) {
        $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
        $paragraph->insertBefore($properties, $paragraph->firstChild);
    }
    $spacing = $xpath->query('./w:spacing', $properties)->item(0);
    if (!$spacing instanceof DOMElement) {
        $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $properties->appendChild($spacing);
    }
    $spacing->setAttributeNS($namespace, 'w:before', '160');
}

/** Resolve a staff-uploaded QR only when it stays inside the project uploads directory. */
function officialWordTemplateConfiguredQrPath(string $storedPath, string $fallback): string
{
    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false || trim($storedPath) === '') return $fallback;
    $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath), DIRECTORY_SEPARATOR));
    $uploadsRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'official_templates');
    if ($candidate !== false && $uploadsRoot !== false && str_starts_with($candidate, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($candidate)) return $candidate;
    return $fallback;
}

/** Keep editable QR captions in the approved four-line layout. */
function officialWordTemplateCaptionLines(string $value, string $fallback): array
{
    $lines = preg_split('/\R/u', trim($value)) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
    if (count($lines) !== 4) {
        $lines = preg_split('/\R/u', $fallback) ?: [];
    }
    return array_slice($lines, 0, 4);
}

/** Replace a VML text-box with a compact, fixed QR-caption layout. */
function officialWordTemplateSetQrCaption(
    DOMXPath $xpath,
    DOMElement $shape,
    array $lines,
    int $halfPoints = 20,
    bool $isLibreOfficePdf = false,
    bool $isServerPdf = false
): void
{
    $content = $xpath->query('.//w:txbxContent', $shape)?->item(0);
    $document = $shape->ownerDocument;
    if (!$content instanceof DOMElement || !$document instanceof DOMDocument) return;
    $textBox = $xpath->query('./v:textbox', $shape)?->item(0);
    if ($textBox instanceof DOMElement) {
        // Do not chain the two captions together; Word otherwise clips the
        // beginning of the first caption when the second VML shape is added.
        // Word grows this box to fit its text, but LibreOffice instead narrows
        // it and wraps the URL. Server PDFs must preserve the explicit source
        // width, while Word downloads retain the source auto-fit behaviour.
        $textBox->setAttribute(
            'style',
            'mso-fit-shape-to-text:' . ($isLibreOfficePdf ? 'f' : 't') . ';inset:0,0,0,0'
        );
        // Word applies a hidden default left inset to VML text boxes unless
        // it is set as a VML attribute as well.  Removing it makes each
        // caption begin exactly at the left edge of its QR image.
        $textBox->setAttribute('inset', '0,0,0,0');
    }
    while ($content->firstChild !== null) $content->removeChild($content->firstChild);

    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $paragraph = $document->createElementNS($namespace, 'w:p');
    $paragraphProperties = $document->createElementNS($namespace, 'w:pPr');
    $spacing = $document->createElementNS($namespace, 'w:spacing');
    $spacing->setAttributeNS($namespace, 'w:before', '0');
    $spacing->setAttributeNS($namespace, 'w:after', '0');
    // The approved reference uses 9.36 pt between QR-caption baselines.
    $spacing->setAttributeNS($namespace, 'w:line', $isServerPdf ? '187' : '200');
    $spacing->setAttributeNS($namespace, 'w:lineRule', $isServerPdf ? 'exact' : 'auto');
    $paragraphProperties->appendChild($spacing);
    $paragraph->appendChild($paragraphProperties);
    foreach ($lines as $index => $line) {
        $run = $document->createElementNS($namespace, 'w:r');
        $properties = $document->createElementNS($namespace, 'w:rPr');
        $fonts = $document->createElementNS($namespace, 'w:rFonts');
        // LibreOffice falls back to a different Latin face when QR-Code uses
        // TH SarabunIT๙. Use its installed SarabunPSK face for every script
        // in Server PDFs, so QR-Code, Thai captions, and URLs have one
        // genuinely consistent 8 pt visual size.
        $captionFont = ($isLibreOfficePdf || $isServerPdf) ? 'TH SarabunPSK' : 'TH SarabunIT๙';
        foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $attribute) {
            $fonts->setAttributeNS($namespace, 'w:' . $attribute, $captionFont);
        }
        $properties->appendChild($fonts);
        // Thai SarabunPSK captions render larger than QR-Code/URLs at the
        // same OOXML size in Server LibreOffice. Compensate only the two Thai
        // explanatory lines so all four QR caption lines stay balanced at
        // their approved size.
        $isUrl = preg_match('#^https?://#i', trim((string)$line)) === 1;
        $lineHalfPoints = ($isLibreOfficePdf || $isServerPdf) && $index > 0 && !$isUrl ? 13 : $halfPoints;
        $size = $document->createElementNS($namespace, 'w:sz');
        $size->setAttributeNS($namespace, 'w:val', (string)$lineHalfPoints);
        $properties->appendChild($size);
        $sizeCs = $document->createElementNS($namespace, 'w:szCs');
        $sizeCs->setAttributeNS($namespace, 'w:val', (string)$lineHalfPoints);
        $properties->appendChild($sizeCs);
        $run->appendChild($properties);
        $text = $document->createElementNS($namespace, 'w:t');
        $text->nodeValue = (string)$line;
        $run->appendChild($text);
        $paragraph->appendChild($run);
        if ($index < count($lines) - 1) {
            $breakRun = $document->createElementNS($namespace, 'w:r');
            $breakRun->appendChild($document->createElementNS($namespace, 'w:br'));
            $paragraph->appendChild($breakRun);
        }
    }
    $content->appendChild($paragraph);
}

/** Reserve enough room for both four-line QR captions above the contact line. */
function officialWordTemplateClearQrFooter(DOMXPath $xpath, array $document = [], bool $hasMultipleStudents = false, bool $isDirectWordDownload = false, bool $isLibreOfficePdf = false, bool $isServerPdf = false): void
{
    $defaults = officialTemplateSettingDefaults();
    $footerContact = trim((string)($document['_template_footer_contact'] ?? $defaults['footer_contact']));
    if ($footerContact !== '' && $footerContact !== $defaults['footer_contact']) {
        officialWordTemplateReplaceParagraph($xpath, 'แผนกงาน', $footerContact);
    }
    $paragraph = $xpath->query('//w:p[.//w:t[contains(., "แผนกงาน") or contains(., "โทรศัพท์")]]')?->item(0);
    if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) return;
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    if ($isDirectWordDownload) {
        // Both faculty-approved Word templates already position this line
        // beneath the QR captions. The multi-student source, however, has
        // two extra blank rows and 6 pt of before-spacing compared with the
        // approved one-student source. Normalize only that inherited gap.
        if ($hasMultipleStudents) {
            $properties = $xpath->query('./w:pPr', $paragraph)?->item(0);
            if ($properties instanceof DOMElement) {
                foreach ($xpath->query('./w:spacing', $properties) as $spacing) {
                    if ($spacing instanceof DOMElement) {
                        $spacing->setAttributeNS($namespace, 'w:before', '0');
                    }
                }
            }
            $blankParagraphs = [];
            for ($node = $paragraph->previousSibling; $node instanceof DOMElement && $node->localName === 'p'; $node = $node->previousSibling) {
                if ($node->namespaceURI !== $namespace) break;
                $hasVisibleText = $xpath->query('.//w:t[normalize-space()]', $node)->length > 0;
                $hasDrawing = $xpath->query('.//w:pict|.//w:drawing', $node)->length > 0;
                if ($hasVisibleText || $hasDrawing) break;
                $blankParagraphs[] = $node;
            }
            // Retain the same two blank rows used by the one-student letter.
            for ($index = 2; $index < count($blankParagraphs); $index++) {
                $blankParagraphs[$index]->parentNode?->removeChild($blankParagraphs[$index]);
            }
        }
        return;
    }
    $properties = $xpath->query('./w:pPr', $paragraph)?->item(0);
    if (!$properties instanceof DOMElement) {
        $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
        $paragraph->insertBefore($properties, $paragraph->firstChild);
    }
    if ($isServerPdf && !$hasMultipleStudents) {
        // The approved template has two empty paragraphs immediately before
        // the contact line. Word collapses them near the floating QR group,
        // but LibreOffice preserves their full height and moves this line to
        // page two. Remove only truly blank paragraphs; QR and signatures
        // contain drawings or text, and are therefore left unchanged.
        $removed = 0;
        for ($node = $paragraph->previousSibling; $node !== null && $removed < 2; ) {
            $previous = $node->previousSibling;
            if ($node instanceof DOMElement && $node->namespaceURI === $namespace && $node->localName === 'p') {
                $hasVisibleText = $xpath->query('.//w:t[normalize-space()]', $node)->length > 0;
                $hasDrawing = $xpath->query('.//w:pict|.//w:drawing', $node)->length > 0;
                if (!$hasVisibleText && !$hasDrawing) {
                    $node->parentNode?->removeChild($node);
                    $removed++;
                    $node = $previous;
                    continue;
                }
            }
            break;
        }
    }
    // The contact line is the final line of every one-page letter. Override
    // (rather than merely remove) inherited LibreOffice keep/page-break
    // controls, otherwise that one line is moved alone to page two.
    foreach ($xpath->query('./w:keepNext|./w:keepLines|./w:pageBreakBefore', $properties) as $pageControl) {
        $properties->removeChild($pageControl);
    }
    // LibreOffice treats the presence of these controls as enabled in this
    // template, even when their value is explicitly 0.  Remove them from the
    // contact paragraph so it may use the remaining space on page one.
    foreach (['keepNext', 'keepLines', 'pageBreakBefore'] as $controlName) {
        foreach ($xpath->query('./w:' . $controlName, $properties) as $control) {
            if ($control instanceof DOMElement) $properties->removeChild($control);
        }
    }
    $spacing = $xpath->query('./w:spacing', $properties)?->item(0);
    if (!$spacing instanceof DOMElement) {
        $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $properties->appendChild($spacing);
    }
    // The server PDF uses a controlled QR group above this line. Do not add
    // Word's flow-based gap here: LibreOffice expands it and moves this final
    // contact line to an otherwise empty second page.
    $beforeSpacing = !$hasMultipleStudents ? '540' : '900';
    if ($isServerPdf) {
        // Keep a clear 23 pt gap below the Server QR URL so the final contact
        // line never touches it. Multi-student letters retain the compact gap
        // required to preserve their one-page constraint.
        // Multi-student letters still have sufficient lower-page room for a
        // small, deliberate separation from the QR URLs.
        // The signature text is shifted independently of the floating QR
        // group.  Leave no flow gap before this final contact paragraph: a
        // 9 pt gap makes LibreOffice push the whole line onto page two even
        // though its visible text still clears the QR captions.
        // This rule applies to every headcount. The QR captions themselves
        // provide the required visual separation; flow spacing here makes a
        // multi-student letter spill its final line onto page two.
        $beforeSpacing = '0';
    } elseif ($isLibreOfficePdf) {
        // The QR group is positioned to the approved signatory baseline.
        // Keep a 30 pt clearance before the contact line without creating a
        // second page.
        $beforeSpacing = '600';
    }
    $spacing->setAttributeNS($namespace, 'w:before', $beforeSpacing);
    $spacing->setAttributeNS($namespace, 'w:after', '0');

    if ($isServerPdf) {
        // Keep the contact line out of the QR-caption area without changing
        // paragraph flow. -62 half-points is the existing 11 pt offset plus the
        // requested additional 20 pt downward shift; QR stays fixed.
        foreach ($xpath->query('./w:r[.//w:t]', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $runProperties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$runProperties instanceof DOMElement) {
                $runProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($runProperties, $run->firstChild);
            }
            $position = $xpath->query('./w:position', $runProperties)->item(0);
            if (!$position instanceof DOMElement) {
                $position = $paragraph->ownerDocument->createElementNS($namespace, 'w:position');
                $runProperties->appendChild($position);
            }
            $position->setAttributeNS($namespace, 'w:val', '-62');
        }
    }
    if (PHP_OS_FAMILY === 'Windows' && !$hasMultipleStudents) {
        // The Windows one-student reference places this contact line below
        // both QR captions. Move its glyphs only, leaving the footer's flow
        // height unchanged so the letter remains on one A4 page.
        foreach ($xpath->query('./w:r[.//w:t]', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $runProperties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$runProperties instanceof DOMElement) {
                $runProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($runProperties, $run->firstChild);
            }
            $position = $xpath->query('./w:position', $runProperties)->item(0);
            if (!$position instanceof DOMElement) {
                $position = $paragraph->ownerDocument->createElementNS($namespace, 'w:position');
                $runProperties->appendChild($position);
            }
            $position->setAttributeNS($namespace, 'w:val', '-90');
        }
    }
}

/** Keep Server PDFs with multiple student names on one page without affecting Word. */
function officialWordTemplateCompactLibreOfficeMultiStudentSendPdf(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (!preg_match('/^\s*[๑-๙]\./u', $text)) continue;
        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) continue;
        foreach ($xpath->query('./w:spacing', $properties) as $spacing) {
            $properties->removeChild($spacing);
        }
        $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        $spacing->setAttributeNS($namespace, 'w:after', '0');
        // 14 pt is enough for the Server's 12 pt conversion font and saves
        // the final contact line from becoming a second PDF page.
        $spacing->setAttributeNS($namespace, 'w:line', '280');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'exact');

        $insertionPoint = null;
        foreach ($properties->childNodes as $propertyChild) {
            if ($propertyChild instanceof DOMElement && in_array($propertyChild->localName, [
                'ind', 'contextualSpacing', 'mirrorIndents', 'suppressOverlap',
                'jc', 'textDirection', 'textAlignment', 'textboxTightWrap',
                'outlineLvl', 'divId', 'cnfStyle', 'rPr', 'sectPr',
            ], true)) {
                $insertionPoint = $propertyChild;
                break;
            }
        }
        $properties->insertBefore($spacing, $insertionPoint);
    }
}

/** Normalize Arabic numerals remaining in visible template text to Thai numerals. */
function officialWordTemplateConvertAllTextDigitsToThai(DOMXPath $xpath): void
{
    foreach ($xpath->query('//w:t') as $textNode) {
        $textNode->nodeValue = officialThaiDigits($textNode->textContent);
    }
}

/** Force the effective font size in Linux-only output, in half-point units. */
function officialWordTemplateApplyLibreOfficeFontSize(DOMXPath $xpath, int $halfPoints): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:r[.//w:t]') as $run) {
        if (!$run instanceof DOMElement || !$run->ownerDocument instanceof DOMDocument) continue;

        $properties = $xpath->query('./w:rPr', $run)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $run->ownerDocument->createElementNS($namespace, 'w:rPr');
            $run->insertBefore($properties, $run->firstChild);
        }
        foreach ($xpath->query('./w:sz|./w:szCs', $properties) as $size) {
            $properties->removeChild($size);
        }
        foreach (['sz', 'szCs'] as $elementName) {
            $size = $run->ownerDocument->createElementNS($namespace, 'w:'.$elementName);
            $size->setAttributeNS($namespace, 'w:val', (string)$halfPoints);
            $properties->appendChild($size);
        }
    }
}

/** Increase only the readable send-letter content, never its fixed layout blocks. */
function officialWordTemplateApplyLibreOfficeSendContentSize(DOMXPath $xpath, int $halfPoints): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $dateParagraph = $xpath->query('//w:body/w:p[.//w:t[normalize-space()]]')->item(0);

    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $text = trim($text);
        $isLetterContent = ($dateParagraph instanceof DOMElement && $paragraph->isSameNode($dateParagraph))
            || str_starts_with($text, 'เรื่อง')
            || str_starts_with($text, 'เรียน')
            || str_starts_with($text, 'อ้างถึง')
            || str_starts_with($text, 'ตามหนังสือที่อ้างถึง')
            || str_starts_with($text, 'ทั้งนี้ ขอให้หน่วยงาน')
            || str_starts_with($text, 'จึงเรียนมาเพื่อ');
        if (!$isLetterContent) continue;

        foreach ($xpath->query('./w:r[.//w:t]', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $properties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$properties instanceof DOMElement) {
                $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($properties, $run->firstChild);
            }
            foreach ($xpath->query('./w:sz|./w:szCs', $properties) as $size) $properties->removeChild($size);
            foreach (['sz', 'szCs'] as $elementName) {
                $size = $paragraph->ownerDocument->createElementNS($namespace, 'w:'.$elementName);
                $size->setAttributeNS($namespace, 'w:val', (string)$halfPoints);
                $properties->appendChild($size);
            }
        }
    }
}

/**
 * LibreOffice preserves blank paragraphs from the Word header table and gives
 * them real height.  Word collapses those placeholders, which is why the
 * approved Windows PDF fits the QR and signature block on one page.  Remove
 * only blank paragraphs from the first header table, then use a safe 15 pt
 * exact line height for server output.  This keeps the body readable while
 * reserving room for at least five student names.
 */
function officialWordTemplateApplyLibreOfficeCompactLayout(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    // Keep all header-table paragraphs from the approved template.  The empty
    // paragraphs are intentional vertical spacers, but LibreOffice expands
    // them beyond the Word source.  Preserve them at the source's 12 pt
    // single-line height so the number/address stay correctly aligned without
    // consuming the space needed by the lower letter content.
    $headerTable = $xpath->query('//w:body/w:tbl[1]')->item(0);
    if ($headerTable instanceof DOMElement) {
        foreach ($xpath->query('.//w:p', $headerTable) as $paragraph) {
            if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
            $hasLayoutContent = trim($text) !== ''
                || $xpath->query('.//w:pict|.//w:drawing|./w:r/w:tab', $paragraph)->length > 0;
            if ($hasLayoutContent) continue;

            $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
            if (!$properties instanceof DOMElement) {
                $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
                $paragraph->insertBefore($properties, $paragraph->firstChild);
            }
            foreach ($xpath->query('./w:spacing', $properties) as $spacing) $properties->removeChild($spacing);
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $spacing->setAttributeNS($namespace, 'w:before', '0');
            $spacing->setAttributeNS($namespace, 'w:after', '0');
            $spacing->setAttributeNS($namespace, 'w:line', '240');
            $spacing->setAttributeNS($namespace, 'w:lineRule', 'exact');
            $properties->appendChild($spacing);
        }
    }

    // Match the vertical placement of the Garuda in the approved Word PDF.
    // It is an anchored VML image in the first header table, so changing its
    // top margin moves only the emblem and not the neighbouring address.
    foreach ($xpath->query('//*[local-name() = "shape" and @id = "Picture 3"]') as $shape) {
        if (!$shape instanceof DOMElement) continue;
        $style = (string)$shape->getAttribute('style');
        $shape->setAttribute('style', preg_replace('/margin-top:[^;]*/', 'margin-top:-9.35pt', $style) ?? $style);
    }

    // Do not compact every paragraph here.  This routine was added with the
    // QR block and changed the inherited official Word paragraph metrics
    // (including the narrative line height).  Keep those original metrics;
    // only the QR-specific cleanup below is allowed to make room on the page.

    // The Word source places the date directly after the header table with no
    // added before-spacing.  Keeping it at zero avoids pushing the whole body
    // (and eventually the contact line) onto a second page.
    $dateParagraph = $xpath->query('//w:body/w:p[.//w:t[normalize-space()]]')->item(0);
    if ($dateParagraph instanceof DOMElement && $dateParagraph->ownerDocument instanceof DOMDocument) {
        $properties = $xpath->query('./w:pPr', $dateParagraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $dateParagraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $dateParagraph->insertBefore($properties, $dateParagraph->firstChild);
        }
        $spacing = $xpath->query('./w:spacing', $properties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $dateParagraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $properties->appendChild($spacing);
        }
        $spacing->setAttributeNS($namespace, 'w:before', '0');
    }

    // LibreOffice treats the three Word placeholder paragraphs above this
    // group as full-height lines. Remove only those immediate placeholders;
    // a controlled 25 pt gap is set below so QR/signature positions stay
    // orderly without pushing the contact line onto page two.
    $qrParagraph = $xpath->query('//w:body/w:p[.//w:pict or .//w:drawing]')->item(0);
    if ($qrParagraph instanceof DOMElement) {
        for ($node = $qrParagraph->previousSibling; $node instanceof DOMElement && $node->localName === 'p';) {
            $previous = $node->previousSibling;
            $text = '';
            foreach ($xpath->query('.//w:t', $node) as $textNode) $text .= $textNode->textContent;
            $hasLayoutContent = trim($text) !== ''
                || $xpath->query('.//w:pict|.//w:drawing|./w:r/w:tab', $node)->length > 0;
            if ($hasLayoutContent) break;
            $node->parentNode?->removeChild($node);
            $node = $previous;
        }
        foreach ($xpath->query('.//w:r[.//w:t]', $qrParagraph) as $run) {
            if (!$run instanceof DOMElement || !$run->ownerDocument instanceof DOMDocument) continue;
            $properties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$properties instanceof DOMElement) {
                $properties = $run->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($properties, $run->firstChild);
            }
            foreach ($xpath->query('./w:sz|./w:szCs', $properties) as $size) $properties->removeChild($size);
            foreach (['sz', 'szCs'] as $elementName) {
                $size = $run->ownerDocument->createElementNS($namespace, 'w:'.$elementName);
                $size->setAttributeNS($namespace, 'w:val', '20');
                $properties->appendChild($size);
            }
        }
        // Use one bounded 25 pt gap in place of LibreOffice's three expanded
        // blank paragraphs. This preserves a real signing space while keeping
        // the larger 11 pt letter and its contact line on one page.
        $paragraphProperties = $xpath->query('./w:pPr', $qrParagraph)->item(0);
        if (!$paragraphProperties instanceof DOMElement && $qrParagraph->ownerDocument instanceof DOMDocument) {
            $paragraphProperties = $qrParagraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $qrParagraph->insertBefore($paragraphProperties, $qrParagraph->firstChild);
        }
        if ($paragraphProperties instanceof DOMElement) {
            $spacing = $xpath->query('./w:spacing', $paragraphProperties)->item(0);
            if (!$spacing instanceof DOMElement && $qrParagraph->ownerDocument instanceof DOMDocument) {
                $spacing = $qrParagraph->ownerDocument->createElementNS($namespace, 'w:spacing');
                $paragraphProperties->appendChild($spacing);
            }
            if ($spacing instanceof DOMElement) {
                $spacing->setAttributeNS($namespace, 'w:before', '500');
                $spacing->setAttributeNS($namespace, 'w:after', '0');
            }
        }
    }

    // LibreOffice does not interpret Word's Thai-distributed alignment like
    // Microsoft Word.  Preserve every other paragraph metric from the
    // approved template, but make the two narrative paragraphs justify to
    // both margins so Thai text has the same orderly block form.
    foreach ($xpath->query('//w:body/w:p[.//w:t[normalize-space()]]') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (!str_starts_with(trim($text), 'ตามหนังสือที่อ้างถึง')
            && !str_starts_with(trim($text), 'ทั้งนี้ ขอให้หน่วยงาน')
            && !str_starts_with(trim($text), 'ด้วยคณะวิศวกรรมศาสตร์')
            && !str_starts_with(trim($text), 'คณะเห็นว่า')) {
            continue;
        }
        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument?->createElementNS($namespace, 'w:pPr');
            if ($properties instanceof DOMElement) $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        // LibreOffice interprets the template's Thai-distributed alignment
        // differently from Word and creates uneven gaps. Official narrative
        // paragraphs must be justified to both margins.
        if ($properties instanceof DOMElement) {
            $alignment = $xpath->query('./w:jc', $properties)->item(0);
            if (!$alignment instanceof DOMElement) {
                $alignment = $paragraph->ownerDocument?->createElementNS($namespace, 'w:jc');
                if ($alignment instanceof DOMElement) $properties->appendChild($alignment);
            }
            if ($alignment instanceof DOMElement) {
                $alignment->setAttributeNS($namespace, 'w:val', 'both');
            }
        }
    }

    // The blank immediately before the closing expands in LibreOffice; remove
    // only that placeholder and restore the measured closing gap separately.
    foreach ($xpath->query('//w:body/w:p[.//w:t[starts-with(normalize-space(), "จึงเรียนมาเพื่อ")]]') as $closingParagraph) {
        if (!$closingParagraph instanceof DOMElement) continue;
        $previous = $closingParagraph->previousSibling;
        if (!$previous instanceof DOMElement || $previous->localName !== 'p') continue;
        $previousText = '';
        foreach ($xpath->query('.//w:t', $previous) as $textNode) $previousText .= $textNode->textContent;
        if (trim($previousText) === '' && $xpath->query('.//w:pict|.//w:drawing|./w:r/w:tab', $previous)->length === 0) {
            $previous->parentNode?->removeChild($previous);
        }
        break;
    }
}

/**
 * Creates one DOCX file for either download or PDF conversion.
 * The PDF path deliberately starts from this same file so Word and PDF share
 * the exact same pagination, Thai Distributed layout, and tab stops.
 */
function saveOfficialInternshipWordFile(array $document, array $students, string $outputType, string $outputPath, bool $forPdf = false, string $fontOption = 'psk'): void
{
    if (writeOfficialInternshipTemplateWord($document, $students, $outputType, $outputPath, $forPdf, $fontOption)) return;

    $word = createOfficialInternshipWord($document, $students, $outputType, $fontOption);
    \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($outputPath);
    $zip = new ZipArchive();
    if ($zip->open($outputPath) === true) {
        officialWordRegisterThaiFontFallback($zip);
        $zip->close();
    }
}

function officialWordTemplateReplaceParagraph(DOMXPath $xpath, string $needle, string $replacement): void
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        $textNodes = $xpath->query('.//w:t', $paragraph);
        $text = '';
        foreach ($textNodes as $node) $text .= $node->textContent;
        if (!str_contains($text, $needle) || $textNodes->length === 0) continue;

        // หนังสือต้นฉบับเยื้องย่อหน้าด้วยช่องว่างต้นบรรทัดไว้แล้ว
        // จึงเก็บช่องว่างนั้นไว้เมื่อแทนที่ข้อความ เพื่อให้ตำแหน่งตรงกับแม่แบบราชการ
        preg_match('/^[\s\x{00A0}]*/u', $text, $leadingWhitespace);
        $textNodes->item(0)->nodeValue = ($leadingWhitespace[0] ?? '').$replacement;
        for ($index = 1; $index < $textNodes->length; $index++) $textNodes->item($index)->nodeValue = '';
        return;
    }
}

/** Adjust only the send letter's follow-up narrative while retaining its template paragraph flow. */
function officialWordTemplateSetFollowUpNarrativeCharacterSpacing(DOMXPath $xpath, string $spacingValue): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
        if (!str_starts_with(trim($text), 'ทั้งนี้ ขอให้หน่วยงาน')) continue;

        $runProperties = $xpath->query('./w:pPr/w:rPr', $paragraph)->item(0);
        if (!$runProperties instanceof DOMElement) {
            $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
            if (!$properties instanceof DOMElement) {
                $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
                $paragraph->insertBefore($properties, $paragraph->firstChild);
            }
            $runProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
            $properties->appendChild($runProperties);
        }
        $spacing = $xpath->query('./w:spacing', $runProperties)->item(0);
        if (!$spacing instanceof DOMElement) {
            $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $runProperties->appendChild($spacing);
        }
        $spacing->setAttributeNS($namespace, 'w:val', $spacingValue);

        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $properties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$properties instanceof DOMElement) {
                $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($properties, $run->firstChild);
            }
            $runSpacing = $xpath->query('./w:spacing', $properties)->item(0);
            if (!$runSpacing instanceof DOMElement) {
                $runSpacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
                $properties->appendChild($runSpacing);
            }
            $runSpacing->setAttributeNS($namespace, 'w:val', $spacingValue);
        }
        return;
    }
}

/** Apply a small natural-flow adjustment to the “ตามหนังสือที่อ้างถึง” paragraph only. */
function officialWordTemplateSetReferenceNarrativeCharacterSpacing(DOMXPath $xpath, string $spacingValue): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
        if (!str_starts_with(trim($text), 'ตามหนังสือที่อ้างถึง')) continue;

        $paragraphRunProperties = $xpath->query('./w:pPr/w:rPr', $paragraph)->item(0);
        if (!$paragraphRunProperties instanceof DOMElement) {
            $paragraphProperties = $xpath->query('./w:pPr', $paragraph)->item(0);
            if (!$paragraphProperties instanceof DOMElement) {
                $paragraphProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
                $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
            }
            $paragraphRunProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
            $paragraphProperties->appendChild($paragraphRunProperties);
        }
        $paragraphSpacing = $xpath->query('./w:spacing', $paragraphRunProperties)->item(0);
        if (!$paragraphSpacing instanceof DOMElement) {
            $paragraphSpacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
            $paragraphRunProperties->appendChild($paragraphSpacing);
        }
        $paragraphSpacing->setAttributeNS($namespace, 'w:val', $spacingValue);

        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $runProperties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$runProperties instanceof DOMElement) {
                $runProperties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $run->insertBefore($runProperties, $run->firstChild);
            }
            $runSpacing = $xpath->query('./w:spacing', $runProperties)->item(0);
            if (!$runSpacing instanceof DOMElement) {
                $runSpacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
                $runProperties->appendChild($runSpacing);
            }
            $runSpacing->setAttributeNS($namespace, 'w:val', $spacingValue);
        }
        return;
    }
}

/** Remove inherited manual breaks from a paragraph that has just been refilled. */
function officialWordTemplateRemoveParagraphLineBreaks(DOMXPath $xpath, string $needle): void
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
        if (!str_contains($text, $needle)) continue;
        foreach ($xpath->query('./w:r/w:br', $paragraph) as $break) {
            $break->parentNode?->removeChild($break);
        }
        return;
    }
}

/** Remove one superfluous template blank directly before a visible paragraph. */
function officialWordTemplateRemoveOneBlankParagraphBefore(DOMXPath $xpath, string $needle): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (!str_contains($text, $needle)) continue;

        $previous = $paragraph->previousSibling;
        if (!$previous instanceof DOMElement || $previous->namespaceURI !== $namespace || $previous->localName !== 'p') return;
        $hasText = $xpath->query('.//w:t[normalize-space()]', $previous)->length > 0;
        $hasDrawing = $xpath->query('.//w:drawing|.//w:pict', $previous)->length > 0;
        if (!$hasText && !$hasDrawing) $previous->parentNode?->removeChild($previous);
        return;
    }
}

/** Apply one consistent official paragraph gap to the request-letter narrative. */
function officialWordTemplateNormalizeRequestNarrativeSpacing(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach (['คณะเห็นว่า', 'ทั้งนี้ โปรด', 'จึงเรียนมาเพื่อโปรดพิจารณา'] as $needle) {
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
            if (!str_contains($text, $needle)) continue;
            $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
            if (!$properties instanceof DOMElement) continue;
            $spacing = $xpath->query('./w:spacing', $properties)->item(0);
            if (!$spacing instanceof DOMElement) {
                $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
                $properties->appendChild($spacing);
            }
            $spacing->setAttributeNS($namespace, 'w:before', '120');
            $spacing->setAttributeNS($namespace, 'w:after', '0');
            $spacing->setAttributeNS($namespace, 'w:beforeAutospacing', '0');
            $spacing->setAttributeNS($namespace, 'w:afterAutospacing', '0');
            break;
        }
    }
}

/** Align the opening position of the standalone reply and closing paragraphs. */
function officialWordTemplateAlignRequestReplyAndClosingParagraphs(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $paragraphs = [];
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        $trimmed = trim($text);
        if (str_starts_with($trimmed, 'ทั้งนี้ โปรด') || str_starts_with($trimmed, 'จึงเรียนมาเพื่อโปรดพิจารณา')) {
            $paragraphs[] = $paragraph;
        }
    }
    // A one-student request keeps the reply within the preceding paragraph;
    // it already follows its source layout and must not be changed here.
    if (count($paragraphs) !== 2) return;

    foreach ($paragraphs as $paragraph) {
        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        $indentation = $xpath->query('./w:ind', $properties)->item(0);
        if (!$indentation instanceof DOMElement) {
            $indentation = $paragraph->ownerDocument->createElementNS($namespace, 'w:ind');
            $properties->appendChild($indentation);
        }
        // 1,418 twips is the source template's approved first tab stop.
        $indentation->setAttributeNS($namespace, 'w:firstLine', '1418');

        // Replace the template's leading spaces/tab with the explicit
        // indentation above. Only the initial layout runs are removed.
        $seenVisibleText = false;
        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $runText = '';
            foreach ($xpath->query('.//w:t', $run) as $textNode) $runText .= $textNode->textContent;
            if (!$seenVisibleText && trim($runText) === '') {
                $run->parentNode?->removeChild($run);
                continue;
            }
            if (!$seenVisibleText) {
                foreach ($xpath->query('.//w:t', $run) as $textNode) {
                    $textNode->nodeValue = preg_replace('/^[\s\x{00A0}]+/u', '', $textNode->textContent) ?? $textNode->textContent;
                }
                $seenVisibleText = true;
            }
        }
        foreach ($xpath->query('./w:r[.//w:tab]', $paragraph) as $tabRun) {
            $tabRun->parentNode?->removeChild($tabRun);
        }
    }
}

/** Keep a 3-5-student request letter's visible contact block on page one. */
function officialWordTemplateTightenMultiStudentRequestFooter(DOMXPath $xpath, int $studentCount): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    // The approved three-student layout leaves two blank lines before the
    // contact details. Four or five students need the full available height
    // for their added name rows.
    $keepBlankLines = $studentCount === 3 ? 2 : 0;

    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (!str_contains($text, 'แผนกงานวิชาการและวิจัย')) continue;

        $blankParagraphs = [];
        for ($node = $paragraph->previousSibling; $node instanceof DOMElement && $node->localName === 'p'; $node = $node->previousSibling) {
            if ($node->namespaceURI !== $namespace) break;
            $blankText = '';
            foreach ($xpath->query('.//w:t', $node) as $textNode) $blankText .= $textNode->textContent;
            // These are blank spacing rows in the source and may contain a
            // bare tab run.  A tab with no text has no visible content, so it
            // must not prevent the row from being removed.
            $hasVisibleLayout = trim($blankText) !== ''
                || $xpath->query('.//w:drawing|.//w:pict|./w:pPr/w:sectPr', $node)->length > 0;
            if ($hasVisibleLayout) break;
            $blankParagraphs[] = $node;
        }
        // The array runs from nearest to farthest. Keep the first ones so the
        // visible gap is retained, then delete only the excess blank lines.
        for ($index = $keepBlankLines; $index < count($blankParagraphs); $index++) {
            $blankParagraphs[$index]->parentNode?->removeChild($blankParagraphs[$index]);
        }
        return;
    }
}

/** Apply bold styling to one phrase without changing its paragraph layout. */
function officialWordTemplateBoldTextOccurrence(DOMXPath $xpath, string $paragraphNeedle, string $boldText): void
{
    if ($boldText === '') return;
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $paragraphText = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $paragraphText .= $textNode->textContent;
        if (!str_contains($paragraphText, $paragraphNeedle)) continue;

        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if (!$run instanceof DOMElement) continue;
            $runText = '';
            foreach ($xpath->query('.//w:t', $run) as $textNode) $runText .= $textNode->textContent;
            $offset = mb_strpos($runText, $boldText, 0, 'UTF-8');
            if ($offset === false) continue;

            $before = mb_substr($runText, 0, $offset, 'UTF-8');
            $after = mb_substr($runText, $offset + mb_strlen($boldText, 'UTF-8'), null, 'UTF-8');
            $textNodes = $xpath->query('.//w:t', $run);
            if ($textNodes->length === 0) return;
            $textNodes->item(0)->nodeValue = $before;
            for ($index = 1; $index < $textNodes->length; $index++) $textNodes->item($index)->nodeValue = '';

            $boldRun = $run->cloneNode(true);
            if (!$boldRun instanceof DOMElement) return;
            $boldTextNodes = $xpath->query('.//w:t', $boldRun);
            if ($boldTextNodes->length === 0) return;
            $boldTextNodes->item(0)->nodeValue = $boldText;
            for ($index = 1; $index < $boldTextNodes->length; $index++) $boldTextNodes->item($index)->nodeValue = '';
            $properties = $xpath->query('./w:rPr', $boldRun)->item(0);
            if (!$properties instanceof DOMElement) {
                $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:rPr');
                $boldRun->insertBefore($properties, $boldRun->firstChild);
            }
            foreach ($xpath->query('./w:b|./w:bCs', $properties) as $boldProperty) {
                $properties->removeChild($boldProperty);
            }
            $properties->appendChild($paragraph->ownerDocument->createElementNS($namespace, 'w:b'));
            $properties->appendChild($paragraph->ownerDocument->createElementNS($namespace, 'w:bCs'));
            $run->parentNode?->insertBefore($boldRun, $run->nextSibling);

            if ($after !== '') {
                $tailRun = $run->cloneNode(true);
                if ($tailRun instanceof DOMElement) {
                    $tailTextNodes = $xpath->query('.//w:t', $tailRun);
                    if ($tailTextNodes->length > 0) {
                        $tailTextNodes->item(0)->nodeValue = $after;
                        for ($index = 1; $index < $tailTextNodes->length; $index++) $tailTextNodes->item($index)->nodeValue = '';
                        $run->parentNode?->insertBefore($tailRun, $boldRun->nextSibling);
                    }
                }
            }
            return;
        }
    }
}

/** Remove one obsolete template paragraph while preserving every other layout element. */
function officialWordTemplateRemoveParagraph(DOMXPath $xpath, string $needle): void
{
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (!str_contains($text, $needle)) continue;
        $paragraph->parentNode?->removeChild($paragraph);
        return;
    }
}

/**
 * Replaces a paragraph while turning newline-separated student names into
 * Word soft line breaks.  This keeps the names within one official paragraph
 * instead of creating extra paragraphs with inherited before/after spacing.
 */
function officialWordTemplateReplaceParagraphWithLineBreaks(DOMXPath $xpath, string $needle, string $replacement): ?DOMElement
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $textNodes = $xpath->query('.//w:t', $paragraph);
        $text = '';
        foreach ($textNodes as $node) $text .= $node->textContent;
        if (!str_contains($text, $needle) || $textNodes->length === 0) continue;

        preg_match('/^[\s\x{00A0}]*/u', $text, $leadingWhitespace);
        $parts = preg_split('/\R/u', ($leadingWhitespace[0] ?? '').$replacement) ?: [];
        $firstText = $textNodes->item(0);
        $run = $firstText?->parentNode;
        if (!$firstText instanceof DOMElement || !$run instanceof DOMElement) return null;

        $firstText->nodeValue = array_shift($parts) ?? '';
        for ($index = 1; $index < $textNodes->length; $index++) $textNodes->item($index)->nodeValue = '';

        $insertBefore = $run->nextSibling;
        $document = $paragraph->ownerDocument;
        if (!$document instanceof DOMDocument) return $paragraph;
        foreach ($parts as $part) {
            $breakRun = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
            $breakRun->appendChild($document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br'));
            $textRun = $run->cloneNode(true);
            if (!$textRun instanceof DOMElement) continue;
            foreach (iterator_to_array($textRun->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 't') $textRun->removeChild($child);
            }
            $textNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
            if (preg_match('/^\s|\s$/u', $part)) $textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $textNode->nodeValue = $part;
            $textRun->appendChild($textNode);
            $paragraph->insertBefore($breakRun, $insertBefore);
            $paragraph->insertBefore($textRun, $insertBefore);
        }
        return $paragraph;
    }
    return null;
}

/** Remove only empty paragraphs at the end of a document, never its section properties. */
function officialWordTemplateRemoveTrailingBlankParagraphs(DOMXPath $xpath): void
{
    $body = $xpath->query('//w:body')->item(0);
    if (!$body instanceof DOMElement) return;

    for ($node = $body->lastChild; $node !== null;) {
        $previous = $node->previousSibling;
        if (!$node instanceof DOMElement || $node->localName === 'sectPr') {
            $node = $previous;
            continue;
        }
        if ($node->localName !== 'p') break;

        $text = '';
        foreach ($xpath->query('.//w:t', $node) as $textNode) $text .= $textNode->textContent;
        $containsVisibleContent = trim($text) !== ''
            || $xpath->query('.//w:drawing|.//w:pict|.//w:br|./w:r/w:tab', $node)->length > 0;
        if ($containsVisibleContent) break;

        $body->removeChild($node);
        $node = $previous;
    }
}

/** Replaces only one text run, leaving nearby tabs, drawings, and text boxes intact. */
function officialWordTemplateReplaceTextNode(DOMXPath $xpath, string $needle, string $replacement): void
{
    foreach ($xpath->query('//w:t') as $node) {
        $text = $node->textContent;
        if (!str_contains($text, $needle)) continue;
        $node->nodeValue = str_replace($needle, $replacement, $text);
        return;
    }
}

/** Replace the first visible Thai-date paragraph while retaining its source pPr. */
function officialWordTemplateReplaceFirstThaiDateParagraph(DOMXPath $xpath, string $replacement): void
{
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
        if (preg_match('/(?:มกราคม|กุมภาพันธ์|มีนาคม|เมษายน|พฤษภาคม|มิถุนายน|กรกฎาคม|สิงหาคม|กันยายน|ตุลาคม|พฤศจิกายน|ธันวาคม)/u', $text) !== 1) continue;
        // The approved request template positions its date with a real Word
        // tab.  Keep that tab and write only into the first text run after it;
        // replacing the whole paragraph moves the date back to the left.
        $seenTab = false;
        $dateTextNode = null;
        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            if ($xpath->query('./w:tab', $run)->length > 0) {
                $seenTab = true;
                continue;
            }
            if (!$seenTab) continue;
            $textNode = $xpath->query('./w:t', $run)->item(0);
            if ($textNode instanceof DOMElement) {
                $dateTextNode = $textNode;
                break;
            }
        }
        if ($dateTextNode instanceof DOMElement) {
            $dateTextNode->nodeValue = $replacement;
            $clearFollowing = false;
            foreach ($xpath->query('./w:r/w:t', $paragraph) as $textNode) {
                if ($clearFollowing) $textNode->nodeValue = '';
                if ($textNode === $dateTextNode) $clearFollowing = true;
            }
        } else {
            // Other approved letter types centre their date using leading
            // source whitespace, so retain that geometry as the fallback.
        preg_match('/^[\s\x{00A0}]*/u', $text, $leadingWhitespace);
        officialWordTemplateSetParagraphText($xpath, $paragraph, ($leadingWhitespace[0] ?? '') . $replacement);
        }
        // The editable source template marks its blank date placeholder in
        // red.  Once a real date is inserted it is ordinary letter content,
        // so remove that placeholder colour instead of inheriting it.
        foreach ($xpath->query('./w:r/w:rPr/w:color', $paragraph) as $color) {
            $color->parentNode?->removeChild($color);
        }
        return;
    }
}

/**
 * Fills the numbered student-list paragraphs from the original send letter.
 * Cloned list paragraphs retain Word's own numbering and Thai paragraph style.
 */
function officialWordTemplateReplaceNumberedStudentList(DOMXPath $xpath, string $firstNeedle, string $secondNeedle, array $students): void
{
    $slots = [];
    foreach ($xpath->query('//w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $text = '';
        foreach ($xpath->query('./w:r/w:t', $paragraph) as $node) $text .= $node->textContent;
        if (str_contains($text, $firstNeedle) || str_contains($text, $secondNeedle)) $slots[] = $paragraph;
    }

    // The approved multi-student template provides real numbered-name slots
    // rather than the former sample names. Detect those slots by their
    // visible marker so the source layout remains authoritative.
    if (count($slots) < 2) {
        $slots = [];
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            if (!$paragraph instanceof DOMElement) continue;
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
            if (preg_match('/^\s*(?:[1-9]|[๑-๙])\.\s*/u', $text) === 1) $slots[] = $paragraph;
        }
    }
    if (count($slots) < 2) return;

    $names = [];
    foreach ($students as $student) {
        $names[] = officialThaiDigits(trim((string)($student['prefix_short'] ?? '') . (string)($student['firstname'] ?? '') . ' ' . (string)($student['lastname'] ?? '')));
    }
    if (!$names) return;

    // นักศึกษาคนเดียวต้องอยู่ต่อในย่อหน้าเนื้อหา ไม่สร้างบรรทัดรายการหรือเลขลำดับ
    if (count($names) === 1) {
        foreach ($slots as $slot) $slot->parentNode?->removeChild($slot);
        return;
    }

    // Preserve the approved 18 pt list leading through three names.  Four
    // and five names use 16 pt minimum leading so the complete letter still
    // fits on one A4 page without reducing its type size.
    $listLineHeight = count($names) > 3 ? 320 : 360;
    $insertBefore = $slots[count($slots) - 1]->nextSibling;
    for ($index = 0; $index < count($names); $index++) {
        $slot = $slots[$index] ?? null;
        if (!$slot instanceof DOMElement) {
            $slot = $slots[count($slots) - 1]->cloneNode(true);
            if (!$slot instanceof DOMElement) continue;
            $slots[0]->parentNode?->insertBefore($slot, $insertBefore);
        }
        if (count($names) <= 3) {
            // The approved multi-student DOCX already has the correct
            // marker indentation and line rhythm. Replace only the visible
            // text and preserve every paragraph property unchanged.
            officialWordTemplateSetParagraphText($xpath, $slot, officialThaiDigits((string)($index + 1)) . '. ' . $names[$index]);
            foreach ($xpath->query('./w:r/w:tab', $slot) as $tab) {
                $tab->parentNode?->removeChild($tab);
            }
        } else {
            officialWordTemplateSetExplicitThaiStudentListItem($xpath, $slot, $index + 1, $names[$index]);
            officialWordTemplateCompactStudentListParagraph($xpath, $slot, count($names), $listLineHeight);
        }
    }
    for ($index = count($names); $index < count($slots); $index++) {
        $slots[$index]->parentNode?->removeChild($slots[$index]);
    }
}

/** Render list markers as Thai text so Word and LibreOffice never substitute Arabic digits. */
function officialWordTemplateSetExplicitThaiStudentListItem(DOMXPath $xpath, DOMElement $paragraph, int $sequence, string $name): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    officialWordTemplateSetParagraphText($xpath, $paragraph, officialThaiDigits((string)$sequence) . '. ' . $name);
    // Keep multi-student rows entirely regular, matching the approved
    // multi-student reference. Only the name embedded in a one-student
    // narrative is emphasized.
    $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
    if (!$properties instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) return;

    // The original template's automatic list is thaiNumbers, but LibreOffice
    // renders that OOXML format as Arabic. Use an explicit Thai marker while
    // keeping the template's hanging-indent geometry.
    foreach ($xpath->query('./w:numPr|./w:ind', $properties) as $property) {
        $properties->removeChild($property);
    }
    $indent = $paragraph->ownerDocument->createElementNS($namespace, 'w:ind');
    $indent->setAttributeNS($namespace, 'w:left', '1800');
    $indent->setAttributeNS($namespace, 'w:hanging', '360');
    $properties->appendChild($indent);
}

/**
 * The original numbered-list style comes with Word's inherited paragraph
 * spacing.  That is unnoticeable for one or two names, but can push a valid
 * five-student send letter onto a second page.  Keep the approved template's
 * font, numbering, indents and alignment; only make the generated list use
 * compact, single-line paragraph spacing.
 */
function officialWordTemplateCompactStudentListParagraph(DOMXPath $xpath, DOMElement $paragraph, int $studentCount, int $lineHeight = 360): void
{
    if ($studentCount < 2) return;

    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
    if (!$properties instanceof DOMElement) {
        $properties = $paragraph->ownerDocument?->createElementNS($namespace, 'w:pPr');
        if (!$properties instanceof DOMElement) return;
        $paragraph->insertBefore($properties, $paragraph->firstChild);
    }

    foreach ($xpath->query('./w:spacing', $properties) as $spacing) {
        $properties->removeChild($spacing);
    }

    $spacing = $paragraph->ownerDocument?->createElementNS($namespace, 'w:spacing');
    if (!$spacing instanceof DOMElement) return;
    $spacing->setAttributeNS($namespace, 'w:before', '0');
    $spacing->setAttributeNS($namespace, 'w:after', '0');
    $spacing->setAttributeNS($namespace, 'w:line', (string)$lineHeight);
    // The student names use the official 16 pt font. The former 9 pt
    // automatic leading (180 twips) made two or more lines overlap in Word.
    // “At least” preserves the 18 pt baseline but lets Word expand a row if
    // a Thai font's glyph metrics are taller, preventing any overlap.
    $spacing->setAttributeNS($namespace, 'w:lineRule', 'atLeast');
    $properties->appendChild($spacing);

    if ($xpath->query('./w:contextualSpacing', $properties)->length === 0) {
        $contextualSpacing = $paragraph->ownerDocument?->createElementNS($namespace, 'w:contextualSpacing');
        if ($contextualSpacing instanceof DOMElement) $properties->appendChild($contextualSpacing);
    }
}

/** Keep a 2-5-student request letter on one A4 page without changing 16 pt text. */
function officialWordTemplateCompactMultiStudentRequestLayout(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $bodyParagraphs = [];
    $signatureBlockStart = null;
    foreach ($xpath->query('//w:body/w:p') as $index => $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $bodyParagraphs[] = $paragraph;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (str_contains(trim($text), 'ขอแสดงความนับถือ')) {
            // The preceding blank paragraph and all following paragraphs are
            // deliberate signature spacing in the approved template.
            $signatureBlockStart = max(0, count($bodyParagraphs) - 2);
            break;
        }
    }
    // The empty paragraphs before the first visible body line are deliberate:
    // they reserve vertical space for the Garuda in the header. Preserve them
    // so the document date never rises into the emblem.
    $seenVisibleBodyLine = false;
    foreach ($bodyParagraphs as $index => $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        // Never compact the sign-off, signature, or contact block. Its blank
        // lines are part of the faculty-approved layout, not spare space.
        if ($signatureBlockStart !== null && $index >= $signatureBlockStart) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;

        // Empty layout-only paragraphs are the usual reason a footer spills
        // alone onto page 2 after student names are added. Never remove a
        // paragraph that owns a drawing, tab, or section property.
        if (trim($text) === '') {
            // Tab-stop definitions are paragraph metadata, not visible content.
            // Preserve only a real inline tab, drawing, picture or section.
            $hasContent = $xpath->query('.//w:drawing|.//w:pict|./w:r/w:tab|./w:pPr/w:sectPr', $paragraph)->length > 0;
            if ($seenVisibleBodyLine && !$hasContent) $paragraph->parentNode?->removeChild($paragraph);
            continue;
        }

        $seenVisibleBodyLine = true;

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        foreach ($xpath->query('./w:spacing', $properties) as $spacing) $properties->removeChild($spacing);
        $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        $spacing->setAttributeNS($namespace, 'w:after', '0');
        // 18 pt line height preserves a clear 16 pt Thai font while freeing
        // enough vertical room for five names and the contact footer.
        $spacing->setAttributeNS($namespace, 'w:line', '360');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'atLeast');
        $properties->appendChild($spacing);
    }
}

/**
 * Keep the approved send letter on one A4 page when it contains more than
 * one student. The supplied template has blank placeholder paragraphs above
 * the final contact line; Word counts them as full lines and moves that line
 * alone to page two. Remove only those empty layout placeholders, never the
 * QR, signature, heading or contact paragraph itself.
 */
function officialWordTemplateCompactMultiStudentSendLayout(DOMXPath $xpath, bool $applyWordFooterSafeguard = true, bool $preserveTemplateSignatureSpacing = false): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    // Apply the safe leading after cloning the template list paragraphs. Word
    // may preserve the template's 180-twip automatic spacing despite the
    // list helper above, which is too small for 16 pt Thai glyphs and causes
    // names to overlap. Numbered paragraphs are only the generated student
    // rows in this official send-letter template.
    foreach ($xpath->query('//w:body/w:p[w:pPr/w:numPr]') as $listParagraph) {
        if (!$listParagraph instanceof DOMElement || !$listParagraph->ownerDocument instanceof DOMDocument) continue;
        $properties = $xpath->query('./w:pPr', $listParagraph)->item(0);
        if (!$properties instanceof DOMElement) continue;
        foreach ($xpath->query('./w:spacing', $properties) as $spacing) {
            $properties->removeChild($spacing);
        }
        $spacing = $listParagraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        $spacing->setAttributeNS($namespace, 'w:after', '0');
        $spacing->setAttributeNS($namespace, 'w:line', $preserveTemplateSignatureSpacing ? '360' : '320');
        // Keep the 16 pt baseline and allow Word to expand a Thai name row if
        // needed. This frees only the excess leading in multi-student files;
        // exact line height would clip Thai glyphs in the DOCX.
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'atLeast');
        $properties->appendChild($spacing);
    }

    if ($preserveTemplateSignatureSpacing) {
        // The editable reference has one empty paragraph between the second
        // narrative and its closing. In generated two- and three-student
        // letters this becomes an extra full line of empty space, moving all
        // lower content down without contributing to the approved layout.
        officialWordTemplateRemoveOneBlankParagraphBefore($xpath, 'จึงเรียนมาเพื่อโปรดทราบ');
        return;
    }

    // The direct Word download should retain the faculty template's deliberate
    // breathing room between the closing sentence and the signature.  PDF
    // conversion has a separate fixed layout safeguard, so only compact that
    // path's structural blanks.
    foreach ($preserveTemplateSignatureSpacing
        ? ['แผนกงานวิชาการและวิจัย']
        : ['แผนกงานวิชาการและวิจัย', 'จึงเรียนมาเพื่อโปรดทราบ'] as $needle) {
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            if (!$paragraph instanceof DOMElement) continue;
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
            if (!str_contains(trim($text), $needle)) continue;

            for ($node = $paragraph->previousSibling; $node instanceof DOMElement && $node->localName === 'p';) {
                $previous = $node->previousSibling;
                $blankText = '';
                foreach ($xpath->query('.//w:t', $node) as $textNode) $blankText .= $textNode->textContent;
                // A tab-stop definition in pPr does not render a visible
                // line. Only keep a paragraph when it has an actual inline
                // tab, drawing, picture, or section property.
                $hasLayoutContent = trim($blankText) !== ''
                    || $xpath->query('.//w:drawing|.//w:pict|./w:r/w:tab|./w:pPr/w:sectPr', $node)->length > 0;
                if ($hasLayoutContent) break;
                $node->parentNode?->removeChild($node);
                $node = $previous;
            }
            break;
        }
    }

    if (!$applyWordFooterSafeguard) return;

    // The QR is a floating drawing, so Word does not count its full height
    // when it lays out the following contact paragraph. For a multi-student
    // letter, replace variable blank paragraphs with one controlled contact
    // gap: it prevents overlap and preserves the one-page requirement.
    $removeBlankParagraphsBefore = static function (DOMXPath $xpath, DOMElement $paragraph): void {
        for ($node = $paragraph->previousSibling; $node instanceof DOMElement && $node->localName === 'p';) {
            $previous = $node->previousSibling;
            $text = '';
            foreach ($xpath->query('.//w:t', $node) as $textNode) $text .= $textNode->textContent;
            $hasVisibleLayout = trim($text) !== ''
                || $xpath->query('.//w:drawing|.//w:pict|./w:r/w:tab|./w:pPr/w:sectPr', $node)->length > 0;
            if ($hasVisibleLayout) break;
            $node->parentNode?->removeChild($node);
            $node = $previous;
        }
    };

    // Remove only layout blanks directly before the main body, signature and
    // QR group. No visible paragraph, drawing, tab, or section is removed.
    if (!$preserveTemplateSignatureSpacing) {
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            if (!$paragraph instanceof DOMElement) continue;
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
            if (str_contains($text, 'ตามหนังสือที่อ้างถึง') || str_contains($text, 'ขอแสดงความนับถือ')) {
                $removeBlankParagraphsBefore($xpath, $paragraph);
            }
        }
        $qrParagraph = $xpath->query('//w:body/w:p[.//w:drawing or .//w:pict]')->item(0);
        if ($qrParagraph instanceof DOMElement) $removeBlankParagraphsBefore($xpath, $qrParagraph);
    }

    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= $textNode->textContent;
        if (!str_contains($text, 'แผนกงานวิชาการและวิจัย')) continue;

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        foreach ($xpath->query('./w:spacing', $properties) as $spacing) $properties->removeChild($spacing);
        $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        // 34 pt is the measured clearance for the 66.7 pt floating QR after
        // the two signature-title lines, while taking less room than the
        // three empty template paragraphs it replaces.
        $spacing->setAttributeNS($namespace, 'w:before', '680');
        $spacing->setAttributeNS($namespace, 'w:after', '0');
        // Keep the contact line readable; its vertical position is controlled
        // by the explicit before-spacing above.
        $spacing->setAttributeNS($namespace, 'w:line', '320');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'atLeast');
        $properties->appendChild($spacing);
        break;
    }
}

/** Keep multi-student send letters within one A4 page without shrinking type. */
function officialWordTemplateCompactMultiStudentSendBody(DOMXPath $xpath): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($xpath->query('//w:body/w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement || !$paragraph->ownerDocument instanceof DOMDocument) continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $textNode) $text .= trim($textNode->textContent);
        $isBodyParagraph = str_starts_with($text, 'ตามหนังสือที่อ้างถึง')
            || str_starts_with($text, 'ทั้งนี้')
            || str_starts_with($text, 'จึงเรียนมาเพื่อ');
        $isStudentRow = $xpath->query('./w:pPr/w:numPr', $paragraph)->length > 0;
        if (!$isBodyParagraph && !$isStudentRow) continue;

        $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
        if (!$properties instanceof DOMElement) {
            $properties = $paragraph->ownerDocument->createElementNS($namespace, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }
        foreach ($xpath->query('./w:spacing', $properties) as $spacing) $properties->removeChild($spacing);
        $spacing = $paragraph->ownerDocument->createElementNS($namespace, 'w:spacing');
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        $spacing->setAttributeNS($namespace, 'w:after', '0');
        $spacing->setAttributeNS($namespace, 'w:line', '320');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'atLeast');
        $properties->appendChild($spacing);
    }
}

/**
 * Fill the numbered-name slots between the request narrative and response
 * instruction. The approved request template already carries the correct
 * list rhythm; reuse it rather than inserting a second set of paragraphs.
 */
function officialWordTemplateReplaceRequestStudentList(DOMXPath $xpath, DOMElement $afterParagraph, array $students): void
{
    if (!$students) return;

    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $document = $afterParagraph->ownerDocument;
    if (!$document instanceof DOMDocument || !$afterParagraph->parentNode instanceof DOMNode) return;

    $slots = [];
    $responseParagraph = null;
    for ($node = $afterParagraph->nextSibling; $node !== null; $node = $node->nextSibling) {
        if (!$node instanceof DOMElement || $node->namespaceURI !== $namespace || $node->localName !== 'p') continue;
        $text = '';
        foreach ($xpath->query('.//w:t', $node) as $textNode) $text .= $textNode->textContent;
        if (str_contains($text, 'ทั้งนี้ โปรด')) {
            $responseParagraph = $node;
            break;
        }
        if (preg_match('/^\s*(?:[1-9]|[๑-๙])\.\s*/u', $text) === 1) $slots[] = $node;
    }
    if (!$slots) return;

    $insertBefore = $responseParagraph;
    foreach ($students as $index => $student) {
        $slot = $slots[$index] ?? null;
        if (!$slot instanceof DOMElement) {
            $slot = $slots[count($slots) - 1]->cloneNode(true);
            if (!$slot instanceof DOMElement) continue;
            $afterParagraph->parentNode->insertBefore($slot, $insertBefore);
        }
        // The template separates prefix, given name and surname with Word
        // tab runs.  Keep those runs: a single replacement string collapses
        // the columns and puts the student name in the wrong position.
        $textNodes = $xpath->query('./w:r/w:t', $slot);
        if ($textNodes->length >= 3) {
            $prefix = trim((string)($student['prefix_short'] ?? ''));
            $givenName = trim((string)($student['firstname'] ?? ''));
            $surname = trim((string)($student['lastname'] ?? ''));
            $textNodes->item(0)->nodeValue = officialThaiDigits((string)($index + 1)) . '. ' . officialThaiDigits($prefix);
            $textNodes->item(1)->nodeValue = officialThaiDigits($givenName) . ' ';
            $textNodes->item(2)->nodeValue = officialThaiDigits($surname);
            for ($textIndex = 3; $textIndex < $textNodes->length; $textIndex++) $textNodes->item($textIndex)->nodeValue = '';
            officialWordTemplatePinRequestStudentSurnameColumn($xpath, $slot, $textNodes->item(1), $textNodes->item(2));
        } else {
            $name = trim((string)($student['prefix_short'] ?? '') . (string)($student['firstname'] ?? '') . ' ' . (string)($student['lastname'] ?? ''));
            officialWordTemplateSetParagraphText($xpath, $slot, officialThaiDigits((string)($index + 1)) . '. ' . officialThaiDigits($name));
        }
    }
    for ($index = count($students); $index < count($slots); $index++) {
        $slots[$index]->parentNode?->removeChild($slots[$index]);
    }
}

/**
 * Give every surname the same fixed Word tab column.
 *
 * The original sample uses two default tabs. A shorter given name reaches an
 * earlier default tab than a longer one, so its surname visibly shifts left.
 * Clear those intervening defaults and retain one explicit 3,600-twip tab.
 */
function officialWordTemplatePinRequestStudentSurnameColumn(DOMXPath $xpath, DOMElement $paragraph, ?DOMNode $givenText, ?DOMNode $surnameText): void
{
    if (!$paragraph->ownerDocument instanceof DOMDocument || !$givenText instanceof DOMNode || !$surnameText instanceof DOMNode) return;
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
    if (!$properties instanceof DOMElement) return;
    $tabs = $xpath->query('./w:tabs', $properties)->item(0);
    if (!$tabs instanceof DOMElement) {
        $tabs = $paragraph->ownerDocument->createElementNS($namespace, 'w:tabs');
        $properties->insertBefore($tabs, $properties->firstChild);
    }
    foreach ([['2160', 'clear'], ['2880', 'clear'], ['3600', 'left']] as [$position, $type]) {
        if ($xpath->query('./w:tab[@w:pos="' . $position . '"]', $tabs)->length > 0) continue;
        $tab = $paragraph->ownerDocument->createElementNS($namespace, 'w:tab');
        $tab->setAttributeNS($namespace, 'w:val', $type);
        $tab->setAttributeNS($namespace, 'w:pos', $position);
        $tabs->appendChild($tab);
    }

    $givenRun = $givenText->parentNode;
    $surnameRun = $surnameText->parentNode;
    $afterGivenName = false;
    $keptSurnameTab = false;
    foreach ($xpath->query('./w:r', $paragraph) as $run) {
        if ($run === $givenRun) {
            $afterGivenName = true;
            continue;
        }
        if ($run === $surnameRun) break;
        if (!$afterGivenName || $xpath->query('./w:tab', $run)->length === 0) continue;
        if (!$keptSurnameTab) {
            $keptSurnameTab = true;
            continue;
        }
        $run->parentNode?->removeChild($run);
    }
}

/** Writes text into the first direct run of a simple paragraph and clears its old runs. */
function officialWordTemplateSetParagraphText(DOMXPath $xpath, DOMElement $paragraph, string $text): void
{
    $textNodes = $xpath->query('./w:r/w:t', $paragraph);
    if ($textNodes->length === 0) return;
    $textNodes->item(0)->nodeValue = $text;
    for ($index = 1; $index < $textNodes->length; $index++) $textNodes->item($index)->nodeValue = '';
}

/** Replace the final matching text run in a template paragraph without removing its tab stops. */
function officialWordTemplateReplaceLastTextValue(DOMXPath $xpath, string $paragraphNeedle, string $value, string $replacement): void
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        $textNodes = $xpath->query('.//w:t', $paragraph);
        $paragraphText = '';
        foreach ($textNodes as $node) $paragraphText .= $node->textContent;
        if (!str_contains($paragraphText, $paragraphNeedle)) continue;

        $target = null;
        foreach ($textNodes as $node) {
            if (trim($node->textContent) === $value) $target = $node;
        }
        if ($target !== null) $target->nodeValue = $replacement;
        return;
    }
}

/** Replaces a split date while preserving the tab stop and paragraph indent from the source letter. */
function officialWordTemplateReplaceDatePreservingTab(DOMXPath $xpath, string $dateNeedle, string $replacement): void
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        $textNodes = $xpath->query('.//w:t', $paragraph);
        $targetIndex = null;
        foreach ($textNodes as $index => $node) {
            if ($node->textContent === $dateNeedle) { $targetIndex = $index; break; }
        }
        if ($targetIndex === null) continue;

        $textNodes->item($targetIndex)->nodeValue = $replacement;
        for ($index = $targetIndex + 1; $index < $textNodes->length; $index++) {
            $textNodes->item($index)->nodeValue = '';
        }
        return;
    }
}

/** Apply bold formatting to a suffix while retaining the paragraph's original font and position. */
function officialWordTemplateBoldText(DOMXPath $xpath, string $paragraphNeedle, string $boldText): void
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        $textNodes = $xpath->query('.//w:t', $paragraph);
        $paragraphText = '';
        foreach ($textNodes as $node) $paragraphText .= $node->textContent;
        if (!str_contains($paragraphText, $paragraphNeedle) || !str_ends_with($paragraphText, $boldText) || $textNodes->length === 0) continue;

        $firstText = $textNodes->item(0);
        $run = $firstText->parentNode;
        if (!$run instanceof DOMElement) return;
        $leadingText = substr($paragraphText, 0, strlen($paragraphText) - strlen($boldText));
        $firstText->nodeValue = $leadingText;

        $boldRun = $run->cloneNode(true);
        $boldTextNodes = $xpath->query('.//w:t', $boldRun);
        if ($boldTextNodes->length === 0) return;
        $boldTextNodes->item(0)->nodeValue = $boldText;

        $runProperties = $xpath->query('./w:rPr', $boldRun)->item(0);
        if (!$runProperties instanceof DOMElement) {
            $runProperties = $boldRun->ownerDocument->createElementNS($boldRun->namespaceURI, 'w:rPr');
            $boldRun->insertBefore($runProperties, $boldRun->firstChild);
        }
        foreach ($xpath->query('./w:b|./w:bCs', $runProperties) as $boldProperty) {
            $runProperties->removeChild($boldProperty);
        }
        $runProperties->appendChild($boldRun->ownerDocument->createElementNS($boldRun->namespaceURI, 'w:b'));
        $runProperties->appendChild($boldRun->ownerDocument->createElementNS($boldRun->namespaceURI, 'w:bCs'));
        $run->parentNode?->insertBefore($boldRun, $run->nextSibling);
        return;
    }
}

/** Creates editable Word versions of the official internship letters. */
function createOfficialInternshipWord(array $document, array $students, string $outputType, string $fontOption = 'psk'): PhpWord
{
    if (!$students) {
        throw new InvalidArgumentException('ไม่พบรายชื่อนักศึกษา');
    }

    $fontName = officialWordThaiFontName($fontOption);
    $word = new PhpWord();
    $word->setDefaultFontName($fontName);
    $word->setDefaultFontSize(16);
    $twips = static fn(float $cm): int => (int)round(Converter::cmToTwip($cm));
    $responseForm = $outputType === 'response';
    $section = $word->addSection([
        'pageSizeW' => $twips(21),
        'pageSizeH' => $twips(29.7),
        'marginTop' => $twips($responseForm ? 1.5 : 0.25),
        'marginBottom' => $twips($responseForm ? 1.5 : 0.41),
        'marginLeft' => $twips($responseForm ? 2 : 3),
        'marginRight' => $twips(2),
    ]);
    $normal = ['name' => $fontName, 'size' => 16, 'color' => '000000'];
    $bold = $normal + ['bold' => true];
    $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0];
    $left = ['alignment' => Jc::LEFT, 'spaceAfter' => 0, 'lineHeight' => 1.0];
    $justify = ['alignment' => Jc::BOTH, 'firstLine' => $twips(1.27), 'spaceAfter' => 0, 'lineHeight' => 1.0];
    if ($outputType === 'response') {
        officialWordAddResponseForm($section, $document, $students, $normal, $bold, $left, $center, $justify);
        return $word;
    }

    $date = officialThaiDate((string)($document['document_date'] ?? date('Y-m-d')));
    $department = officialThaiDigits((string)($students[0]['department_name'] ?? 'สาขาวิชา'));
    $company = officialThaiDigits((string)($document['company_name'] ?? ''));
    $count = count($students);
    $countThai = officialThaiDigits((string)$count);
    $names = officialStudentNames($students, true);

    $number = $section->addTextRun($left);
    $number->addText('ที่  ', $normal);
    $number->addText(officialFormatDocumentNumber((string)($document['document_number'] ?? '')), $normal);
    $section->addText($date, $normal, $center);
    $section->addTextBreak(1);

    $isRequest = $outputType === 'request';
    $recipient = officialThaiDigits((string)($document['recipient_name'] ?? ''));
    officialWordAddLabelLine($section, 'เรื่อง', $isRequest ? 'ขอความอนุเคราะห์รับนักศึกษาฝึกประสบการณ์วิชาชีพ' : 'ขอส่งตัวนักศึกษาฝึกประสบการณ์วิชาชีพ', $normal, $left);
    officialWordAddLabelLine($section, 'เรียน', $recipient, $normal, $left);
    if (!$isRequest) {
        officialWordAddLabelLine($section, 'อ้างถึง', officialThaiDigits((string)($document['reference_text'] ?? 'แบบตอบรับนักศึกษา')), $normal, $left);
        officialWordAddLabelLine($section, 'สิ่งที่ส่งมาด้วย', 'ขั้นตอนการประเมินนักศึกษาในระบบฝึกประสบการณ์วิชาชีพ จำนวน ๑ ฉบับ', $normal, $left);
    } else {
        officialWordAddLabelLine($section, 'สิ่งที่ส่งมาด้วย', '๑. ใบสมัคร จำนวน '.$countThai.' ชุด', $normal, $left);
        officialWordAddLabelLine($section, '', '๒. ใบแสดงผลการเรียน จำนวน '.$countThai.' ชุด', $normal, $left);
        officialWordAddLabelLine($section, '', '๓. แบบตอบรับนักศึกษา จำนวน '.$countThai.' แผ่น', $normal, $left);
    }
    $section->addTextBreak(1);

    $semester = officialThaiDigits((string)($students[0]['semester'] ?? ''));
    $academic = officialThaiDigits((string)($students[0]['academic_year'] ?? ''));
    $start = officialThaiDate((string)($students[0]['start_date'] ?? ''));
    $end = officialThaiDate((string)($students[0]['end_date'] ?? ''));
    if ($isRequest) {
        $body = "ด้วยคณะวิศวกรรมศาสตร์และเทคโนโลยี ได้กำหนดให้นักศึกษาชั้นปีที่ ๓ มีการออกฝึกประสบการณ์วิชาชีพ $department ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end เพื่อให้นักศึกษามีประสบการณ์ในการปฏิบัติงานและเตรียมตัวออกไปปฏิบัติงานหลังจากสำเร็จการศึกษา คณะเห็นว่า $company เป็นหน่วยงานที่มีการดำเนินงานเกี่ยวข้องกับสาขาวิชา$department ดังนั้น เพื่อเป็นการเสริมสร้างความรู้และประสบการณ์วิชาชีพให้แก่นักศึกษา จึงขอความอนุเคราะห์รับนักศึกษาเข้าฝึกประสบการณ์วิชาชีพ จำนวน $countThai คน ดังนี้";
        $section->addText($body, $normal, $justify);
    } else {
        $body = "ตามหนังสือที่อ้างถึง $company ได้ตอบรับนักศึกษา คณะวิศวกรรมศาสตร์และเทคโนโลยี $department เข้าฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end จำนวน $countThai คน นั้น คณะขอส่งนักศึกษามารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ ดังรายชื่อต่อไปนี้";
        $section->addText($body, $normal, $justify);
    }
    foreach ($students as $index => $student) {
        $section->addText(officialThaiDigits(($index + 1).'.  '.trim(($student['prefix_short'] ?? '').($student['firstname'] ?? '').' '.($student['lastname'] ?? ''))), $normal, ['indentation' => ['left' => $twips(1.27)], 'spaceAfter' => 0]);
    }
    if ($isRequest) {
        $due = !empty($document['response_due_date']) ? ' ภายในวันที่ '.officialThaiDate((string)$document['response_due_date']) : '';
        $section->addText('ทั้งนี้ โปรดส่งแบบตอบรับนักศึกษาตามสิ่งที่ส่งมาด้วย'.$due, $normal, $justify);
        $closing = 'จึงเรียนมาเพื่อโปรดพิจารณา หวังเป็นอย่างยิ่งว่าคงได้รับความอนุเคราะห์จากหน่วยงานด้วยดี และขอขอบคุณมา ณ โอกาสนี้';
    } else {
        $section->addText('ทั้งนี้ ขอให้หน่วยงานของท่านประเมินผลการปฏิบัติงานนักศึกษา ตาม QR - Code ด้านล่าง หลังจากที่นักศึกษาปฏิบัติงานเสร็จสิ้นตามระยะเวลาที่กำหนด และขอความอนุเคราะห์หน่วยงานของท่านออกหนังสือรับรองการปฏิบัติงานให้แก่นักศึกษา เพื่อใช้เป็นหลักฐานแสดงการฝึกประสบการณ์วิชาชีพต่อไป', $normal, $justify);
        $closing = 'จึงเรียนมาเพื่อโปรดทราบ และขอขอบคุณในความอนุเคราะห์ด้วยดีมา ณ โอกาสนี้';
    }
    $section->addTextBreak(1);
    $section->addText($closing, $normal, $justify);
    $section->addTextBreak(2);
    $section->addText('ขอแสดงความนับถือ', $normal, $center);
    $section->addTextBreak(3);
    $section->addText('('.officialThaiDigits((string)($document['signatory_name'] ?? '')).')', $normal, $center);
    $section->addText(officialThaiDigits((string)($document['signatory_title_line1'] ?? '')), $normal, $center);
    if (!empty($document['signatory_title_line2'])) {
        $section->addText(officialThaiDigits((string)$document['signatory_title_line2']), $normal, $center);
    }
    return $word;
}

function officialWordAddLabelLine($section, string $label, string $value, array $font, array $paragraph): void
{
    $table = $section->addTable([
        'borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0, 'cellMarginTop' => 0,
        'cellMarginBottom' => 0, 'cellMarginLeft' => 0, 'cellMarginRight' => 0,
    ]);
    $row = $table->addRow();
    $row->addCell(1800)->addText($label, $font, $paragraph);
    $row->addCell(7300)->addText($value, $font, $paragraph);
}

function officialWordAddResponseForm($section, array $document, array $students, array $normal, array $bold, array $left, array $center, array $justify): void
{
    $twips = static fn(float $cm): int => (int)round(Converter::cmToTwip($cm));
    $compact = ['alignment' => Jc::LEFT, 'spaceAfter' => 0, 'lineHeight' => 1.0];
    $indent = $compact + ['indentation' => ['left' => $twips(0.7)]];
    $semester = officialThaiDigits((string)($students[0]['semester'] ?? ''));
    $academic = officialThaiDigits((string)($students[0]['academic_year'] ?? ''));
    $start = officialThaiDate((string)($students[0]['start_date'] ?? ''));
    $end = officialThaiDate((string)($students[0]['end_date'] ?? ''));
    $company = officialThaiDigits((string)($document['company_name'] ?? ''));

    // This follows the faculty's blank acceptance-form template.  Values
    // originate from the exact request-letter record, not from the current
    // student profile, so a later profile change cannot alter an issued form.
    $section->addText('แบบตอบรับนักศึกษา', array_replace($bold, ['size' => 18]), $center);
    $section->addText('เรียน\tรองคณบดีฝ่ายวิชาการและวิจัย', $normal, $left);
    $section->addText('คำชี้แจง', $bold, $left);
    $section->addText('1. นักศึกษาออกฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ '.$semester.' ปีการศึกษา '.$academic, $normal, $left);
    $section->addText('    ระหว่างวันที่ '.$start.' ถึงวันที่ '.$end, $bold, $left);
    $section->addText('2. โปรดกรอกข้อมูลและส่งกลับตามที่อยู่ด้านท้ายของแบบตอบรับ', $normal, $left);
    $section->addTextBreak(1);
    $section->addText('โปรดระบุความประสงค์ของสถานประกอบการ / หน่วยงาน', $bold, $left);
    $section->addText('(   ) ไม่สามารถรับนักศึกษาได้\t\t(   ) สามารถรับนักศึกษาได้ ดังรายชื่อต่อไปนี้', $normal, $indent);
    $section->addTextBreak(1);
    foreach ($students as $index => $student) {
        $name = trim((string)($student['prefix_short'] ?? '').(string)($student['firstname'] ?? '').' '.(string)($student['lastname'] ?? ''));
        $department = trim((string)($student['department_name'] ?? ''));
        $department = str_starts_with($department, 'สาขาวิชา') ? $department : 'สาขาวิชา'.$department;
        $section->addText(officialThaiDigits(($index + 1).'. '.$name.'  '.$department), $normal, $compact);
    }
    $section->addTextBreak(1);
    $section->addText('1. รายละเอียดเกี่ยวกับสถานประกอบการ / หน่วยงาน', $bold, $left);
    $section->addText('ชื่อสถานประกอบการ / หน่วยงาน  '.$company, $normal, $indent);
    $section->addText('ชื่อ (ภาษาอังกฤษ) ..................................................................................................................', $normal, $indent);
    $section->addText('ที่อยู่ เลขที่ ................................................... ถนน ...................................................', $normal, $indent);
    $section->addText('ตำบล/แขวง .................................... อำเภอ/เขต .................................... จังหวัด .................................... รหัสไปรษณีย์ ..................', $normal, $indent);
    $section->addText('โทรศัพท์ .................................................. URL (ถ้ามี) .................................................. E-mail (ถ้ามี) ..................................................', $normal, $indent);
    $section->addText('ลักษณะการดำเนินงาน ..................................................................................................................', $normal, $indent);
    $section->addText('เวลาปฏิบัติงานของสถานประกอบการ (ระบุวันและช่วงเวลา) ....................................................................................', $normal, $indent);
    $section->addText('จำนวนพนักงานรวม ................................ คน', $normal, $indent);
    $section->addText('ชื่อผู้จัดการสถานประกอบการ / หัวหน้าหน่วยงาน ...................................................................................................', $normal, $indent);
    $section->addTextBreak(1);
    $section->addText('หากมหาวิทยาลัยประสงค์จะติดต่อประสานงานกับสถานประกอบการ / หน่วยงาน ขอให้', $normal, $left);
    $section->addText('(   ) ติดต่อโดยตรงกับผู้จัดการ / หัวหน้าหน่วยงาน', $normal, $indent);
    $section->addText('(   ) ติดต่อกับบุคคลที่สถานประกอบการ / หน่วยงานมอบหมาย ดังนี้', $normal, $indent);
    $section->addText('ชื่อ .................................................................................. โทรศัพท์ ..................................................................', $normal, $indent);
    $section->addPageBreak();
    $section->addText('2. รายละเอียดเกี่ยวกับงานที่เสนอให้นักศึกษาและคุณสมบัตินักศึกษา', $bold, $left);
    $section->addText('ตำแหน่งงานที่นักศึกษาปฏิบัติ (Job Position) ...............................................................................................................', $normal, $left);
    $section->addText('รายละเอียดงานที่นักศึกษาปฏิบัติ (Job Description)', $normal, $left);
    foreach (range(1, 6) as $_) $section->addText('....................................................................................................................................................................................', $normal, $left);
    $section->addTextBreak(1);
    $section->addText('3. สวัสดิการ', $bold, $left);
    $section->addText('สวัสดิการที่มีให้นักศึกษาระหว่างปฏิบัติงาน', $normal, $left);
    $section->addText('    ค่าตอบแทน  (   ) ไม่มี      (   ) มี .................... บาท / วัน หรือ ..................... บาท / เดือน', $normal, $left);
    $section->addText('    ที่พัก          (   ) ไม่มี       (   ) มี และไม่เสียค่าใช้จ่าย', $normal, $left);
    $section->addText('                    (   ) นักศึกษาเสียค่าใช้จ่ายเอง .............. บาท / วัน หรือ ................. บาท / เดือน', $normal, $left);
    $section->addText('    รถรับส่งไปกลับระหว่างสถานประกอบการ ที่พัก และชุมชนใกล้เคียง', $normal, $left);
    $section->addText('                    (   ) ไม่มี       (   ) มี และไม่เสียค่าใช้จ่าย', $normal, $left);
    $section->addText('                    (   ) นักศึกษาเสียค่าใช้จ่ายเอง ................. บาท / วัน หรือ ................. บาท / เดือน', $normal, $left);
    $section->addText('สวัสดิการอื่นๆ (ถ้ามีระบุ เช่น อาหาร ชุดทำงาน) ...........................................................................................................', $normal, $left);
    $section->addTextBreak(3);
    $section->addText('ลงชื่อ ............................................................... ผู้ให้ข้อมูลสถานประกอบการ', $normal, ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
    $section->addText('(.......................................................)', $normal, ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
    $section->addText('ตำแหน่ง .......................................................', $normal, ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
    $section->addText('วันที่ ......... เดือน ................. พ.ศ. ...........', $normal, ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);
    $section->addTextBreak(2);
    $section->addText('แผนกงานวิชาการและวิจัย (งานสหกิจศึกษาและการฝึกงานในสถานประกอบการ)', $normal, $center);
    $section->addText('คณะวิศวกรรมศาสตร์และเทคโนโลยี มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน', $normal, $center);
    $section->addText('ที่อยู่ 744 ถ.สุรนารายณ์ ต.ในเมือง อ.เมืองนครราชสีมา จ.นครราชสีมา 30000', $normal, $center);
    $section->addText('เบอร์โทรศัพท์ 0-4423-3000 ต่อ 3194  อีเมล FET@rmuti.ac.th', $normal, $center);
}
