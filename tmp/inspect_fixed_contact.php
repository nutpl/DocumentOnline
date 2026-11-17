<?php
$zip = new ZipArchive(); $zip->open('C:\\xampp\\htdocs\\Project\\tmp\\combined_user_3_fixed2.docx'); $xml = $zip->getFromName('word/document.xml'); $zip->close();
$dom = new DOMDocument(); $dom->loadXML($xml); $xp = new DOMXPath($dom);
$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main'); $xp->registerNamespace('wp','http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing'); $xp->registerNamespace('v','urn:schemas-microsoft-com:vml');
echo 'all=' . $xp->query('//w:t[contains(., "แผนกงานวิชาการและวิจัย")]')->length . PHP_EOL;
echo 'drawingml=' . $xp->query('//wp:anchor[.//w:t[contains(., "แผนกงานวิชาการและวิจัย")]]')->length . PHP_EOL;
echo 'vml=' . $xp->query('//v:shape[.//w:t[contains(., "แผนกงานวิชาการและวิจัย")]]')->length . PHP_EOL;