<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/simple_xlsx_writer.php';

function internshipExportThaiDate(?string $date): string
{
    if (!$date) return '';
    $months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $d=new DateTimeImmutable($date);
    return (int)$d->format('j').' '.$months[(int)$d->format('n')].' '.((int)$d->format('Y')+543);
}

$stmt=$pdo->prepare('SELECT e.department_id,d.department_name FROM employee e JOIN department d ON d.department_id=e.department_id WHERE e.user_id=? AND e.employee_type IN ("staff","teacher") LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$staff=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$staff){http_response_code(403);renderPlainErrorAlert('ไม่มีสิทธิ์');}

$stmt=$pdo->prepare('SELECT ip.*,ic.academic_year,ic.semester,ic.start_date,ic.end_date,s.student_number,s.firstname,s.lastname,p.prefix_short,g.group_name,d.department_name,f.faculty_name,
    (SELECT CONCAT(COALESCE(cp.prefix_short,cp.prefix_name,""),ce.firstname," ",ce.lastname)
        FROM employee ce
        LEFT JOIN prefixes cp ON cp.prefix_id=ce.prefix_id
        JOIN employee_position cep ON cep.employee_id=ce.employee_id
        JOIN positions cpos ON cpos.position_id=cep.position_id
        WHERE ce.department_id=d.department_id
          AND cpos.position_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ"
        ORDER BY ce.employee_id LIMIT 1) AS coop_coordinator_name,
    isu.visit1_date,isu.visit1_time,isu.visit1_mode,isu.visit2_date,isu.visit2_time,isu.visit2_mode,
    COALESCE(CONCAT(COALESCE(sup_prefix.prefix_short,sup_prefix.prefix_name,""),sup.firstname," ",sup.lastname),isu.supervisor_names) AS supervisor_names,
    CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios
        JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id
        WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="request") THEN "sent" ELSE NULL END AS request_letter_status,
    rd.review_status AS response_review_status,it.application_status,
    CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios
        JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id
        WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="send") THEN "sent" ELSE NULL END AS send_letter_status,
    it.employment_status,it.notes AS tracking_notes
    FROM internship_profile ip
    JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
    JOIN student s ON s.student_id=ip.student_id
    LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
    JOIN group_class g ON g.group_id=s.group_id
    JOIN department d ON d.department_id=g.department_id
    LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
    LEFT JOIN internship_supervision isu ON isu.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_tracking it ON it.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_response_document rd ON rd.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id
    LEFT JOIN internship_direct_send_import direct_import ON direct_import.internship_profile_id=ip.internship_profile_id
    LEFT JOIN employee sup ON sup.employee_id=isu.supervisor_employee_id
    LEFT JOIN prefixes sup_prefix ON sup_prefix.prefix_id=sup.prefix_id
    WHERE ip.request_status="active"
      AND g.department_id=?
      AND (ia.status="submitted" OR direct_import.internship_profile_id IS NOT NULL)
    ORDER BY ip.company_name,s.student_number');
$stmt->execute([$staff['department_id']]);
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

$facultyName=preg_replace('/^\s*คณะ\s*/u','',(string)($data[0]['faculty_name']??''));
$departmentName=preg_replace('/^\s*สาขาวิชา\s*/u','',(string)($data[0]['department_name']??$staff['department_name']??''));
$groups=array_values(array_unique(array_filter(array_column($data,'group_name'))));
$advisors=array_values(array_unique(array_filter(array_column($data,'coop_coordinator_name'))));
$periods=[];
foreach($data as $row){
    if(!empty($row['start_date'])||!empty($row['end_date'])){
        $period='วันที่ '.internshipExportThaiDate($row['start_date']??null).' ถึงวันที่ '.internshipExportThaiDate($row['end_date']??null);
        $periods[$period]=true;
    }
}

