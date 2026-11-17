<?php
require __DIR__ . '/../../vendor/autoload.php';
$template = $argv[1] ?? __DIR__ . '/../../templates/official_internship/send_letter_template.docx';
$word = \PhpOffice\PhpWord\IOFactory::load($template);
$section = $word->getSections()[0];
foreach ($section->getElements() as $index => $element) {
    echo $index.' '.get_class($element)."\n";
    if ($element instanceof \PhpOffice\PhpWord\Element\Text) echo '  '.$element->getText()."\n";
    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) foreach ($element->getElements() as $child) if (method_exists($child,'getText')) echo '  ['.$child->getText()."]\n";
    if ($element instanceof \PhpOffice\PhpWord\Element\Table) foreach ($element->getRows() as $row) foreach ($row->getCells() as $cell) foreach ($cell->getElements() as $child) {
        echo '  CELL '.get_class($child);
        if (method_exists($child,'getElements')) foreach($child->getElements() as $sub) if(method_exists($sub,'getText')) echo ' ['.$sub->getText().']';
        echo "\n";
    }
}
