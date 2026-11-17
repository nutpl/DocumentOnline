<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/official_internship_pdf_helper.php';

$pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->AddPage();
$pdf->SetFont(thSarabunPskFont(), '', 16);
$text = 'ตามหนังสือที่อ้างถึง บริษัท คัสโตมิคซ์ จำกัด ได้ตอบรับนักศึกษา คณะวิศวกรรมศาสตร์และเทคโนโลยี วิศวกรรมคอมพิวเตอร์ เข้าฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ 1 ปีการศึกษา 2569 ระหว่างวันที่ 15 มิถุนายน 2569 ถึงวันที่ 2 ตุลาคม 2569 จำนวน 1 คน นั้น คณะขอส่งนักศึกษา ราย นางสาวอรวรรณ กันหาเขียว มารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ';
$tokens=[];$space=false;
foreach (preg_split('/(\s+)/u', trim($text), -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
    if ($part === '') continue;
    if (preg_match('/^\s+$/u', $part)) {$space=true;continue;}
    foreach (officialThaiWordPieces($part) as $piece) {$tokens[]=['text'=>$piece,'prepend_space'=>$space];$space=false;}
    $space=true;
}
$line=[];$lineWidth=0;$first=true;$spaceWidth=$pdf->GetStringWidth(' ');
foreach ($tokens as $token) {
    $width=$pdf->GetStringWidth($token['text']);$gap=($line&&!empty($token['prepend_space']))?$spaceWidth:0;$available=160-($first?25:0);
    if ($line && $lineWidth+$gap+$width>$available) {
        $lineText='';foreach($line as $i=>$item)$lineText.=($i&&!empty($item['prepend_space'])?' ':'').$item['text'];
        $clusters=preg_split('/\X/u',$lineText,-1,PREG_SPLIT_NO_EMPTY);
        printf("width=%.2f available=%.2f clusters=%d gap=%.3f %s\n",$pdf->GetStringWidth($lineText),$available,count($clusters),($available-$pdf->GetStringWidth($lineText))/(count($clusters)-1),$lineText);
        $line=[];$lineWidth=0;$first=false;
    }
    $token['prepend_space']=$line&&!empty($token['prepend_space']);$lineWidth+=$token['prepend_space']?$spaceWidth+$width:$width;$line[]=$token;
}
