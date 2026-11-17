<?php
declare(strict_types=1);

require_once __DIR__ . '/official_internship_pdf_helper.php';

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Creates the acceptance form by importing the faculty-approved PDF itself.
 * The original two-page artwork, table lines, seal, footer and spacing stay
 * untouched; only request-specific values are placed in the existing blanks.
 */
function officialResponseFormTemplatePath(PDO $pdo): string
{
    $statement = $pdo->prepare('SELECT t.pdf_template
        FROM document_template t
        JOIN document_type dt ON dt.document_type_id=t.document_type_id
        WHERE dt.document_code=? AND t.status="active" AND t.pdf_template IS NOT NULL AND t.pdf_template<>""
        ORDER BY t.template_id DESC LIMIT 1');
    $statement->execute(['INTERNSHIP_RESPONSE_FORM']);
    $storedPath = trim((string)$statement->fetchColumn());
    if ($storedPath === '') {
        throw new RuntimeException('ไม่พบแม่แบบแบบตอบรับที่เปิดใช้งานในฐานข้อมูล');
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath), DIRECTORY_SEPARATOR);
    $template = $projectRoot === false ? false : realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);
    if ($template === false || !is_file($template) || !str_starts_with($template, $projectRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('ไม่พบไฟล์แม่แบบแบบตอบรับที่ระบุไว้ในฐานข้อมูล');
    }
    return $template;
}

function createOfficialResponseFormPdf(PDO $pdo, array $document, array $students): Fpdi
{
    if (!$students) {
        throw new InvalidArgumentException('ไม่พบรายชื่อนักศึกษา');
    }

    $template = officialResponseFormTemplatePath($pdo);

    $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    $pageCount = $pdf->setSourceFile($template);
    for ($page = 1; $page <= $pageCount; $page++) {
        $imported = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($imported);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($imported, 0, 0, $size['width'], $size['height']);
        if ($page === 1) {
            officialResponseFormWritePageOne($pdf, $document, $students);
        }
    }
    return $pdf;
}

function officialResponseFormWritePageOne(Fpdi $pdf, array $document, array $students): void
{
    $font = thSarabunPskFont();
    $bold = thSarabunPskFont('B');
    $write = static function (float $x, float $y, float $width, string $text, float $size = 16, string $style = '', string $align = 'L') use ($pdf, $font, $bold): void {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont(str_contains($style, 'B') ? $bold : $font, str_contains($style, 'U') ? 'U' : '', $size);
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 5.2, $text, 0, 0, $align, false, '', 1, false, 'T', 'M');
    };
    $erase = static function (float $x, float $y, float $width, float $height = 5.8) use ($pdf): void {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $width, $height, 'F');
    };
    $dottedUnderline = static function (float $x, float $y, string $text, float $size = 16) use ($pdf, $bold): void {
        $pdf->SetFont($bold, '', $size);
        $width = $pdf->GetStringWidth($text);
        // The approved form uses a dotted rule below the filled value, not
        // the font's solid underline decoration.
        $pdf->SetLineStyle([
            'width' => 0.30,
            // The original acceptance form uses a bold dashed rule beneath
            // the values, not a dotted or solid underline.
            'dash' => '1.20,1.35',
            'color' => [0, 0, 0],
        ]);
        $pdf->Line($x, $y + 5.05, $x + $width, $y + 5.05);
    };


    $semester = (string)($students[0]['semester'] ?? '');
    $academicYear = (string)($students[0]['academic_year'] ?? '');
    $startDate = officialResponseFormArabicDate((string)($students[0]['start_date'] ?? ''));
    $endDate = officialResponseFormArabicDate((string)($students[0]['end_date'] ?? ''));

    // Replace both sample period lines as whole lines, preserving the exact
    // wording and position of the faculty-approved PDF.
    $erase(24.5, 53.4, 166.0, 8.3);
    $erase(28.5, 60.9, 166.0, 8.3);
    $prefix = '1. นักศึกษาออกฝึกประสบการณ์วิชาชีพ ';
    $period = 'ภาคการศึกษาที่ '.$semester.' ปีการศึกษา '.$academicYear;
    $pdf->SetFont($font, '', 16);
    $periodX = 25.0 + $pdf->GetStringWidth($prefix);
    // Keep the dotted rules at the original template level and position the
    // text higher, so Thai glyphs never touch the dots.
    $write(25.0, 53.3, 165.0, $prefix, 16);
    $write($periodX, 53.3, 100.0, $period, 16, 'B');
    $dottedUnderline($periodX, 54.2, $period, 16);
    $dateRange = 'ระหว่างวันที่ '.$startDate.' ถึงวันที่ '.$endDate;
    $write(29.0, 60.3, 165.0, $dateRange, 16, 'B');
    $dottedUnderline(29.0, 61.2, $dateRange, 16);

    // One request-company relationship produces one form.  Fill only the
    // rows that belong to that request; unused rows remain blank in the
    // original template for the company to see clearly.
    foreach ($students as $index => $student) {
        if ($index >= 5) {
            break;
        }
        $y = 90.6 + ($index * 7.0);
        $name = trim((string)($student['prefix_short'] ?? '') . (string)($student['firstname'] ?? '') . ' ' . (string)($student['lastname'] ?? ''));
        // “สาขาวิชา” is printed by the original PDF already.  Add only the
        // actual programme name after that fixed label.
        $department = preg_replace('/^\s*สาขาวิชา\s*/u', '', trim((string)($student['department_name'] ?? '')));
        // Preserve the original dot leaders: the values must appear above
        // them, like handwriting on a pre-printed form, with the dots still
        // visibly under the text. Empty rows stay entirely unchanged.
        $write(32.0, $y - 2.5, 84.0, $name, 14);
        $write(133.0, $y - 2.5, 52.0, (string)$department, 14);
    }
}

function officialResponseFormArabicDate(string $date): string
{
    if ($date === '') {
        return '';
    }
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    $value = new DateTimeImmutable($date);
    return (int)$value->format('j').' '.$months[(int)$value->format('n')].' '.((int)$value->format('Y') + 543);
}
