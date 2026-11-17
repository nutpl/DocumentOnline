<?php
declare(strict_types=1);

// Keep the first official-letter page exactly as converted from the faculty's
// .doc source, including the section-level header/footer references.  The
// previous one-page extraction omitted these references, which shifted the
// rendered page vertically in Word.
$source = __DIR__ . '/official-word-samples/send-original-doc-reference.docx';
$target = __DIR__ . '/../../templates/official_internship/send_letter_template.docx';

if (!is_file($source) || !copy($source, $target)) {
    throw new RuntimeException('Cannot prepare the send-letter template.');
}

$zip = new ZipArchive();
if ($zip->open($target) !== true) throw new RuntimeException('Cannot open template package.');
$xml = $zip->getFromName('word/document.xml');
if ($xml === false) throw new RuntimeException('Missing word/document.xml.');

$dom = new DOMDocument();
$dom->preserveWhiteSpace = true;
if (!@$dom->loadXML($xml)) throw new RuntimeException('Invalid document XML.');
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

$body = $xpath->query('/w:document/w:body')->item(0);
if (!$body instanceof DOMElement) {
    throw new RuntimeException('Missing official-letter document body.');
}
$paragraphs = $xpath->query('/w:document/w:body/w:p');
if ($paragraphs === false || $paragraphs->length < 28) {
    throw new RuntimeException('Unexpected official-letter source structure.');
}

// The first official letter occupies its table plus 28 body paragraphs.  A
// later letter begins with the next table.  Remove every later *body child*,
// not merely later paragraphs, otherwise the later letterhead tables remain
// and Word displays duplicated logos and address blocks.
$keepThrough = $paragraphs->item(27);
if (!$keepThrough instanceof DOMElement) throw new RuntimeException('Missing closing paragraph.');
$remove = false;
foreach (iterator_to_array($body->childNodes) as $child) {
    if ($child === $keepThrough) {
        $remove = true;
        continue;
    }
    if (!$remove || !$child instanceof DOMElement || $child->localName === 'sectPr') continue;
    $body->removeChild($child);
}

$zip->addFromString('word/document.xml', $dom->saveXML());
$zip->close();

echo "Rebuilt send-letter template with original page section settings.\n";
