<?php
session_start();
require_once '../middleware/staff.php';
require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/internship_supervision_helper.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: supervision.php'); exit; }
verify_csrf_or_abort();
$staff = supervisionEmployee($pdo, (int)$_SESSION['user_id'], 'staff');
if (!$staff) { http_response_code(403); renderPlainErrorAlert('ไม่มีสิทธิ์'); }

function importNormalize(string $value): string { return mb_strtolower(preg_replace('/\s+/u', '', trim($value)), 'UTF-8'); }
function importDate($value): ?string {
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
    $text=trim((string)$value);
    if(preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/',$text,$m)){$year=(int)$m[3];if($year>2400)$year-=543;return sprintf('%04d-%02d-%02d',$year,(int)$m[2],(int)$m[1]);}
    $time=strtotime($text); return $time ? date('Y-m-d',$time) : null;
}
function importTime($value): ?string {
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) {$seconds=(int)round(((float)$value-floor((float)$value))*86400);return sprintf('%02d:%02d',intdiv($seconds,3600)%24,intdiv($seconds%3600,60));}
    return preg_match('/^(\d{1,2}):(\d{2})/',trim((string)$value),$m) ? sprintf('%02d:%02d',(int)$m[1],(int)$m[2]) : null;
}
function importMode($value): ?string {$value=mb_strtolower(trim((string)$value),'UTF-8');if(in_array($value,['onsite','on-site','ออนไซต์'],true))return 'Onsite';if(in_array($value,['online','on-line','ออนไลน์'],true))return 'Online';return null;}

try {
    $file=$_FILES['excel_file']??null;
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('กรุณาเลือกไฟล์ Excel ที่อาจารย์กรอกแล้ว');
    if(($file['size']??0)>10*1024*1024)throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
    if(mb_strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION))!=='xlsx')throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx');
    $book=IOFactory::load($file['tmp_name']);$sheet=null;
    foreach($book->getWorksheetIterator() as $candidate)if(str_starts_with($candidate->getTitle(),'แผนนิเทศ')){$sheet=$candidate;break;}
    if(!$sheet)throw new RuntimeException('ไม่พบชีตที่ชื่อขึ้นต้นด้วย “แผนนิเทศ”');
    $rows=$sheet->toArray(null,true,true,false);if(!$rows)throw new RuntimeException('ชีตแผนนิเทศไม่มีข้อมูล');
    $headers=array_map(static fn($v)=>trim((string)$v),array_shift($rows));$index=array_flip($headers);
    foreach(['รายชื่ออาจารย์นิเทศ','วันที่นิเทศ ครั้งที่ 1','วันที่นิเทศ ครั้งที่ 2'] as $required)if(!isset($index[$required]))throw new RuntimeException('รูปแบบชีตไม่ถูกต้อง: ไม่พบคอลัมน์ '.$required);
    $teachers=supervisionTeachers($pdo,(int)$staff['department_id']);$teacherMap=[];
    foreach($teachers as $teacher){$name=trim($teacher['prefix_name'].$teacher['firstname'].' '.$teacher['lastname']);$teacherMap[importNormalize($name)]=(int)$teacher['employee_id'];}
    $profiles=[];$profilesByStudent=[];$profilesByNameCompany=[];
    foreach(supervisionRows($pdo,(int)$staff['department_id']) as $row){
        $profileId=(int)$row['internship_profile_id'];$profiles[$profileId]=true;
        $profilesByStudent[trim((string)$row['student_number'])]=$profileId;
        $studentName=trim(($row['prefix_short']??'').$row['firstname'].' '.$row['lastname']);
        $profilesByNameCompany[importNormalize($studentName).'|'.importNormalize((string)$row['company_name'])]=$profileId;
    }
    $updated=0;$warnings=[];$pdo->beginTransaction();
    foreach($rows as $rowNo=>$row){$get=static fn(string $name)=>$row[$index[$name]??-1]??null;$profileId=(int)$get('รหัสข้อมูลระบบ (ห้ามแก้)');if(!$profileId||!isset($profiles[$profileId]))$profileId=$profilesByStudent[trim((string)$get('รหัสนักศึกษา'))]??0;if(!$profileId){$key=importNormalize((string)$get('ชื่อ - สกุล นักศึกษา')).'|'.importNormalize((string)$get('ชื่อสถานประกอบการ'));$profileId=$profilesByNameCompany[$key]??0;}if(!$profileId){$warnings[]='แถว '.($rowNo+2).' ไม่พบข้อมูลนักศึกษา';continue;}$teacherName=trim((string)$get('รายชื่ออาจารย์นิเทศ'));$teacherId=$teacherName!==''?($teacherMap[importNormalize($teacherName)]??0):0;if($teacherName!==''&&!$teacherId){$warnings[]='แถว '.($rowNo+2).' ไม่พบอาจารย์ “'.$teacherName.'”';continue;}saveSupervision($pdo,$staff,['profile_id'=>$profileId,'supervisor_employee_id'=>$teacherId,'visit1_date'=>importDate($get('วันที่นิเทศ ครั้งที่ 1')),'visit1_time'=>importTime($get('เวลานิเทศ ครั้งที่ 1')),'visit1_mode'=>importMode($get('รูปแบบครั้งที่ 1')),'visit2_date'=>importDate($get('วันที่นิเทศ ครั้งที่ 2')),'visit2_time'=>importTime($get('เวลานิเทศ ครั้งที่ 2')),'visit2_mode'=>importMode($get('รูปแบบครั้งที่ 2'))]);$updated++;}
    $pdo->commit();$query=['imported'=>$updated];if($warnings)$query['warning']=implode(' | ',array_slice(array_unique($warnings),0,5));header('Location: supervision.php?'.http_build_query($query));exit;
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();header('Location: supervision.php?import_error='.rawurlencode($e->getMessage()));exit;}
