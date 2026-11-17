<?php
require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Gives every floating DrawingML/VML object in a generated DOCX a document-
 * wide identity.  Microsoft Word otherwise associates objects that share the
 * template's original IDs after InsertFile(), which can duplicate or detach
 * the QR/contact footer in a combined document.
 */
function officialInternshipNormalizeWordFloatingObjectIds(string $docxFile): void
{
    $archive = new ZipArchive();
    if ($archive->open($docxFile) !== true) {
        throw new RuntimeException('ไม่สามารถเปิดไฟล์ Word ที่รวมแล้วเพื่อตรวจสอบวัตถุลอยได้');
    }
    try {
        $xml = $archive->getFromName('word/document.xml');
        if ($xml === false) {
            throw new RuntimeException('ไฟล์ Word ที่รวมแล้วไม่สมบูรณ์');
        }
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (!$dom->loadXML($xml)) {
            throw new RuntimeException('ไม่สามารถอ่านข้อมูลเอกสาร Word ที่รวมแล้วได้');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        $xpath->registerNamespace('wp14', 'http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing');
        $xpath->registerNamespace('w14', 'http://schemas.microsoft.com/office/word/2010/wordml');
        $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

        $nextDrawingId = 1;
        foreach ($xpath->query('//wp:docPr') as $drawingProperties) {
            if ($drawingProperties instanceof DOMElement) {
                $drawingProperties->setAttribute('id', (string) $nextDrawingId++);
            }
        }
        $nextAnchorId = 1;
        $nextEditId = 1;
        foreach ($xpath->query('//wp:anchor') as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }
            $anchor->setAttributeNS(
                'http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing',
                'wp14:anchorId',
                sprintf('F%07X', $nextAnchorId++)
            );
            $anchor->setAttributeNS(
                'http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing',
                'wp14:editId',
                sprintf('E%07X', $nextEditId++)
            );
        }
        // InsertFile also copies Word's paragraph identities.  In the
        // approved template a contact paragraph exists both in normal flow
        // and inside its positioned textbox.  If equivalent paragraphs from
        // different letters retain the same w14 IDs, Word can render that
        // textbox text in the wrong page flow after a merge.
        $word2010Namespace = 'http://schemas.microsoft.com/office/word/2010/wordml';
        $nextParagraphId = 1;
        $nextTextId = 1;
        foreach ($xpath->query('//*[@w14:paraId]') as $identifiedElement) {
            if (!$identifiedElement instanceof DOMElement) {
                continue;
            }
            $identifiedElement->setAttributeNS($word2010Namespace, 'w14:paraId', sprintf('%08X', $nextParagraphId++));
            if ($identifiedElement->hasAttributeNS($word2010Namespace, 'textId')) {
                $identifiedElement->setAttributeNS($word2010Namespace, 'w14:textId', sprintf('%08X', $nextTextId++));
            }
        }
        $nextVmlId = 1;
        foreach ($xpath->query('//v:shape|//v:group') as $shape) {
            if ($shape instanceof DOMElement) {
                $shape->setAttribute('id', 'MergedObject' . $nextVmlId++);
            }
        }
        if ($archive->addFromString('word/document.xml', $dom->saveXML()) === false) {
            throw new RuntimeException('ไม่สามารถบันทึกวัตถุในไฟล์ Word ที่รวมแล้วได้');
        }
    } finally {
        $archive->close();
    }
}

/**
 * Appends generated official letters into one DOCX while retaining the first
 * document's headers, footer, sections, styles and media.  Every input is
 * generated from the same approved template, so their relationship IDs are
 * compatible with the base package.
 *
 * @param string[] $sourceFiles
 * @param int[] $multiStudentSourceIndexes Zero-based input indexes of letters
 *                                         whose layout must not be followed by
 *                                         a standalone page-break paragraph.
 */
