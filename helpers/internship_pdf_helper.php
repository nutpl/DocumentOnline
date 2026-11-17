<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/pdf_font_helper.php';

use setasign\Fpdi\Tcpdf\Fpdi;

function thaiDateParts(string $date): array
{
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $d = new DateTimeImmutable($date ?: 'now');
    return [$d->format('j'), $months[(int)$d->format('n')], (string)((int)$d->format('Y') + 543)];
}

function createInternshipApplicationPdf(array $row): Fpdi
{
    $source = realpath(__DIR__ . '/../uploads/pdf/RMUTI_Pro-Ex_02-clean.pdf');
    if (!$source) { throw new RuntimeException('ไม่พบต้นฉบับ RMUTI_Pro-Ex_02'); }
    $pdf = new Fpdi('P','mm','A4',true,'UTF-8',false);
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetMargins(0,0,0); $pdf->SetAutoPageBreak(false);
    $pdf->setSourceFile($source); $tpl = $pdf->importPage(1); $pdf->AddPage('P','A4'); $pdf->useTemplate($tpl,0,0,210,297);
    $thaiFont = thSarabunPskFont();
    $thaiItalicFont = thSarabunPskFont('I');
    $pdf->SetTextColor(20,55,130); $pdf->SetFont($thaiFont,'',9);
    $pdf->SetFillColor(255,255,255); $pdf->Rect(125.4,18.2,3.6,3.8,'F');
    $pdf->SetDrawColor(20,20,20); $pdf->Rect(125.4,18.2,3.6,3.8); $pdf->SetTextColor(20,55,130); $pdf->SetFont('dejavusans','B',9); $pdf->SetXY(125.2,17.3); $pdf->Cell(4,5,'✓');

    $field = static function(float $x,float $y,float $w,string $text,float $size=8,bool $line=true,float $lift=2.5) use ($pdf, $thaiFont): void {
        // The cleaned template already contains the official rules. Drawing them again causes
        // double lines and can cover adjacent labels, so only place the field value.
        $pdf->SetTextColor(20,55,130); $pdf->SetFont($thaiFont,'',11); $pdf->SetXY($x+0.4,$y-$lift); $pdf->Cell($w-0.8,4.2,$text,0,0,'L',false,'',1,false,'T','M');
    };
    [$day,$month,$year] = thaiDateParts($row['request_date'] ?? date('Y-m-d'));
    $field(126,50.0,8,$day,8,true,2.0); $field(145,50.0,24,$month,8,true,2.0); $field(187,50.0,8,$year,8,true,2.0);
    $studentFirstName=trim(($row['prefix_short']??'').($row['firstname']??''));
    $facultyName=preg_replace('/^\s*คณะ\s*/u','',(string)($row['faculty_name']??''));
    $field(27,64.0,31,$studentFirstName); $field(77,64.0,29,(string)$row['lastname']); $field(137,64.0,32,(string)$row['student_number']); $field(181,64.0,12,(string)$row['year_level']);
    $field(31,70.0,65,(string)$row['department_name'],7); $field(108,70.0,46,$facultyName,7); $field(170,70.0,24,(string)$row['student_phone'],7);
    $field(101,76.0,8,(string)$row['semester']);
    $trainingPeriod = '';
    if (!empty($row['start_date']) && !empty($row['end_date'])) {
        [$sd,$sm,$sy] = thaiDateParts((string)$row['start_date']);
        [$ed,$em,$ey] = thaiDateParts((string)$row['end_date']);
        $trainingPeriod = "$sd $sm $sy ถึง $ed $em $ey";
    }
    $field(126,76.0,66,$trainingPeriod,7);

    $checks = [['qualification_department',31.3,87.0],['qualification_preparation',31.3,93.0],['qualification_gpa',31.3,99.0],['qualification_company',31.3,105.0]];
    foreach ($checks as [$name,$x,$y]) { if (!empty($row[$name])) { $pdf->SetDrawColor(20,55,130); $pdf->SetLineWidth(0.35); $pdf->Line($x+0.55,$y+0.35,$x+1.3,$y+1.1); $pdf->Line($x+1.3,$y+1.1,$x+2.85,$y-0.8); } }

    $field(75,120.0,36,(string)$row['recipient_name'],8); $field(124,120.0,29,(string)$row['recipient_position'],8); $field(169,120.0,25,(string)$row['recipient_department'],8);
    $field(42,126.3,99,(string)$row['company_name'],7); $field(154,126.3,11,(string)$row['company_number'],7); $field(179,126.3,13,(string)$row['company_moo'],7);
    $field(34,132.3,28,(string)$row['company_building'],7); $field(69,132.3,12,(string)$row['company_floor'],7); $field(82,132.3,35,(string)$row['company_road'],7); $field(119,132.3,32,(string)$row['company_alley'],7); $field(162,132.3,31,(string)$row['subdistrict'],7);
    $field(40,138.3,31,(string)$row['district'],7); $field(101,138.3,35,(string)$row['province'],7); $field(164,138.3,22,(string)$row['postal_code'],7);
    $field(36,144.3,27,(string)$row['company_phone'],7); $field(76,144.3,25,(string)$row['company_fax'],7); $field(126,144.3,67,(string)$row['company_email'],7);
    $field(145,150.4,48,trim(($row['contact_prefix'] ?? '').' '.($row['contact_name'] ?? '')),8);
    $field(36,156.5,35,(string)$row['contact_position'],7); $field(105,156.5,45,(string)$row['contact_department'],7);
    $field(36,162.5,36,(string)$row['contact_phone'],7); $field(108,162.5,84,(string)$row['contact_email'],7);

    // Keep the signature line blank, but place the authenticated student's printed
    // name once inside the original parenthesized line without redrawing any rules.
    $studentFullName=trim(($row['prefix_short']??'').$row['firstname'].' '.$row['lastname']);
    $printedName=$studentFullName;
    $pdf->SetTextColor(20,55,130);$pdf->SetFont($thaiFont,'',11);$pdf->SetXY(116,176.1);$pdf->Cell(48,4.2,$printedName,0,0,'C',false,'',1);
    $coordinatorName=trim((string)($row['coordinator_name']??''));
    if($coordinatorName!==''){$pdf->SetTextColor(20,55,130);$pdf->SetFont($thaiFont,'',11);$pdf->SetXY(122,210);$pdf->Cell(54,4.2,$coordinatorName,0,0,'C',false,'',1);}
    $advisorName=trim((string)($row['advisor_name']??''));
    if($advisorName!==''){$pdf->SetTextColor(20,55,130);$pdf->SetFont($thaiFont,'',11);$pdf->SetXY(34,210);$pdf->Cell(54,4.2,$advisorName,0,0,'C',false,'',1);}
    return $pdf;
}
