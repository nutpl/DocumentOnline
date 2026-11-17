<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/pdf_font_helper.php';
require_once __DIR__ . '/official_document_number_helper.php';

use setasign\Fpdi\Tcpdf\Fpdi;

function officialThaiDigits(string $text): string
{
    return strtr($text, [
        '0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔',
        '5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙',
    ]);
}

function officialThaiDate(?string $date): string
{
    if (!$date) return '-';
    $months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $d=new DateTimeImmutable($date);
    return officialThaiDigits((int)$d->format('j').' '.$months[(int)$d->format('n')].' '.((int)$d->format('Y')+543));
}

/**
 * The send-letter QR code is part of the approved Word template.  Read that
 * immutable image once into the system temporary directory so the server-side
 * PDF renderer can place it at the same position as the signed-off document.
 */
function officialPdfQrRasterPath(string $sourcePath, string $cachePrefix): ?string
{
    if (!is_file($sourcePath) || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return null;
    $destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cachePrefix . '-' . md5_file($sourcePath) . '.jpg';
    if (is_file($destination) && filesize($destination) > 1000) return $destination;

    $contents = file_get_contents($sourcePath);
    if (!is_string($contents) || $contents === '') return null;
    $sourceImage = @imagecreatefromstring($contents);
    if ($sourceImage === false) return null;
    $canvas = imagecreatetruecolor(imagesx($sourceImage), imagesy($sourceImage));
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $sourceImage, 0, 0, 0, 0, imagesx($sourceImage), imagesy($sourceImage));
    $written = @imagejpeg($canvas, $destination, 100);
    imagedestroy($canvas);
    imagedestroy($sourceImage);
    return $written ? $destination : null;
}

function officialSendLetterQrCodePath(array $document = []): ?string
{
    $configuredPath = function_exists('officialWordTemplateConfiguredQrPath')
        ? officialWordTemplateConfiguredQrPath((string)($document['_template_assessment_qr_image'] ?? ''), '')
        : '';
    if ($configuredPath !== '') return officialPdfQrRasterPath($configuredPath, 'official-send-letter-qr');

    static $cachedPath = null;
    if ($cachedPath !== null) return $cachedPath;

    $template = __DIR__ . '/../templates/official_internship/send_letter_template.docx';
    if (!is_file($template) || !class_exists('ZipArchive')) return $cachedPath = null;

    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return $cachedPath = null;

    // TCPDF creates temporary alpha masks for transparent PNG files.  Flatten
    // the Word-template QR to an opaque JPEG first so PDF creation works in
    // both XAMPP and the Docker container without depending on its temp path.
    $destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'official-send-letter-qr-' . md5_file($template) . '.jpg';
    if (is_file($destination) && filesize($destination) > 1000) return $cachedPath = $destination;

    $zip = new ZipArchive();
    if ($zip->open($template) !== true) return $cachedPath = null;
    try {
        $contents = $zip->getFromName('word/media/image2.png');
    } finally {
        $zip->close();
    }
    if ($contents === false || strlen($contents) < 1000) {
        return $cachedPath = null;
    }
    $sourceImage = @imagecreatefromstring($contents);
    if ($sourceImage === false) return $cachedPath = null;
    $canvas = imagecreatetruecolor(imagesx($sourceImage), imagesy($sourceImage));
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $sourceImage, 0, 0, 0, 0, imagesx($sourceImage), imagesy($sourceImage));
    $written = @imagejpeg($canvas, $destination, 100);
    imagedestroy($canvas);
    imagedestroy($sourceImage);
    if (!$written) return $cachedPath = null;
    return $cachedPath = $destination;
}

/** QR code for the staff-editable assessment-steps instructions. */
function officialEvaluationStepsQrCodePath(array $document = []): ?string
{
    $fallback = __DIR__ . '/../templates/official_internship/evaluation_steps_qr.png';
    $configuredPath = function_exists('officialWordTemplateConfiguredQrPath')
        ? officialWordTemplateConfiguredQrPath((string)($document['_template_steps_qr_image'] ?? ''), $fallback)
        : $fallback;
    return officialPdfQrRasterPath($configuredPath, 'official-evaluation-steps-qr');
}

/** Preserve the approved four-line captions for both QR blocks in PDFs. */
function officialPdfQrCaptionLines(array $document, string $settingKey): string
{
    $defaults = function_exists('officialTemplateSettingDefaults') ? officialTemplateSettingDefaults() : [];
    $fallback = (string)($defaults[$settingKey] ?? '');
    $value = (string)($document['_template_' . $settingKey] ?? $fallback);
    if (function_exists('officialWordTemplateCaptionLines')) {
        return implode("\n", officialWordTemplateCaptionLines($value, $fallback));
    }
    return $value;
}

