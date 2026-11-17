<?php
declare(strict_types=1);

$source = __DIR__ . '/official-word-samples/send-two-students.docx';
$reference = __DIR__ . '/official-word-samples/send-original-doc-reference.docx';
$target = __DIR__ . '/send-section-references-test.docx';
copy($source, $target);

function documentXml(string $path): DOMDocument {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException("Cannot open $path");
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $dom = new DOMDocument(); $dom->preserveWhiteSpace = true;
    if ($xml === false || !@$dom->loadXML($xml)) throw new RuntimeException("Cannot parse $path");
    return $dom;
}
function section(DOMDocument $dom): DOMElement {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $node = $xpath->query('/w:document/w:body/w:sectPr')->item(0);
    if (!$node instanceof DOMElement) throw new RuntimeException('Missing section properties.');
    return $node;
}

$targetDom = documentXml($target);
$referenceDom = documentXml($reference);
$targetSection = section($targetDom);
$referenceSection = section($referenceDom);

$namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
foreach (iterator_to_array($targetSection->childNodes) as $child) {
    if ($child instanceof DOMElement && in_array($child->localName, ['headerReference', 'footerReference'], true)) {
        $targetSection->removeChild($child);
    }
}
$before = $targetSection->firstChild;
foreach ($referenceSection->childNodes as $child) {
    if ($child instanceof DOMElement && $child->namespaceURI === $namespace && in_array($child->localName, ['headerReference', 'footerReference'], true)) {
        $targetSection->insertBefore($targetDom->importNode($child, true), $before);
    }
}

$zip = new ZipArchive();
if ($zip->open($target) !== true) throw new RuntimeException('Cannot update test file.');
$zip->addFromString('word/document.xml', $targetDom->saveXML());
$zip->close();
