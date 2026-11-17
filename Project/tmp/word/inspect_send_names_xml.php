<?php
$zip=new ZipArchive();$zip->open($argv[1]);$xml=$zip->getFromName('word/document.xml');$zip->close();$dom=new DOMDocument();$dom->loadXML($xml);$xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');foreach($xp->query('//w:p') as $i=>$p){if($i===25||$i===26)echo "P$i\n".$dom->saveXML($p)."\n";}