function officialStudentNames(array $students, bool $joinPrefix=false): string
{
    if (count($students) === 1) {
        $student = $students[0];
        $prefix=(string)($student['prefix_short']??'');
        return officialThaiDigits(trim($prefix.($joinPrefix?'':' ').$student['firstname'].' '.$student['lastname']));
    }
    $lines=[];
    foreach($students as $i=>$student){
        $prefix=(string)($student['prefix_short']??'');
        $lines[]=officialThaiDigits(($i+1).'.  '.trim($prefix.($joinPrefix?'':' ').$student['firstname'].' '.$student['lastname']));
    }
    return implode("\n",$lines);
}

function officialThaiWordPieces(string $token): array
{
    // These are the formal-letter terms used by the internship letters.  They
    // let the PDF follow Word's Thai line-break behavior without splitting a
    // Thai word into unrelated characters.
    static $terms = [
        'ตามหนังสือที่อ้างถึง','และเทคโนโลยี','ภาคการศึกษาที่','ปีการศึกษา','ระหว่างวันที่','ดังรายชื่อต่อไปนี้',
        'ประสบการณ์วิชาชีพ','การปฏิบัติงาน','ตามระยะเวลา','ที่กำหนด','ในการเข้าสู่ระบบ','จะแจ้งไปยังอีเมล','พนักงานที่ปรึกษา',
        'เพื่อดำเนินการ','ตามขั้นตอนการประเมินนักศึกษา','ดังเอกสารแนบท้าย','และขอความอนุเคราะห์','หน่วยงานของท่าน',
        'ออกหนังสือรับรองการปฏิบัติงาน','เพื่อใช้เป็นหลักฐาน','มารายงานตัว','ได้ตอบรับ','เข้าฝึก','นักศึกษา','คณะ','ขอส่ง',
        'ประสบการณ์','วิชาชีพ','ปีการศึกษา','ระหว่าง','ถึงวันที่','จำนวน','ทั้งนี้','ขอให้','หน่วยงาน','ประเมินผล',
        'ด้านล่าง','หลังจากที่','เสร็จสิ้น','รหัสผ่าน','ของนักศึกษา','ให้แก่นักศึกษา','แสดงการฝึก','ต่อไป','นั้น','เพื่อ','ตาม','โดย','และ','คน',
    ];
    usort($terms, static fn(string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
    $pieces=[];$buffer='';$offset=0;$length=mb_strlen($token,'UTF-8');
    while($offset<$length){
        $remaining=mb_substr($token,$offset,null,'UTF-8');$match=null;
        foreach($terms as $term){if(str_starts_with($remaining,$term)){$match=$term;break;}}
        if($match===null){$buffer.=mb_substr($token,$offset,1,'UTF-8');$offset++;continue;}
        if($buffer!==''){$pieces[]=$buffer;$buffer='';}
        $pieces[]=$match;$offset+=mb_strlen($match,'UTF-8');
    }
    if($buffer!=='')$pieces[]=$buffer;
    return $pieces ?: [$token];
}

function officialWriteJustifiedText(Fpdi $pdf,string $text,float $x,float $y,float $width,float $lineHeight,float $firstIndent,array $protectedPhrases=[],bool $stretchSpaces=false): float
{
    $placeholders=[];
    foreach(array_values(array_filter($protectedPhrases)) as $index=>$phrase){
        $key='__PROTECTED_'.$index.'__';
        $text=preg_replace('/'.preg_quote($phrase,'/').'/', ' '.$key.' ', $text, 1);
        $placeholders[$key]=$phrase;
    }
    $spaceWidth=$pdf->GetStringWidth(' ');
    foreach(preg_split('/\R/u',$text) as $segment){
        $tokens=[];$prependSpace=false;
        foreach(preg_split('/(\s+)/u',trim($segment),-1,PREG_SPLIT_DELIM_CAPTURE) as $part){
            if($part==='')continue;
            if(preg_match('/^\s+$/u',$part)){$prependSpace=true;continue;}
            $pieces=isset($placeholders[$part])?[$part]:officialThaiWordPieces($part);
            foreach($pieces as $piece){$tokens[]=['text'=>$piece,'prepend_space'=>$prependSpace];$prependSpace=false;}
            $prependSpace=true;
        }
        if(!$tokens){$y+=$lineHeight;continue;}
        $lines=[];$line=[];$lineWidth=0.0;$isFirst=true;
        $queue=$tokens;
        while($queue){
            $token=$queue[0];$display=$placeholders[$token['text']]??$token['text'];$tokenWidth=$pdf->GetStringWidth($display);
            $available=$width-($isFirst?$firstIndent:0);
            $gap=($line&&!empty($token['prepend_space']))?$spaceWidth:0;
            $candidate=$lineWidth+$gap+$tokenWidth;
            if($candidate<=$available){$hasLine=(bool)$line;$lineWidth=$candidate;$line[]=['text'=>$display,'prepend_space'=>$hasLine&&!empty($token['prepend_space'])];array_shift($queue);continue;}

            // Never split Thai text by character.  A word or protected phrase
            // that no longer fits is moved to the next line, like Word's
            // normal paragraph flow.  This avoids fragments such as a trailing
            // single Thai glyph at the right edge of the document.
            if($line){$lines[]=['tokens'=>$line,'width'=>$lineWidth,'indent'=>$isFirst?$firstIndent:0];$line=[];$lineWidth=0.0;$isFirst=false;continue;}
            // A single token wider than the available line is kept intact.
            // This fallback prevents a loop for an unusually long name.
            $line=[['text'=>$display,'prepend_space'=>false]];$lineWidth=$tokenWidth;array_shift($queue);
        }
        if($line)$lines[]=['tokens'=>$line,'width'=>$lineWidth,'indent'=>$isFirst?$firstIndent:0];
        foreach($lines as $lineIndex=>$lineData){
            $lineTokens=$lineData['tokens'];$indent=(float)$lineData['indent'];$available=$width-$indent;
            $lineText='';foreach($lineTokens as $tokenIndex=>$token){$lineText.=($tokenIndex>0&&!empty($token['prepend_space'])?' ':'').$token['text'];}
            // Use TCPDF's native character-spacing instruction for Thai
            // justification.  Unlike positioning every cluster in a separate
            // Cell, this keeps combining marks attached to their base glyph.
            // Only non-final lines are justified, matching Word paragraphs.
            $characterSpacing=0.0;
            if($stretchSpaces&&$lineIndex<count($lines)-1){
                // TCPDF applies character spacing per Unicode code point,
                // including Thai vowels and tone marks.  Use the same count;
                // grapheme-cluster counting made the spacing several times
                // too large on Thai lines.
                $clusterCount=mb_strlen($lineText,'UTF-8');
                $lineTextWidth=$pdf->GetStringWidth($lineText);
                // Do not force a short Thai line to the right edge.  Word
                // leaves such a line natural; forcing it creates conspicuous
                // gaps between vowels and tone marks.
                if($clusterCount>1&&$lineTextWidth>=($available*0.88)&&$lineTextWidth<$available){
                    $characterSpacing=($available-$lineTextWidth)/($clusterCount-1);
                    if($characterSpacing<=0.22)$pdf->SetFontSpacing($characterSpacing);else $characterSpacing=0.0;
                }
            }
            $pdf->SetXY($x+$indent,$y);
            $pdf->Cell($available,$lineHeight,$lineText,0,0,'L');
            if($characterSpacing!==0.0)$pdf->SetFontSpacing(0);
            $y+=$lineHeight;
        }
    }
    $pdf->SetY($y);
    return $y;
}

function createOfficialInternshipPdf(array $document,array $students): Fpdi
{
    $isRequest=$document['document_type']==='request';
    $source=realpath(__DIR__.'/../uploads/pdf/'.($isRequest?'internship_request_official.pdf':'internship_send_official.pdf'));
    if(!$source){throw new RuntimeException('ไม่พบต้นฉบับหนังสือราชการ');}
    // Match the approved send-letter document: top 0.25 cm, bottom 0.41 cm,
    // left 3 cm and right 2 cm. The source PDF supplies the fixed letterhead
    // and footer, while all generated text follows this content area.
    $marginTop=2.5;
    $marginBottom=4.1;
    $marginLeft=30.0;
    $marginRight=20.0;
    $pdf=new Fpdi('P','mm','A4',true,'UTF-8',false);$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetMargins($marginLeft,$marginTop,$marginRight);$pdf->SetAutoPageBreak(false,$marginBottom);
    $pdf->setSourceFile($source);$tpl=$pdf->importPage(1);$pdf->AddPage('P','A4');$pdf->useTemplate($tpl,0,0,210,297);
    $sendQrCodePath=$isRequest?null:officialSendLetterQrCodePath($document);
    $stepsQrCodePath=$isRequest?null:officialEvaluationStepsQrCodePath($document);
    // Retain the original Garuda and faculty address. Replace all example-specific content.
    $pdf->SetFillColor(255,255,255);$pdf->Rect(20,46,82,18,'F');
    if($isRequest){$pdf->Rect(62,84,86,13,'F');$pdf->Rect(19,88,174,199,'F');}
    else{
        // Start below the faculty address so the final address line
        // "จังหวัดนครราชสีมา ๓๐๐๐๐" is not clipped by the cleanup block.
        $pdf->Rect(62,76,86,21,'F');
        // Clear the example-specific letter body.  The QR and contact block are
        // redrawn below from the approved Word source because their old PDF
        // positions do not match the current official letter layout.
        $pdf->Rect(19,82,174,147,'F');
        $pdf->Rect(90.5,229,102.5,31,'F');
        if($sendQrCodePath!==null || $stepsQrCodePath!==null){
            $pdf->Rect(20,223,83,43,'F');
            // The compact 4–6-student layout puts the contact line lower,
            // so clear the full lower contact region from the source page.
            $pdf->Rect(20,266,175,24,'F');
        }
    }
    $thaiFont=thSarabunPskFont();
    // The source letter uses the standard 16 pt TH Sarabun PSK face in its
    // faculty-address block. Match all generated content to that typography.
    $pdf->SetTextColor(0,0,0);$pdf->SetFont($thaiFont,'',16);
    $pdf->SetXY($isRequest?29:$marginLeft,$isRequest?53.8:46);$pdf->Cell(80,6,'ที่  '.officialFormatDocumentNumber((string)($document['document_number'] ?? '')));
    $dateX=$isRequest?90.5:95;
    $pdf->SetXY($dateX,$isRequest?86.6:78);$pdf->Cell(70,6,officialThaiDate($document['document_date']),0,0,'C');
    $semester=officialThaiDigits((string)($students[0]['semester']??''));$academic=officialThaiDigits((string)($students[0]['academic_year']??0));$start=officialThaiDate($students[0]['start_date']??null);$end=officialThaiDate($students[0]['end_date']??null);$department=officialThaiDigits((string)($students[0]['department_name']??'สาขาวิชา'));$count=count($students);$countThai=officialThaiDigits((string)$count);$company=officialThaiDigits((string)$document['company_name']);
    // Four to six students use two columns.  One to three students retain the
    // approved Word-style single-column list.
    $compactFivePreview=$count>=4&&$count<=6;
    $multiStudentLayout=$count>=2&&$count<=6;
    if($isRequest){
        $requestRecipient=trim(implode(' ',array_filter([
            $students[0]['recipient_name']??'',
            $students[0]['recipient_position']??'',
            $document['company_name']??'',
        ])));
        if($requestRecipient==='')$requestRecipient=trim((string)($document['recipient_name']??''));
        $pdf->SetXY(29,95.35);$pdf->Cell(18,6.4,'เรื่อง');$pdf->SetXY(47,95.35);$pdf->Cell(143,6.4,'ขอความอนุเคราะห์รับนักศึกษาฝึกประสบการณ์วิชาชีพ');
        $pdf->SetXY(29,104.1);$pdf->Cell(18,6.4,'เรียน');$pdf->SetXY(47,104.1);$pdf->Cell(143,6.4,officialThaiDigits($requestRecipient));
        $pdf->SetXY(29,112.6);$pdf->Cell(27,6.4,'สิ่งที่ส่งมาด้วย');
        $attachmentRows=[
            ['๑. ใบสมัคร','จำนวน '.$countThai.' ชุด'],
            ['๒. ใบแสดงผลการเรียน','จำนวน '.$countThai.' ชุด'],
            ['๓. แบบตอบรับนักศึกษา','จำนวน '.officialThaiDigits((string)max(1,$count)).' แผ่น'],
        ];
        foreach($attachmentRows as $rowIndex=>$attachment){
            $rowY=112.6+($rowIndex*6.4);
            $pdf->SetXY(56,$rowY);$pdf->Cell(69,6.4,$attachment[0],0,0,'L');
            $pdf->SetXY(125,$rowY);$pdf->Cell(44,6.4,$attachment[1],0,0,'L');
        }
        $studentListLead=$count===1?' ':"\n";
        $indent=str_repeat(' ',18);
        $requestIntro="ด้วยคณะวิศวกรรมศาสตร์และเทคโนโลยี ได้กำหนดให้นักศึกษาชั้นปีที่ ๓ มีการออกฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end เพื่อให้นักศึกษามีประสบการณ์ในการปฏิบัติงานและเตรียมตัวออกไปปฏิบัติงานหลังจากสำเร็จการศึกษา";
        $requestDepartment=str_starts_with($department,'สาขาวิชา')?$department:'สาขาวิชา'.$department;
        // คงถ้อยคำติดกันตามหนังสือราชการต้นฉบับ ไม่แทรกช่องว่างเพื่อบังคับการตัดบรรทัด
        $requestBody="คณะเห็นว่า $company เป็นหน่วยงานที่มีการดำเนินงานเกี่ยวข้องกับ $requestDepartment ดังนั้น เพื่อเป็นการเสริมสร้างความรู้และประสบการณ์วิชาชีพให้แก่นักศึกษา จึงขอความอนุเคราะห์รับนักศึกษาเข้าฝึกประสบการณ์วิชาชีพ จำนวน $countThai คน";
        if($count===1){$requestBody.=' ได้แก่ '.officialStudentNames($students,true);}else{$requestBody.=' ดังนี้';}
        $requestLineHeight=6.4;
        $requestX=29;$requestWidth=160;
        officialWriteJustifiedText($pdf,$requestIntro,$requestX,134.5,$requestWidth,$requestLineHeight,24.6,[
            $start,$end,
        ],true);
        officialWriteJustifiedText($pdf,$requestBody,$requestX,$pdf->GetY(),$requestWidth,$requestLineHeight,24.6,[
            $company,$requestDepartment,'ขอความอนุเคราะห์',
        ],true);
        $y=$pdf->GetY()+2.1;
        if($count>1){
            if($compactFivePreview){
                $compactListHeight=5.5;$listStartY=$y;
                foreach($students as $studentIndex=>$student){
                    $column=$studentIndex%2;$row=(int)floor($studentIndex/2);
                    $listX=$column===0?43:116;
                    $name=officialThaiDigits(($studentIndex+1).'.  '.trim(($student['prefix_short']??'').$student['firstname'].' '.$student['lastname']));
                    $pdf->SetXY($listX,$listStartY+($row*$compactListHeight));$pdf->Cell(72,$compactListHeight,$name,0,0,'L');
                }
                $y=$listStartY+(ceil($count/2)*$compactListHeight)+1.2;
            }else{
                foreach($students as $studentIndex=>$student){$pdf->SetXY(54.2,$y);$pdf->Cell(120,$requestLineHeight,officialThaiDigits(($studentIndex+1).'.  '.trim(($student['prefix_short']??'').$student['firstname'].' '.$student['lastname'])));$y+=$requestLineHeight;}
                $y+=2.1;
            }
        }
        $pdf->SetY($y);
        if(!empty($document['response_due_date'])&&$compactFivePreview){
            $responseLead='ทั้งนี้ โปรดส่งแบบตอบรับนักศึกษาตามสิ่งที่ส่งมาด้วย ';
            $responseDue='ภายในวันที่ '.officialThaiDate($document['response_due_date']);
            $pdf->SetXY($requestX+24.6,$y);
            $pdf->SetFont($thaiFont,'',16);$pdf->Cell($pdf->GetStringWidth($responseLead),5.5,$responseLead,0,0,'L');
            $pdf->SetFont(thSarabunPskFont('B'),'',16);$pdf->Cell(0,5.5,$responseDue,0,0,'L');
            $pdf->SetFont($thaiFont,'',16);$y+=5.7;
        }elseif(!empty($document['response_due_date'])){
            $responseLead='ทั้งนี้ โปรดส่งแบบตอบรับนักศึกษาตามสิ่งที่ส่งมาด้วย ';
            $responseDue='ภายในวันที่ '.officialThaiDate($document['response_due_date']);
            $pdf->SetXY($requestX+24.6,$y);
            $pdf->SetFont($thaiFont,'',16);$pdf->Cell($pdf->GetStringWidth($responseLead),6,$responseLead,0,0,'L');
            $pdf->SetFont(thSarabunPskFont('B'),'',16);$pdf->Cell(0,6,$responseDue,0,1,'L');
            $pdf->SetFont($thaiFont,'',16);$y=$pdf->GetY();
        }
        // Fine-tune the visual left edge of “จึงเรียน” to match “ทั้งนี้” in
        // the printed TH Sarabun PSK output.
        officialWriteJustifiedText($pdf,'จึงเรียนมาเพื่อโปรดพิจารณา หวังเป็นอย่างยิ่งว่าคงได้รับความอนุเคราะห์จากหน่วยงานด้วยดี และขอขอบคุณมา ณ โอกาสนี้',$requestX,$y,$requestWidth,$compactFivePreview?5.5:$requestLineHeight,24.3,[
            'และขอขอบคุณมา ณ โอกาสนี้',
        ]);
    }else{
        $sendRecipient=trim(implode(' ',array_filter([
            $students[0]['recipient_name']??'',
            $students[0]['recipient_position']??'',
            $document['company_name']??'',
        ])));
        if($sendRecipient==='')$sendRecipient=trim((string)($document['recipient_name']??''));
        $sendBodyX=$marginLeft;$sendBodyWidth=210-$marginLeft-$marginRight;
        $pdf->SetXY($sendBodyX,88);$pdf->Cell(20,6,'เรื่อง');$pdf->SetXY($sendBodyX+11,88);$pdf->Cell($sendBodyWidth-11,6,'ขอส่งตัวนักศึกษาฝึกประสบการณ์วิชาชีพ');
        $pdf->SetXY($sendBodyX,97);$pdf->Cell(20,6,'เรียน');$pdf->SetXY($sendBodyX+11,97);$pdf->Cell($sendBodyWidth-11,6,officialThaiDigits($sendRecipient));
        $pdf->SetXY($sendBodyX,106);$pdf->Cell(20,6,'อ้างถึง');$pdf->SetXY($sendBodyX+11,106);$pdf->Cell($sendBodyWidth-11,6,officialThaiDigits((string)($document['reference_text']?:'แบบตอบรับนักศึกษา')));
        $attachmentLabel='สิ่งที่ส่งมาด้วย';
        $attachmentTitle='ขั้นตอนการประเมินนักศึกษาในระบบฝึกประสบการณ์วิชาชีพ';
        $attachmentCount='จำนวน ๑ ฉบับ';
        // Use fixed columns rather than calculated text widths.  TCPDF's Thai
        // glyph widths can make the former columns collide or appear clipped
        // in some PDF viewers.
        $attachmentTitleX=$sendBodyX+27;
        // Keep the quantity visually attached to the attachment title, as in
        // the Word original, rather than pushing it to the far right margin.
        $attachmentCountX=147;
        $pdf->SetXY($sendBodyX,115);$pdf->Cell(25,6,$attachmentLabel);
        $pdf->SetXY($attachmentTitleX,115);$pdf->Cell(110,6,$attachmentTitle,0,0,'L');
        $pdf->SetXY($attachmentCountX,115);$pdf->Cell(30,6,$attachmentCount,0,0,'L');
        $indent=str_repeat(' ',18);
        $companyInline=$company;
        if($count===1){
            $firstParagraph="ตามหนังสือที่อ้างถึง $companyInline ได้ตอบรับนักศึกษา คณะวิศวกรรมศาสตร์และเทคโนโลยี $department เข้าฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end จำนวน $countThai คน นั้น คณะขอส่งนักศึกษา ราย ".officialStudentNames($students,true).' มารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ';
        }else{
            $firstParagraph="ตามหนังสือที่อ้างถึง $companyInline ได้ตอบรับนักศึกษา คณะวิศวกรรมศาสตร์และเทคโนโลยี $department เข้าฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end จำนวน $countThai คน นั้น คณะขอส่งนักศึกษามารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ ดังรายชื่อต่อไปนี้";
        }
        $secondParagraph='ทั้งนี้ ขอให้หน่วยงานของท่านประเมินผลการปฏิบัติงานนักศึกษา ตาม QR - Code ด้านล่าง'
            .'หลังจากที่นักศึกษาปฏิบัติงานเสร็จสิ้นตามระยะเวลาที่กำหนด (โดยรหัสผ่านในการเข้าระบบ จะแจ้งไปยังอีเมล'
            .'พนักงานที่ปรึกษาของนักศึกษา เพื่อดำเนินการประเมินผลปฏิบัติงานนักศึกษา ตามขั้นตอนการประเมินนักศึกษา'
            .'ดังเอกสารแนบท้าย) และขอความอนุเคราะห์หน่วยงานของท่าน ออกหนังสือรับรองการปฏิบัติงานให้แก่นักศึกษา'
            .'เพื่อใช้เป็นหลักฐานแสดงการฝึกประสบการณ์วิชาชีพต่อไป';
        if($compactFivePreview){
            $compactLead="ตามหนังสือที่อ้างถึง $companyInline ได้ตอบรับนักศึกษา คณะวิศวกรรมศาสตร์และเทคโนโลยี $department เข้าฝึกประสบการณ์วิชาชีพ ภาคการศึกษาที่ $semester ปีการศึกษา $academic ระหว่างวันที่ $start ถึงวันที่ $end จำนวน $countThai คน นั้น คณะขอส่งนักศึกษามารายงานตัว เพื่อเข้าฝึกประสบการณ์วิชาชีพ ดังรายชื่อต่อไปนี้";
            // Keep the five-student letter on one page without making the body
            // look compressed. The standard 16 pt font is retained while the
            // usable text width and vertical gaps follow the original letter.
            $compactLineHeight=5.7;
            $bodyX=29;
            $bodyWidth=166;
            officialWriteJustifiedText($pdf,$compactLead,$bodyX,121.5,$bodyWidth,$compactLineHeight,20,[$companyInline,$start,$end],true);
            $listY=$pdf->GetY()+1.4;
            foreach($students as $studentIndex=>$student){
                $column=$studentIndex%2;$row=(int)floor($studentIndex/2);
                $listX=$column===0?42:116;
                $name=officialThaiDigits(($studentIndex+1).'.  '.trim(($student['prefix_short']??'').$student['firstname'].' '.$student['lastname']));
                $pdf->SetXY($listX,$listY+($row*$compactLineHeight));$pdf->Cell(73,$compactLineHeight,$name,0,0,'L');
            }
            $pdf->SetY($listY+(ceil($count/2)*$compactLineHeight)+1.8);
            officialWriteJustifiedText($pdf,$secondParagraph,$bodyX,$pdf->GetY(),$bodyWidth,$compactLineHeight,20,['QR - Code'],true);
            officialWriteJustifiedText($pdf,'จึงเรียนมาเพื่อโปรดทราบ และขอขอบคุณในความอนุเคราะห์ด้วยดีมา ณ โอกาสนี้',$bodyX,$pdf->GetY()+1.4,$bodyWidth,$compactLineHeight,20,['และขอขอบคุณในความอนุเคราะห์ด้วยดีมา ณ โอกาสนี้']);
        }else{
            // Match the request-letter layout: first-line indent, balanced text
            // width, and a separate closing paragraph.
            // Leave Thai characters enough vertical breathing room.  The former
            // 5.7 mm leading made long send-letter paragraphs look compressed
            // even though the text itself was not stretched horizontally.
            // Keep every generated line safely inside the 3 cm / 2 cm paper
            // margins, including viewers that render Thai glyph overhangs.
            // Match the Word source: the first body line begins 126.75 mm
            // from the top edge, while the paragraph remains within the
            // official 3 cm / 2 cm horizontal margins.
            // The approved send-letter layout uses a 29 mm left edge and a
            // 166 mm text measure.  The former 30/160 mm measure wrapped
            // Thai prose too early, unlike the Word reference.
            $bodyX=29.0;$bodyWidth=166.0;$bodyLineHeight=6.25;
            // The Word original uses a clear first-line indent for the formal
            // “ตามหนังสือที่อ้างถึง” paragraph; subsequent lines return to
            // the normal left text edge.
            $bodyIndent=25;
            // Start just below the attachment row.  This leaves enough room
            // for the closing and signature block without creating page 2.
            officialWriteJustifiedText($pdf,$firstParagraph,$bodyX,125.5,$bodyWidth,$bodyLineHeight,$bodyIndent,[
            $companyInline,$department,$start,$end,
            ],true);
            if($count>1){
                // One to three names follow the Word template as a centred
                // single-column list.  Longer groups use the compact branch
                // above, where two columns are necessary to keep one page.
                $listY=$pdf->GetY()+1.5;
                foreach($students as $studentIndex=>$student){
                    $name=officialThaiDigits(($studentIndex+1).'.  '.trim(($student['prefix_short']??'').$student['firstname'].' '.$student['lastname']));
                    $pdf->SetXY(54.2,$listY+($studentIndex*$bodyLineHeight));
                    $pdf->Cell(120,$bodyLineHeight,$name,0,0,'L');
                }
                $pdf->SetY($listY+($count*$bodyLineHeight)+0.8);
            }
            // Keep paragraphs visually distinct without creating a large gap.
            $y=$pdf->GetY()+2.2;
            officialWriteJustifiedText($pdf,$secondParagraph,$bodyX,$y,$bodyWidth,$bodyLineHeight,$bodyIndent,[
                'QR - Code',
            ],true);
            // Preserve the Word reference's breathing room between the final
            // body paragraph and the formal closing line.
            $y=$pdf->GetY()+4.4;
            officialWriteJustifiedText($pdf,'จึงเรียนมาเพื่อโปรดทราบ และขอขอบคุณในความอนุเคราะห์ด้วยดีมา ณ โอกาสนี้',$bodyX,$y,$bodyWidth,$bodyLineHeight,$bodyIndent,[
                'และขอขอบคุณในความอนุเคราะห์ด้วยดีมา ณ โอกาสนี้',
            ]);
        }
    }
    // Keep the signature block in the same region as the retained Word
    // templates.  A request letter may include several student rows, so use
    // the template's normal position unless its variable body needs more room.
    $requestRespectY = $isRequest ? max(209.0, $pdf->GetY() + 4.0) : 0.0;
    // The amount of text in a send letter changes with the company and
    // student name.  Its signature must follow the final closing line rather
    // than stay at one fixed Y coordinate; otherwise the two lines overlap.
    $respectY=$isRequest
        ? $requestRespectY
        : ($multiStudentLayout ? max(214.0, $pdf->GetY()+4.0) : max(197.0, $pdf->GetY()+3.5));
    $signY = $isRequest ? $requestRespectY + 3.0 : $respectY + 7.5;
    $signatureCenterX=132;
    $respectCenterX=$isRequest?129.8:$signatureCenterX;
    $pdf->SetXY($respectCenterX-40,$respectY);$pdf->Cell(80,6,'ขอแสดงความนับถือ',0,0,'C');
    $signatureDetailY=$signY;
    $pdf->SetXY($signatureCenterX-49,$signatureDetailY+16);$pdf->Cell(98,6,officialThaiDigits('('.$document['signatory_name'].')'),0,0,'C');
    // Reserve a practical signing gap, then keep every title on its own
    // baseline.  The old send-letter gap was too large after a variable body
    // and its 3.5 mm title leading could visually collide in Thai.
    $titleLineGap=6.4;
    $pdf->SetXY($signatureCenterX-53,$signatureDetailY+16+$titleLineGap);$pdf->Cell(106,6,officialThaiDigits((string)$document['signatory_title_line1']),0,0,'C');
    if(!empty($document['signatory_title_line2'])){$pdf->SetXY($signatureCenterX-53,$signatureDetailY+16+($titleLineGap*2));$pdf->Cell(106,6,officialThaiDigits((string)$document['signatory_title_line2']),0,0,'C');}
    if($isRequest){
        $pdf->SetFont($thaiFont,'',16);$pdf->SetXY(29,255.5);
        $pdf->MultiCell(161,6.4,"แผนกงานวิชาการและวิจัย\nโทรศัพท์ ๐ ๔๔๒๓ ๓๐๗๓  อีเมล FET@rmuti.ac.th",0,'L');
    }elseif($sendQrCodePath!==null || $stepsQrCodePath!==null){
        // The approved send letter contains two equal QR blocks.  Place each
        // caption directly below its QR, with a common baseline, so neither
        // code nor its four-line explanation can be clipped in the PDF path.
        // Match the approved Word/PDF layout: for multi-student letters the
        // top of both QR codes aligns with the opening parenthesis before the
        // signatory name.  Keep the one-student position unchanged.
        $sendQrY=$multiStudentLayout?237.2:222.0;
        $qrSize=20.0;
        $assessmentQrX=30.0;
        $stepsQrX=70.0;
        if($sendQrCodePath!==null)$pdf->Image($sendQrCodePath,$assessmentQrX,$sendQrY,$qrSize,$qrSize,'JPG');
        if($stepsQrCodePath!==null)$pdf->Image($stepsQrCodePath,$stepsQrX,$sendQrY,$qrSize,$qrSize,'JPG');
        $pdf->SetFont($thaiFont,'',10);
        $captionY=$sendQrY+$qrSize+1.2;
        if($sendQrCodePath!==null){$pdf->SetXY($assessmentQrX,$captionY);$pdf->MultiCell(34,3.45,officialPdfQrCaptionLines($document,'assessment_qr_caption'),0,'L');}
        if($stepsQrCodePath!==null){$pdf->SetXY($stepsQrX,$captionY);$pdf->MultiCell(34,3.45,officialPdfQrCaptionLines($document,'steps_qr_caption'),0,'L');}
        $contact=(string)($document['_template_footer_contact'] ?? 'แผนกงานวิชาการและวิจัย โทรศัพท์ ๐ ๔๔๒๓ ๓๐๐๐ ต่อ ๓๑๐๐ อีเมล FET@rmuti.ac.th');
        $pdf->SetFont($thaiFont,'',16);$pdf->SetXY(30,$captionY+16.5);
        $pdf->Cell(165,6,officialThaiDigits($contact),0,0,'L');
    }
    return $pdf;
}
