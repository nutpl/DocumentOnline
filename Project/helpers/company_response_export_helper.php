<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;

/**
 * Builds a print-faithful Word copy of a completed company response PDF.
 * Each original PDF page becomes one A4 page image, so the form's actual
 * layout, tables, check marks, signatures, and stamps are never reflowed.
 */
function createCompanyResponseWord(string $sourcePdf, string $outputDocx): void
{
    if (!is_file($sourcePdf)) throw new RuntimeException('ไม่พบไฟล์หนังสือตอบรับต้นฉบับ');
    $renderer = companyResponsePdfRenderer();
    if ($renderer === null) throw new RuntimeException('เครื่องแม่ข่ายไม่พบโปรแกรมสำหรับแปลง PDF เป็น Word');
    $pageDimensions = companyResponsePdfPageDimensions($sourcePdf, $renderer);

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'company_response_' . bin2hex(random_bytes(12));
    if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) throw new RuntimeException('ไม่สามารถสร้างพื้นที่ชั่วคราวได้');

    try {
        $prefix = $tempDir . DIRECTORY_SEPARATOR . 'page';
        $command = escapeshellarg($renderer)
            // Keep every original detail (small text, stamps and signatures)
            // at print quality before placing the page in Word.
            . ' -png -r 600 -f 1 -l 10 ' . escapeshellarg($sourcePdf)
            . ' ' . escapeshellarg($prefix) . ' 2>&1';
        exec($command, $commandOutput, $exitCode);
        $pages = glob($prefix . '-*.png') ?: [];
        natsort($pages);
        $pages = array_values($pages);
        if ($exitCode !== 0 || !$pages) throw new RuntimeException('ไม่สามารถแปลงหน้าหนังสือตอบรับได้');

        $word = new PhpWord();
        $sectionStyle = [
            'pageSizeW' => $pageDimensions['widthTwips'],
            'pageSizeH' => $pageDimensions['heightTwips'],
            'marginTop' => 0, 'marginRight' => 0, 'marginBottom' => 0, 'marginLeft' => 0,
        ];
        foreach ($pages as $page) {
            $section = $word->addSection($sectionStyle);
            // Match the uploaded page dimensions exactly, including PDFs that
            // use a custom A4 size. This prevents any official-form reflow.
            $section->addImage($page, [
                'width' => $pageDimensions['widthPx'],
                'height' => $pageDimensions['heightPx'],
                'unit' => 'px', 'wrappingStyle' => 'inline',
            ]);
        }
        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($outputDocx);
        companyResponsePreserveWordImages($outputDocx);
    } finally {
        companyResponseRemoveDirectory($tempDir);
    }
}

/** Prevent Microsoft Word from silently downsampling the original PDF pages. */
function companyResponsePreserveWordImages(string $docxPath): void
{
    $archive = new ZipArchive();
    if ($archive->open($docxPath) !== true) throw new RuntimeException('ไม่สามารถจัดเก็บภาพเอกสารในไฟล์ Word ได้');

    try {
        $settings = $archive->getFromName('word/settings.xml');
        if (!is_string($settings)) throw new RuntimeException('ไม่พบการตั้งค่าไฟล์ Word');
        if (!str_contains($settings, 'doNotCompressPictures')) {
            $settings = str_replace(
                '</w:settings>',
                '<w:doNotCompressPictures/><w:defaultImageDpi w:val="600"/></w:settings>',
                $settings
            );
            if ($archive->addFromString('word/settings.xml', $settings) === false) {
                throw new RuntimeException('ไม่สามารถปิดการบีบอัดภาพในไฟล์ Word ได้');
            }
        }
    } finally {
        $archive->close();
    }
}

/** @return array{widthTwips:int,heightTwips:int,widthPx:float,heightPx:float} */
function companyResponsePdfPageDimensions(string $sourcePdf, string $pdfRenderer): array
{
    // Fall back to standard A4 if pdfinfo is not available. The normal path
    // uses the exact MediaBox size from the uploaded document.
    $widthPt = 595.35;
    $heightPt = 841.89;
    $pdfInfo = dirname($pdfRenderer) . DIRECTORY_SEPARATOR . 'pdfinfo.exe';
    if (is_file($pdfInfo)) {
        $command = escapeshellarg($pdfInfo) . ' ' . escapeshellarg($sourcePdf) . ' 2>&1';
        exec($command, $output, $exitCode);
        $details = implode("\n", $output);
        if ($exitCode === 0 && preg_match('/Page size:\s*([0-9.]+)\s+x\s+([0-9.]+)\s+pts/i', $details, $matches)) {
            $widthPt = (float)$matches[1];
            $heightPt = (float)$matches[2];
        }
    }

    return [
        'widthTwips' => (int)round($widthPt * 20),
        'heightTwips' => (int)round($heightPt * 20),
        // PhpWord interprets image pixels at 96 DPI.
        'widthPx' => $widthPt * (96 / 72),
        'heightPx' => $heightPt * (96 / 72),
    ];
}

function companyResponsePdfRenderer(): ?string
{
    $candidates = [];
    $fromPath = trim((string)@shell_exec('where pdftoppm 2>NUL'));
    if ($fromPath !== '') $candidates = preg_split('/\R+/', $fromPath) ?: [];
    $localAppData = getenv('LOCALAPPDATA');
    if (is_string($localAppData) && $localAppData !== '') {
        $candidates[] = $localAppData . '\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\native\\poppler\\Library\\bin\\pdftoppm.exe';
    }
    // Apache services often do not inherit the interactive user's
    // LOCALAPPDATA variable. Discover the same bundled runtime by its stable
    // location so PDF-to-Word also works from the browser, not only the CLI.
    $userRuntimePattern = 'C:\\Users\\*\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\native\\poppler\\Library\\bin\\pdftoppm.exe';
    foreach (glob($userRuntimePattern) ?: [] as $bundledRenderer) $candidates[] = $bundledRenderer;
    $candidates = array_merge($candidates, [
        'C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe',
        'C:\\Program Files (x86)\\poppler\\Library\\bin\\pdftoppm.exe',
    ]);
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate, " \t\r\n\0\x0B\"");
        // PHP's is_executable() incorrectly returns false for .exe files in
        // some Windows/XAMPP environments.  A real executable file is enough
        // on Windows; Unix still keeps the executable permission check.
        if ($candidate !== '' && is_file($candidate)
            && (DIRECTORY_SEPARATOR === '\\' || is_executable($candidate))) return $candidate;
    }
    return null;
}

function companyResponseRemoveDirectory(string $directory): void
{
    if (!is_dir($directory)) return;
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) companyResponseRemoveDirectory($path); else @unlink($path);
    }
    @rmdir($directory);
}