$tracking=[
    array_fill(0,12,''),
    ['คณะ'.$facultyName.' สาขา'.$departmentName,'','','','','','','','','','',''],
    ['กลุ่มเรียน '.($groups?implode(', ',$groups):'-'),'','','','','','','','','','',''],
    ['อาจารย์ที่ปรึกษา '.($advisors?implode(', ',$advisors):'-'),'','','','','','','','','','',''],
    ['ระยะเวลาปฏิบัติงาน '.($periods?implode(', ',array_keys($periods)):'-'),'','','','','','','','','','',''],
    ['ลำดับ','รหัสนักศึกษา','ชื่อ-สกุล','เบอร์โทรศัพท์','บริษัท','หนังสือสอบถาม','หนังสือตอบรับ','','สมัครงานในระบบ','หนังสือส่งตัว','หมายเหตุ','รับเข้าทำงาน'],
    ['','','','','','','รับเข้าฝึก','ไม่รับเข้าฝึก','CWIE','','',''],
];
$supervision=[['สาขาวิชา','รหัสนักศึกษา','ชื่อ - สกุล นักศึกษา','ชื่อสถานประกอบการ','วันที่นิเทศ ครั้งที่ 1','เวลานิเทศ ครั้งที่ 1','รูปแบบครั้งที่ 1','วันที่นิเทศ ครั้งที่ 2','เวลานิเทศ ครั้งที่ 2','รูปแบบครั้งที่ 2','รายชื่ออาจารย์นิเทศ']];
$directory=[['ลำดับ','ชื่อ-สกุลนักศึกษา','ชื่อสถานประกอบการ','ชื่อพี่เลี้ยง / ผู้ดูแล','ที่อยู่บริษัท','ตำบล/แขวง','อำเภอ/เขต','จังหวัด','รหัสไปรษณีย์','โทรศัพท์','โทรสาร','ลักษณะการดำเนินงาน','จำนวนการรับนักศึกษา']];

foreach($data as $i=>$r){
    $name=trim(($r['prefix_short']??'').$r['firstname'].' '.$r['lastname']);
    $address=trim(implode(' ',array_filter([$r['company_number']??'',!empty($r['company_moo'])?'หมู่ '.$r['company_moo']:'',$r['company_building']??'',$r['company_road']??'',$r['company_alley']??''])));
    $markRequest=($r['request_letter_status']??'')==='sent'?'/':'';
    $markApplication=($r['application_status']??'')==='applied'?'/':'';
    $markSend=($r['send_letter_status']??'')==='sent'?'/':'';
    $responseSent=($r['response_review_status']??'')==='approved'?'/':'';
    $responseNotSent=($r['response_review_status']??'')==='not_accepted'?'/':'';
    $hired=($r['employment_status']??'')==='hired'?'/':'';
    $tracking[]=[$i+1,$r['student_number'],$name,$r['student_phone'],$r['company_name'],$markRequest,$responseSent,$responseNotSent,$markApplication,$markSend,$r['tracking_notes']??'',$hired];
    $supervision[]=[$r['department_name'],$r['student_number'],$name,$r['company_name'],$r['visit1_date'],$r['visit1_time'],$r['visit1_mode'],$r['visit2_date'],$r['visit2_time'],$r['visit2_mode'],$r['supervisor_names']];
    $directory[]=[$i+1,$name,$r['company_name'],trim(($r['contact_prefix']??'').($r['contact_name']??'')),$address,$r['subdistrict'],$r['district'],$r['province'],$r['postal_code'],$r['company_phone'],$r['company_fax'],$r['job_description'],$r['capacity_note']];
}

$semester=(string)($data[0]['semester']??'');
$academicYear=(string)($data[0]['academic_year']??'');
$academicShort=$academicYear!==''?substr($academicYear,-2):'';
$supervisionName='แผนนิเทศ'.($semester!==''?' '.$semester:'').($academicShort!==''?'-'.$academicShort:'').'CPE.';

$sheets=[
    [
        'name'=>'สหกิจ CPE','rows'=>$tracking,'widths'=>[8,18,28,18,40,17,13,13,18,16,24,18],
        'metadata_rows'=>[2,3,4,5],'header_rows'=>[6,7],'freeze_rows'=>7,'plain_rows'=>[1],
        'data_style'=>5,
        'merges'=>['A2:L2','A3:L3','A4:L4','A5:L5','A6:A7','B6:B7','C6:C7','D6:D7','E6:E7','F6:F7','G6:H6','I6:I7','J6:J7','K6:K7','L6:L7'],
    ],
    ['name'=>$supervisionName,'rows'=>$supervision,'widths'=>[24,18,28,38,18,16,18,18,16,18,32],'header_rows'=>[1],'freeze_rows'=>1,'auto_filter'=>'A1:K'.count($supervision),'data_style'=>5],
    ['name'=>'ทำเนียบสถานประกอบการ','rows'=>$directory,'widths'=>[8,28,38,30,42,18,20,18,14,18,18,48,24],'header_rows'=>[1],'freeze_rows'=>1,'auto_filter'=>'A1:M'.count($directory),'data_style'=>5],
];
SimpleXlsxWriter::output($sheets,'ข้อมูลสหกิจ-CPE.xlsx');
