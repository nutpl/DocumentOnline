<?php

/**
 * Converts a Word document to PDF on both the Windows development machine and
 * the Linux web container.  Word COM gives the closest result on Windows,
 * while LibreOffice is used by Docker/Linux.
 */
function convertWordDocumentToPdf(string $sourceFile, string $destinationPdf): void
{
    if (!is_file($sourceFile)) {
        throw new RuntimeException('ไม่พบไฟล์ Word ต้นทาง');
    }

    $destinationDirectory = dirname($destinationPdf);
    if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0770, true) && !is_dir($destinationDirectory)) {
        throw new RuntimeException('ไม่สามารถเตรียมโฟลเดอร์สำหรับไฟล์ PDF ได้');
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $converter = realpath(__DIR__ . '/../scripts/convert_word_to_pdf.ps1');
        if ($converter === false) {
            throw new RuntimeException('ไม่พบตัวแปลงไฟล์ PDF');
        }
        $command = 'powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '
            . escapeshellarg($converter) . ' -SourceDocx ' . escapeshellarg($sourceFile)
            . ' -DestinationPdf ' . escapeshellarg($destinationPdf) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($destinationPdf) || filesize($destinationPdf) < 1) {
            throw new RuntimeException('ไม่สามารถแปลง Word เป็น PDF ได้');
        }
        return;
    }

    $libreOffice = getenv('LIBREOFFICE_BIN') ?: '/usr/bin/libreoffice';
    if (!is_file($libreOffice) || !is_executable($libreOffice)) {
        throw new RuntimeException('ไม่พบ LibreOffice สำหรับแปลง Word เป็น PDF');
    }

    $workDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'libreoffice-' . bin2hex(random_bytes(12));
    $profileDirectory = $workDirectory . DIRECTORY_SEPARATOR . 'profile';
    if (!mkdir($profileDirectory, 0700, true) && !is_dir($profileDirectory)) {
        throw new RuntimeException('ไม่สามารถเตรียมพื้นที่แปลงไฟล์ PDF ได้');
    }

    try {
        $profileUrl = 'file://' . str_replace('\\', '/', $profileDirectory);
        $command = escapeshellarg($libreOffice)
            . ' --headless --nologo --nodefault --nofirststartwizard'
            . ' ' . escapeshellarg('-env:UserInstallation=' . $profileUrl)
            . ' --convert-to pdf --outdir ' . escapeshellarg($workDirectory)
            . ' ' . escapeshellarg($sourceFile) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        $convertedPdf = $workDirectory . DIRECTORY_SEPARATOR . pathinfo($sourceFile, PATHINFO_FILENAME) . '.pdf';
        if ($exitCode !== 0 || !is_file($convertedPdf) || filesize($convertedPdf) < 1) {
            throw new RuntimeException('LibreOffice ไม่สามารถแปลง Word เป็น PDF ได้');
        }
        if (!@rename($convertedPdf, $destinationPdf) && !@copy($convertedPdf, $destinationPdf)) {
            throw new RuntimeException('ไม่สามารถจัดเก็บไฟล์ PDF ที่แปลงแล้วได้');
        }
    } finally {
        removeWordPdfConverterDirectory($workDirectory);
    }

    if (!is_file($destinationPdf) || filesize($destinationPdf) < 1) {
        throw new RuntimeException('ไม่สามารถแปลง Word เป็น PDF ได้');
    }
}

function removeWordPdfConverterDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $items = scandir($directory);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            removeWordPdfConverterDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($directory);
}
