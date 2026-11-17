<?php
$path = 'C:\\Users\\mewww\\Downloads\\ใบส่งตัวนักศึกษารวมหลายบริษัท (3).docx';
$zip = new ZipArchive();
$zip->open($path);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$dom = new DOMDocument(); $dom->loadXML($xml);
$xp = new DOMXPath($dom);
$xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
$nodes = $xp->query('//w:p[.//w:t[contains(., "แผนกงานวิชาการและวิจัย")]]');
echo "count=" . $nodes->length . PHP_EOL;
foreach ($nodes as $i => $p) {
  $ancestor = $xp->query('ancestor::v:shape', $p)->length;
  $descendant = $xp->query('.//v:shape', $p)->length;
  $parent = $p->parentNode instanceof DOMElement ? $p->parentNode->nodeName : 'none';
  $text = trim(preg_replace('/\\s+/', ' ', $p->textContent));
  echo "$i parent=$parent ancestor=$ancestor descendant=$descendant text=" . mb_substr($text, 0, 100) . PHP_EOL;
}