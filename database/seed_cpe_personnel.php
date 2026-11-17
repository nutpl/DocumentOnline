<?php
// Run once with: php database/seed_cpe_personnel.php
// Creates CPE personnel accounts with no password; staff set their password at first login.
require_once __DIR__ . '/../config/database.php';

$prefixes = [
    ['นาย', 'นาย'], ['นาง', 'นาง'], ['นางสาว', 'นางสาว'],
    ['ผู้ช่วยศาสตราจารย์', 'ผศ.'], ['ผู้ช่วยศาสตราจารย์ ดร.', 'ผศ.ดร.'], ['ดร.', 'ดร.'],
];
$positions = ['หัวหน้าสาขาวิชาวิศวกรรมคอมพิวเตอร์', 'อาจารย์ประจำสาขา', 'อาจารย์กิจกรรม', 'ประธานหลักสูตร', 'ธุรการประจำสาขา'];
$people = [
    ['CPE-001','นาย','สนั่น','จันทร์พรม','sanan@rmuti.ac.th','teacher','หัวหน้าสาขาวิชาวิศวกรรมคอมพิวเตอร์'],
    ['CPE-002','ผศ.','วิทยา','ศรีกุล','srigul@hotmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-003','ผศ.','ธรรมกร','ครองไตรภพ','thammakorn@rmuti.ac.th','teacher','อาจารย์ประจำสาขา'],
    ['CPE-004','นาย','ชิติสรรค์','วิชิโต','ch_wichito@hotmail.com','teacher','อาจารย์กิจกรรม'],
    ['CPE-005','ผศ.ดร.','วุฒิพงษ์','พิชิตวงศ์','wudthipong@hotmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-006','นาย','ประกาย','นาดี','prakai@rmuti.ac.th','teacher','อาจารย์ประจำสาขา'],
    ['CPE-007','นางสาว','สุภัทรา','เกิดเมฆ','kerdmec_sk@hotmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-008','นาง','ทิพา','กองศรีมา','kongsrimatipa@gmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-009','ดร.','เกตุกาญจน์','โพธิจิตติกานต์','kedkarnc@hotmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-010','ผศ.','อภิชาต','ติรประเสริฐสิน','apichatter@yahoo.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-011','นาย','พุทธาวุฒิ','ลีกุลธร','cropw@hotmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-012','ผศ.','พรภัสสร','อ่อนเกิด','pornpassorn@hotmail.com','teacher','ประธานหลักสูตร'],
    ['CPE-013','ดร.','กีระชาติ','สุขสิทธิ์','mikaiterng@gmail.com','teacher','อาจารย์ประจำสาขา'],
    ['CPE-014','ดร.','รติพร','จันทร์กลั่น','ratiporn.ch@rmuti.ac.th','teacher','อาจารย์ประจำสาขา'],
    ['CPE-015','นาย','ปริญญา','กิตติสุทธิ์','parinya.ki@rmuti.ac.th','teacher','อาจารย์ประจำสาขา'],
    ['CPE-016','นางสาว','สุกัญญา','สังข์กลาง','sukanya.sn@rmuti.ac.th','staff','ธุรการประจำสาขา'],
];

try {
    $pdo->beginTransaction();
    $prefixInsert=$pdo->prepare("INSERT INTO prefixes (prefix_name,prefix_short,status) SELECT ?,?,'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_short=?)");
    foreach($prefixes as $prefix) $prefixInsert->execute([$prefix[0], $prefix[1], $prefix[1]]);
    $positionInsert=$pdo->prepare('INSERT IGNORE INTO positions (position_name) VALUES (?)');
    foreach($positions as $position) $positionInsert->execute([$position]);
    $prefixMap=$pdo->query('SELECT prefix_id,prefix_short FROM prefixes')->fetchAll(PDO::FETCH_KEY_PAIR);
    $positionMap=$pdo->query('SELECT position_id,position_name FROM positions')->fetchAll(PDO::FETCH_KEY_PAIR);
    $prefixByShort=array_flip($prefixMap); $positionByName=array_flip($positionMap);
    $existingUser=$pdo->prepare('SELECT user_id FROM users WHERE email=? OR username=? LIMIT 1');
    $userInsert=$pdo->prepare("INSERT INTO users (username,email,password,department_id,first_login,status) VALUES (?,?,?,1,0,'active')");
    $employeeInsert=$pdo->prepare('INSERT INTO employee (user_id,employee_code,prefix_id,firstname,lastname,email,department_id,employee_type) VALUES (?,?,?,?,?,?,1,?)');
    $employeeUpdate=$pdo->prepare('UPDATE employee SET prefix_id=?, firstname=?, lastname=?, email=?, employee_type=? WHERE employee_id=?');
    $positionInsertForEmployee=$pdo->prepare('INSERT INTO employee_position (employee_id,position_id,is_primary) VALUES (?,?,1)');
    $hasRole=$pdo->prepare('SELECT user_role_id FROM user_roles WHERE user_id=? AND role=?');
    $addRole=$pdo->prepare('INSERT INTO user_roles (user_id,role) VALUES (?,?)');
    foreach($people as [$code,$prefix,$first,$last,$email,$type,$position]) {
        $existingUser->execute([$email,$code]);
        $userId=$existingUser->fetchColumn();
        if(!$userId){$userInsert->execute([$code,$email,'pending-password']);$userId=(int)$pdo->lastInsertId();}
        $hasRole->execute([$userId,$type]);
        if(!$hasRole->fetchColumn()) $addRole->execute([$userId,$type]);
        $check=$pdo->prepare('SELECT employee_id FROM employee WHERE employee_code=?');$check->execute([$code]);$employeeId=$check->fetchColumn();
        if(!$employeeId){$employeeInsert->execute([$userId,$code,$prefixByShort[$prefix],$first,$last,$email,$type]);$employeeId=(int)$pdo->lastInsertId();}
        else {$employeeUpdate->execute([$prefixByShort[$prefix],$first,$last,$email,$type,$employeeId]);}
        $pdo->prepare('DELETE FROM employee_position WHERE employee_id=?')->execute([$employeeId]);
        $positionInsertForEmployee->execute([$employeeId,$positionByName[$position]]);
    }
    $pdo->commit();
    echo "Added or verified ".count($people)." CPE personnel records.\n";
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR, $e->getMessage()."\n"); exit(1);
}