function mergeOfficialInternshipWordFiles(array $sourceFiles, string $destinationFile, array $multiStudentSourceIndexes = []): void
{
    $sourceFiles = array_values(array_filter($sourceFiles, static fn ($file) => is_file($file)));
    // Kept for call compatibility. Every generated letter is now separated
    // through its own first paragraph, irrespective of student count.
    unset($multiStudentSourceIndexes);
    if (!$sourceFiles) {
        throw new RuntimeException('ไม่พบไฟล์ Word ที่ต้องการรวม');
    }
    if (count($sourceFiles) === 1) {
        if (!@copy($sourceFiles[0], $destinationFile)) {
            throw new RuntimeException('ไม่สามารถเตรียมไฟล์ Word สำหรับรวมเอกสารได้');
        }
        return;
    }
    if (!@copy($sourceFiles[0], $destinationFile)) {
        throw new RuntimeException('ไม่สามารถเตรียมไฟล์ Word สำหรับรวมเอกสารได้');
    }

    $archive = new ZipArchive();
    if ($archive->open($destinationFile) !== true) {
        throw new RuntimeException('ไม่สามารถเปิดไฟล์ Word สำหรับรวมเอกสารได้');
    }
    try {
        $baseXml = $archive->getFromName('word/document.xml');
        if ($baseXml === false) {
            throw new RuntimeException('ไฟล์ Word ต้นฉบับไม่สมบูรณ์');
        }
        $baseDom = new DOMDocument();
        $baseDom->preserveWhiteSpace = true;
        if (!$baseDom->loadXML($baseXml)) {
            throw new RuntimeException('ไม่สามารถอ่านข้อมูลเอกสาร Word ได้');
        }
        $baseXpath = new DOMXPath($baseDom);
        $baseXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $baseXpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        $baseXpath->registerNamespace('wp14', 'http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing');
        $baseXpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
        $baseBody = $baseXpath->query('/w:document/w:body')->item(0);
        if (!$baseBody instanceof DOMElement) {
            throw new RuntimeException('ไม่พบเนื้อหาเอกสาร Word');
        }
        $sectionProperties = $baseXpath->query('./w:sectPr', $baseBody)->item(0);
        $nextDrawingId = 1;
        foreach ($baseXpath->query('//wp:docPr/@id') as $drawingId) {
            $nextDrawingId = max($nextDrawingId, (int)$drawingId->nodeValue + 1);
        }
        $nextVmlId = 1;
        $nextAnchorId = 1;
        $nextEditId = 1;

        // Each source letter starts from the same template and therefore has
        // the same DrawingML/VML object IDs. Word treats duplicate IDs in a
        // merged document as the same floating object, which can detach the
        // footer contact line and place it alone on the next page. Assign
        // document-wide IDs as every source body is appended.
        $renumberImportedDrawingObjects = static function (DOMElement $node) use ($baseXpath, &$nextDrawingId, &$nextVmlId, &$nextAnchorId, &$nextEditId): void {
            foreach ($baseXpath->query('.//wp:docPr', $node) as $drawingProperties) {
                if ($drawingProperties instanceof DOMElement) {
                    $drawingProperties->setAttribute('id', (string)$nextDrawingId++);
                }
            }
            foreach ($baseXpath->query('.//wp:anchor', $node) as $anchor) {
                if (!$anchor instanceof DOMElement) continue;
                // Word also uses these Word 2010 identifiers to associate a
                // floating object with its layout anchor. They must not be
                // duplicated when identical template letters are combined.
                $anchor->setAttributeNS(
                    'http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing',
                    'wp14:anchorId',
                    sprintf('F%07X', $nextAnchorId++)
                );
                $anchor->setAttributeNS(
                    'http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing',
                    'wp14:editId',
                    sprintf('E%07X', $nextEditId++)
                );
            }
            foreach ($baseXpath->query('.//v:shape|.//v:group', $node) as $shape) {
                if ($shape instanceof DOMElement) {
                    $shape->setAttribute('id', 'MergedObject' . $nextVmlId++);
                }
            }
        };

        $startParagraphOnNewPage = static function (DOMElement $paragraph) use ($baseDom, $baseXpath): void {
            $paragraphProperties = $baseXpath->query('./w:pPr', $paragraph)->item(0);
            if (!$paragraphProperties instanceof DOMElement) {
                $paragraphProperties = $baseDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:pPr');
                $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
            }
            $pageBreakBefore = $baseXpath->query('./w:pageBreakBefore', $paragraphProperties)->item(0);
            if (!$pageBreakBefore instanceof DOMElement) {
                $pageBreakBefore = $baseDom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:pageBreakBefore');
                $paragraphProperties->appendChild($pageBreakBefore);
            }
            $pageBreakBefore->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', 'true');
        };

        foreach (array_slice($sourceFiles, 1, null, true) as $sourceIndex => $sourceFile) {
            $sourceArchive = new ZipArchive();
            if ($sourceArchive->open($sourceFile) !== true) {
                throw new RuntimeException('ไม่สามารถเปิดเอกสาร Word ที่ต้องการรวมได้');
            }
            try {
                $sourceXml = $sourceArchive->getFromName('word/document.xml');
            } finally {
                $sourceArchive->close();
            }
            if ($sourceXml === false) {
                throw new RuntimeException('เอกสาร Word ที่ต้องการรวมไม่สมบูรณ์');
            }
            $sourceDom = new DOMDocument();
            if (!$sourceDom->loadXML($sourceXml)) {
                throw new RuntimeException('ไม่สามารถอ่านเอกสาร Word ที่ต้องการรวมได้');
            }
            $sourceXpath = new DOMXPath($sourceDom);
            $sourceXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $sourceBody = $sourceXpath->query('/w:document/w:body')->item(0);
            if (!$sourceBody instanceof DOMElement) {
                throw new RuntimeException('ไม่พบเนื้อหาเอกสาร Word ที่ต้องการรวม');
            }

            $firstImportedParagraph = true;
            foreach (iterator_to_array($sourceBody->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 'sectPr') {
                    continue;
                }
                $importedChild = $baseDom->importNode($child, true);
                if ($importedChild instanceof DOMElement) {
                    $renumberImportedDrawingObjects($importedChild);
                    if ($firstImportedParagraph && $importedChild->localName === 'p') {
                        // Place the break on the first original paragraph, so
                        // Word has no standalone break paragraph to turn into
                        // an unwanted blank page.
                        $startParagraphOnNewPage($importedChild);
                        $firstImportedParagraph = false;
                    }
                }
                $baseBody->insertBefore($importedChild, $sectionProperties ?: null);
            }
        }
        if ($archive->addFromString('word/document.xml', $baseDom->saveXML()) === false) {
            throw new RuntimeException('ไม่สามารถบันทึกไฟล์ Word ที่รวมแล้วได้');
        }
    } finally {
        $archive->close();
    }
    // Preserve each source document's original AlternateContent blocks so
    // Word chooses exactly the same editable DrawingML/VML representation as
    // it does for an individual download, while keeping document-wide IDs.
    officialInternshipNormalizeWordFloatingObjectIds($destinationFile);
}

/**
 * @param string[] $pdfContents
 */
function mergeOfficialInternshipPdfContents(array $pdfContents): string
{
    if (!$pdfContents) {
        throw new RuntimeException('ไม่พบไฟล์ PDF ที่ต้องการรวม');
    }
    $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $temporaryFiles = [];
    try {
        foreach ($pdfContents as $contents) {
            $temporaryFile = tempnam(sys_get_temp_dir(), 'official-batch-pdf-');
            if ($temporaryFile === false || @file_put_contents($temporaryFile, $contents) === false) {
                throw new RuntimeException('ไม่สามารถเตรียมไฟล์ PDF สำหรับรวมเอกสารได้');
            }
            $temporaryFiles[] = $temporaryFile;
            $pageCount = $pdf->setSourceFile($temporaryFile);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $template = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($template);
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);
            }
        }
        return $pdf->Output('', 'S');
    } finally {
        foreach ($temporaryFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }
    }
}
