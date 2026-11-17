<?php
declare(strict_types=1);
// One-time real-data import, deliberately CLI-only. Default is a read-only preview.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../helpers/official_send_eligibility_helper.php';
$path=$argv[1]??'';
$apply=in_array('--apply',$argv,true);
if (!is_file($path)) { fwrite(STDERR,"Usage: php scripts/import_coop_2569_send.php workbook.xlsx [--apply]\n"); exit(1); }
function importText(mixed $value): string { return trim(preg_replace('/\s+/u',' ',(string)$value)); }
function importNameKey(string $value): string { return preg_replace('/\s+/u','',preg_replace('/^(นางสาว|นาย|นาง)\s*/u','',trim($value))); }
function importInsert(PDO $pdo,string $table,array $values): int {
    $stmt=$pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')');
    $stmt->execute(array_values($values)); return (int)$pdo->lastInsertId();
}
try {
    if (filesize($path)>10*1024*1024) throw new RuntimeException('Workbook exceeds 10 MB');
    $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx(); $reader->setReadDataOnly(true);
    $book=$reader->load($path); $sheet=$book->getSheetByName('สหกิจ CPE');
    if (!$sheet || trim((string)$sheet->getCell('B6')->getValue())!=='รหัสนักศึกษา') throw new RuntimeException('Unexpected workbook layout');
    $period=importText($sheet->getCell('A5')->getValue());
    // Accept only this specifically requested cohort; do not infer dates for other workbooks.
    if (!str_contains($period,'2 พฤศจิกายน 2569') || !str_contains($period,'19 กุมภาพันธ์ 2570')) throw new RuntimeException('Unexpected internship period: '.$period);
    $department=$pdo->query("SELECT department_id FROM department WHERE department_name='สาขาวิชาวิศวกรรมคอมพิวเตอร์'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($department)!==1) throw new RuntimeException('Computer engineering department is missing or ambiguous');
    $dept=(int)$department[0]; $prefixes=[];
    foreach ($pdo->query('SELECT prefix_id,prefix_short FROM prefixes') as $p) $prefixes[$p['prefix_short']]=(int)$p['prefix_id'];
    $directory=[]; $dir=$book->getSheetByName('ทำเนียบสถานประกอบการ');
    if ($dir) for($r=5;$r<=min(500,$dir->getHighestDataRow());$r++) {
        $name=importText($dir->getCell([2,$r])->getValue()); if ($name==='') continue;
        $key=importNameKey($name);
        if (isset($directory[$key])) throw new RuntimeException('Duplicate directory name: '.$name);
        $directory[$key]=[];
        foreach ([4=>'contact_name',5=>'company_address',6=>'subdistrict',7=>'district',8=>'province',9=>'postal_code',10=>'company_phone',11=>'company_fax',12=>'job_description'] as $col=>$field) {
            $value=importText($dir->getCell([$col,$r])->getValue());
            if (str_starts_with($value,'=')) throw new RuntimeException('Formula in directory source row '.$r);
            $directory[$key][$field]=$value!==''?$value:null;
        }
    }
    $rows=[]; $codes=[]; $hash=hash_file('sha256',$path); $already=0;
    for($r=8;$r<=min(500,$sheet->getHighestDataRow());$r++) {
        $code=importText($sheet->getCell([2,$r])->getValue()); if($code==='') continue;
        if (!preg_match('/^\d{11}-\d$/D',$code)||isset($codes[$code])) throw new RuntimeException('Invalid or duplicate student number at row '.$r);
        $codes[$code]=true;
        $name=importText($sheet->getCell([3,$r])->getValue()); $nameKey=importNameKey($name); $prefix=null;
        if(preg_match('/^(นางสาว|นาย|นาง)\s*/u',$name,$m)) { $prefix=$prefixes[$m[1]]??null; $name=trim(mb_substr($name,mb_strlen($m[0]))); }
        $parts=preg_split('/\s+/u',$name,2); $company=importText($sheet->getCell([5,$r])->getValue());
        if(count($parts)!==2 || $company==='' || mb_strlen($company)>255 || str_starts_with($company,'=')) throw new RuntimeException('Invalid name/company at row '.$r);
        $find=$pdo->prepare('SELECT s.student_id,s.firstname,s.lastname,g.department_id FROM student s LEFT JOIN group_class g ON g.group_id=s.group_id WHERE s.student_number=?'); $find->execute([$code]); $existing=$find->fetchAll(PDO::FETCH_ASSOC);
        if ($existing) {
            if (count($existing)!==1 || $existing[0]['firstname']!==$parts[0] || $existing[0]['lastname']!==$parts[1] || (int)$existing[0]['department_id']!==$dept) throw new RuntimeException('Existing student conflict: '.$code);
            $matched=false;
            if (officialDirectSendAvailable($pdo)) {
                $find=$pdo->prepare('SELECT COUNT(*) FROM internship_profile ip JOIN internship_direct_send_import imp ON imp.internship_profile_id=ip.internship_profile_id WHERE ip.student_id=? AND imp.source_sha256=? AND imp.source_row=?'); $find->execute([$existing[0]['student_id'],$hash,$r]); $matched=(bool)$find->fetchColumn();
            }
            if(!$matched) throw new RuntimeException('Existing student requires individual review; not overwritten: '.$code);
            $already++; continue;
        }
        $rows[]=['row'=>$r,'code'=>$code,'prefix'=>$prefix,'first'=>$parts[0],'last'=>$parts[1],'company'=>$company,'phone'=>importText($sheet->getCell([4,$r])->getValue()),'directory'=>$directory[$nameKey]??[]];
    }
    $book->disconnectWorksheets();
    if(count($codes)!==19) throw new RuntimeException('Expected 19 students in the supplied cohort; found '.count($codes));
    echo json_encode(['database'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),'department_id'=>$dept,'new'=>count($rows),'already_imported'=>$already,'dates'=>['2026-11-02','2027-02-19'],'missing_prefix'=>count(array_filter($rows,static fn($r)=>$r['prefix']===null))],JSON_UNESCAPED_UNICODE),"\n";
    if(!$apply) { echo "Preview only; no database changes. Add --apply to import.\n"; exit; }
    if(!$rows) { echo "Already imported; no changes.\n"; exit; }
    // Additive schema. All data changes below are atomic; no prior records are updated.
    $pdo->exec(file_get_contents(__DIR__.'/../database/migrations/20260915_direct_send_import.sql'));
    $pdo->beginTransaction();
    $groupName='CPE. (นำเข้า 2/2569)';
    $find=$pdo->prepare('SELECT group_id FROM group_class WHERE department_id=? AND group_name=?');$find->execute([$dept,$groupName]);$group=(int)$find->fetchColumn();
    if(!$group) $group=importInsert($pdo,'group_class',['department_id'=>$dept,'group_name'=>$groupName]);
    $find=$pdo->query("SELECT calendar_id FROM internship_calendar WHERE academic_year=2569 AND semester=2 AND start_date='2026-11-02' AND end_date='2027-02-19' LIMIT 1");$calendar=(int)$find->fetchColumn();
    if(!$calendar) $calendar=importInsert($pdo,'internship_calendar',['academic_year'=>2569,'semester'=>2,'start_date'=>'2026-11-02','end_date'=>'2027-02-19','status'=>'active']);
    $ids=[];
    foreach($rows as $r) {
        // No email/login details were supplied. Reserve an inactive account; never invent a university email.
        $uid=importInsert($pdo,'users',['username'=>'import_'.$r['code'],'email'=>'import_'.$r['code'].'@example.invalid','password'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'status'=>'inactive']);
        importInsert($pdo,'user_roles',['user_id'=>$uid,'role'=>'student']);
        $sid=importInsert($pdo,'student',['user_id'=>$uid,'student_number'=>$r['code'],'prefix_id'=>$r['prefix'],'firstname'=>$r['first'],'lastname'=>$r['last'],'group_id'=>$group]);
        $profile=importInsert($pdo,'internship_profile',[
            'student_id'=>$sid,'calendar_id'=>$calendar,'company_name'=>$r['company'],'student_phone'=>$r['phone'],
            ...$r['directory'],
        ]);
        importInsert($pdo,'internship_direct_send_import',['internship_profile_id'=>$profile,'source_filename'=>basename($path),'source_sha256'=>$hash,'source_row'=>$r['row'],'import_note'=>'นำเข้าข้อมูลจริงตามคำขอเจ้าของระบบเพื่อออกใบส่งตัวโดยตรง ไม่ได้สร้างคำร้อง ลายเซ็น หรือใบตอบรับย้อนหลัง']);
        $ids[]=$profile;
    }
    $pdo->commit(); echo json_encode(['imported'=>count($ids),'profile_ids'=>$ids,'group'=>$groupName],JSON_UNESCAPED_UNICODE),"\n";
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
