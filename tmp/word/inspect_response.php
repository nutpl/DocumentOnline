<?php
function inspect(string $file): void {
  $zip = new ZipArchive(); $zip->open($file); $xml = $zip->getFromName('word/document.xml'); $zip->close();
  $dom = new DOMDocument(); $dom->loadXML($xml); $xp = new DOMXPath($dom); $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
  foreach ($xp->query('//w:p') as $i=>$p) {
    $s=''; foreach($xp->query('.//w:t',$p) as $n) $s.=$n->textContent;
    if (str_contains($s,'ทั้งนี้ โปรด')) { echo "FILE: $file P:$i\nTEXT: ".json_encode($s,JSON_UNESCAPED_UNICODE)."\n"; echo $dom->saveXML($p)."\n\n"; }
  }
}
inspect($argv[1]);
