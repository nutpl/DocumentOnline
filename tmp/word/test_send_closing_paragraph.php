<?php
declare(strict_types=1);

$source = __DIR__ . '/official-word-samples/send-two-students.docx';
$reference = __DIR__ . '/official-word-samples/send-original-doc-reference.docx';
$target = __DIR__ . '/send-closing-paragraph-test.docx';
copy($source, $target);

function loadWordXml(string $path): DOMDocument {
    $zip = new ZipArchive(); if ($zip->open($path) !== true) throw new RuntimeException('Cannot open document.');
    $xml = $zip->getFromName('word/document.xml'); $zip->close();
    $dom = new DOMDocument(); $dom->preserveWhiteSpace = true;
    if ($xml === false || !@$dom->loadXML($xml)) throw new RuntimeException('Cannot load document XML.');
    return $dom;
}
function bodyParagraph(DOMDocument $dom, int $index): DOMElement {
    $xpath = new DOMXPath($dom); $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $node = $xpath->query('/w:document/w:body/w:p')->item($index);
    if (!$node instanceof DOMElement) throw new RuntimeException('Missing expected paragraph.');
    return $node;
}

$targetDom = loadWordXml($target);
$referenceDom = loadWordXml($reference);
$closing = $targetDom->importNode(bodyParagraph($referenceDom, 27), true);
$xpath = new DOMXPath($targetDom); $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$section = $xpath->query('/w:document/w:body/w:sectPr')->item(0);
if (!$section instanceof DOMElement) throw new RuntimeException('Missing target section.');
$section->parentNode?->insertBefore($closing, $section);

$zip = new ZipArchive(); if ($zip->open($target) !== true) throw new RuntimeException('Cannot update test document.');
$zip->addFromString('word/document.xml', $targetDom->saveXML()); $zip->close();
