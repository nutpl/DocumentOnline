<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/staff/generate_official_batch.php');
if (!is_string($source)) throw new RuntimeException('Cannot read batch PDF generator.');

if (str_contains($source, 'mergeOfficialInternshipWordFiles($wordFiles, $mergedWord);')) {
    throw new RuntimeException('Batch PDFs must not be converted from a merged DOCX.');
}
if (!str_contains($source, 'convertWordDocumentToPdf($wordFile, $pdfFile)')
    || !str_contains($source, 'mergeOfficialInternshipPdfContents($pdfPages)')
    || !str_contains($source, "saveOfficialInternshipWordFile(\$letter['document'], \$letter['students'], \$type, \$wordFile, false)")) {
    throw new RuntimeException('Batch PDFs must convert each letter before merging.');
}

$merger = file_get_contents(dirname(__DIR__) . '/helpers/official_internship_batch_helper.php');
if (!is_string($merger)
    || !str_contains($merger, "registerNamespace('wp14'")
    || !str_contains($merger, "registerNamespace('w14'")
    || !str_contains($merger, "'wp14:anchorId'")
    || !str_contains($merger, "'wp14:editId'")
    || !str_contains($merger, "'w14:paraId'")
    || !str_contains($merger, "'w14:textId'")
    || !str_contains($merger, "'MergedObject'")
    || !str_contains($merger, 'startParagraphOnNewPage')
    || !str_contains($merger, "'w:pageBreakBefore'")
    || !str_contains($merger, 'officialInternshipNormalizeWordFloatingObjectIds($destinationFile)')) {
    throw new RuntimeException('Merged Word documents must normalize every floating-object identifier.');
}

echo "Official batch PDF layout checks passed.\n";
