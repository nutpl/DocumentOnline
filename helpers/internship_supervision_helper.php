<?php

require_once __DIR__ . '/activity_log_helper.php';

function supervisionEmployee(PDO $pdo, int $userId, string $employeeType): array
{
    $stmt = $pdo->prepare('SELECT e.employee_id,e.user_id,e.department_id,e.employee_code,e.employee_type,e.firstname,e.lastname,
        COALESCE(pr.prefix_short,pr.prefix_name,"") AS prefix_name,d.department_name
        FROM employee e
        LEFT JOIN prefixes pr ON pr.prefix_id=e.prefix_id
        LEFT JOIN department d ON d.department_id=e.department_id
        WHERE e.user_id=?
          AND (e.employee_type=? OR (?="staff" AND e.employee_type="teacher"))
        LIMIT 1');
    $stmt->execute([$userId, $employeeType, $employeeType]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function supervisionTeachers(PDO $pdo, int $departmentId): array
{
    $stmt = $pdo->prepare('SELECT e.employee_id,e.firstname,e.lastname,
        COALESCE(pr.prefix_short,pr.prefix_name,"") AS prefix_name
        FROM employee e LEFT JOIN prefixes pr ON pr.prefix_id=e.prefix_id
        WHERE e.department_id=? AND e.employee_type="teacher" AND e.user_id IS NOT NULL
        ORDER BY e.firstname,e.lastname');
    $stmt->execute([$departmentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function supervisionRows(PDO $pdo, int $departmentId): array
{
    $stmt = $pdo->prepare('SELECT isu.*,ip.internship_profile_id,ip.company_name,ic.start_date,ic.end_date,s.student_number,
        s.firstname,s.lastname,p.prefix_short,g.group_name,d.department_name,f.faculty_name,
        g.group_id,
        (SELECT CONCAT(
            CASE WHEN COALESCE(cp.prefix_short,cp.prefix_name,"") IN ("นาย","นาง","นางสาว")
                THEN "อาจารย์ " ELSE COALESCE(cp.prefix_short,cp.prefix_name,"") END,
            ce.firstname," ",ce.lastname)
            FROM employee ce
            LEFT JOIN prefixes cp ON cp.prefix_id=ce.prefix_id
            JOIN employee_position cep ON cep.employee_id=ce.employee_id
            JOIN positions cpos ON cpos.position_id=cep.position_id
            WHERE ce.department_id=d.department_id
              AND cpos.position_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ"
            ORDER BY ce.employee_id LIMIT 1) AS coordinator_name,
        CONCAT(COALESCE(sp.prefix_short,sp.prefix_name,""),se.firstname," ",se.lastname) AS supervisor_display_name,
        CONCAT(COALESCE(up.prefix_short,up.prefix_name,""),ue.firstname," ",ue.lastname) AS updated_by_display_name,
        CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="request") THEN "sent" ELSE NULL END AS request_letter_status,
        CASE WHEN EXISTS (SELECT 1 FROM internship_response_document rd WHERE rd.internship_profile_id=ip.internship_profile_id AND rd.review_status="approved") THEN "sent" ELSE NULL END AS response_status,
        it.application_status,
        CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="send") THEN "sent" ELSE NULL END AS send_letter_status,
        it.employment_status
        FROM internship_application ia
        JOIN internship_profile ip ON ip.internship_profile_id=ia.internship_profile_id
        JOIN internship_calendar ic ON ic.calendar_id=ip.calendar_id
        JOIN student s ON s.student_id=ip.student_id
        LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
        JOIN group_class g ON g.group_id=s.group_id
        JOIN department d ON d.department_id=g.department_id
        LEFT JOIN faculty f ON f.faculty_id=d.faculty_id
        LEFT JOIN internship_supervision isu ON isu.internship_profile_id=ip.internship_profile_id
        LEFT JOIN internship_tracking it ON it.internship_profile_id=ip.internship_profile_id
        LEFT JOIN employee se ON se.employee_id=isu.supervisor_employee_id
        LEFT JOIN prefixes sp ON sp.prefix_id=se.prefix_id
        LEFT JOIN employee ue ON ue.user_id=isu.updated_by
        LEFT JOIN prefixes up ON up.prefix_id=ue.prefix_id
        WHERE ia.status="submitted" AND ip.request_status="active" AND g.department_id=?
        ORDER BY ip.company_name,s.student_number');
    $stmt->execute([$departmentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $missing = internshipSupervisionMissingDocuments($row);
        $row['supervision_ready'] = !$missing;
        $row['supervision_missing_documents'] = implode(', ', $missing);
    }
    unset($row);
    return $rows;
}

function internshipSupervisionMissingDocuments(array $row): array
{
    $requirements = [
        'request_letter_status' => ['sent', 'หนังสือสอบถาม'],
        'response_status' => ['sent', 'หนังสือตอบรับ'],
        'application_status' => ['applied', 'การสมัครงานในระบบ'],
        'send_letter_status' => ['sent', 'หนังสือส่งตัว'],
        'employment_status' => ['hired', 'ผลรับเข้าทำงาน'],
    ];
    $missing = [];
    foreach ($requirements as $field => [$expected, $label]) {
        if (($row[$field] ?? null) !== $expected) $missing[] = $label;
    }
    return $missing;
}

function supervisionCurrentInternshipPeriod(PDO $pdo): array
{
    // The supervision board represents one current internship cohort. Prefer
    // the active calendar containing today's date, then the nearest upcoming
    // active calendar if the board is prepared before the term begins.
    $today = date('Y-m-d');
    $current = $pdo->prepare('SELECT calendar_id,start_date,end_date FROM internship_calendar
        WHERE status="active" AND start_date<=? AND end_date>=?
        ORDER BY academic_year DESC,semester DESC LIMIT 1');
    $current->execute([$today, $today]);
    $period = $current->fetch(PDO::FETCH_ASSOC);
    if ($period) return $period;

    $upcoming = $pdo->prepare('SELECT calendar_id,start_date,end_date FROM internship_calendar
        WHERE status="active" AND start_date>=? ORDER BY start_date LIMIT 1');
    $upcoming->execute([$today]);
    return $upcoming->fetch(PDO::FETCH_ASSOC) ?: [];
}

function supervisionSummary(array $rows, array $staff, array $sharedPeriod = []): array
{
    $unique = static function (array $values): array {
        $values = array_map(static fn($value) => trim((string)$value), $values);
        return array_values(array_unique(array_filter($values, static fn($value) => $value !== '')));
    };
    $thaiDate = static function (?string $date): string {
        if (!$date) return '';
        $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
        $value = new DateTimeImmutable($date);
        return (int)$value->format('j').' '.$months[(int)$value->format('n')].' '.((int)$value->format('Y') + 543);
    };

    $periodSource = $sharedPeriod ?: ($rows[0] ?? []);
    $start = $thaiDate($periodSource['start_date'] ?? null);
    $end = $thaiDate($periodSource['end_date'] ?? null);
    $period = ($start !== '' || $end !== '') ? trim('วันที่ '.$start.' ถึงวันที่ '.$end) : '';

    $faculty = preg_replace('/^\s*คณะ\s*/u', '', (string)($rows[0]['faculty_name'] ?? ''));
    $department = preg_replace('/^\s*สาขาวิชา\s*/u', '', (string)($rows[0]['department_name'] ?? $staff['department_name'] ?? ''));
    return [
        'faculty_department' => trim('คณะ'.$faculty.' สาขาวิชา'.$department),
        'groups' => $unique(array_column($rows, 'group_name')),
        'coordinators' => $unique(array_column($rows, 'coordinator_name')),
        'period' => $period,
    ];
}

function saveSupervision(PDO $pdo, array $actor, array $input): void
{
    $profileId = (int)($input['profile_id'] ?? 0);
    $check = $pdo->prepare('SELECT ip.internship_profile_id
        FROM internship_profile ip
        JOIN internship_application ia ON ia.internship_profile_id=ip.internship_profile_id AND ia.status="submitted"
        JOIN student s ON s.student_id=ip.student_id
        JOIN group_class g ON g.group_id=s.group_id
        WHERE ip.internship_profile_id=? AND g.department_id=? LIMIT 1');
    $check->execute([$profileId, (int)$actor['department_id']]);
    $allowedProfile = $check->fetch(PDO::FETCH_ASSOC);
    if ($profileId < 1 || !$allowedProfile) {
        throw new RuntimeException('ไม่มีสิทธิ์แก้ไขข้อมูลนี้');
    }
    // A multi-role teacher can enter this screen in staff mode. Use the
    // active workspace role, when supplied, rather than the employee master
    // type so staff-mode actions retain staff capabilities.
    $isTeacher = ($actor['effective_role'] ?? $actor['employee_type'] ?? '') === 'teacher';
    if ($isTeacher) {
        $trackingStmt = $pdo->prepare('SELECT
            CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="request") THEN "sent" ELSE NULL END AS request_letter_status,
            CASE WHEN EXISTS (SELECT 1 FROM internship_response_document rd WHERE rd.internship_profile_id=ip.internship_profile_id AND rd.review_status="approved") THEN "sent" ELSE NULL END AS response_status,
            it.application_status,
            CASE WHEN EXISTS (SELECT 1 FROM internship_official_student ios JOIN internship_official_document iod ON iod.official_document_id=ios.official_document_id WHERE ios.internship_profile_id=ip.internship_profile_id AND iod.document_type="send") THEN "sent" ELSE NULL END AS send_letter_status,
            it.employment_status
            FROM internship_profile ip LEFT JOIN internship_tracking it ON it.internship_profile_id=ip.internship_profile_id
            WHERE ip.internship_profile_id=? LIMIT 1');
        $trackingStmt->execute([$profileId]);
        $missing = internshipSupervisionMissingDocuments($trackingStmt->fetch(PDO::FETCH_ASSOC) ?: []);
        if ($missing) {
            throw new RuntimeException('ยังไม่สามารถกรอกแผนนิเทศได้ เนื่องจากเอกสารสหกิจยังไม่ครบ: ' . implode(', ', $missing));
        }
    }
    $supervisorId = $isTeacher
        ? (int)$actor['employee_id']
        : (int)($input['supervisor_employee_id'] ?? 0);
    if ($supervisorId > 0) {
        $teacherCheck = $pdo->prepare('SELECT employee_id FROM employee
            WHERE employee_id=? AND department_id=? AND employee_type="teacher" LIMIT 1');
        $teacherCheck->execute([$supervisorId, (int)$actor['department_id']]);
        if (!$teacherCheck->fetchColumn()) {
            throw new RuntimeException('อาจารย์ที่เลือกไม่อยู่ในสาขานี้');
        }
    }

    $teacherName = null;
    if ($supervisorId > 0) {
        $nameStmt = $pdo->prepare('SELECT CONCAT(COALESCE(p.prefix_short,p.prefix_name,""),e.firstname," ",e.lastname)
            FROM employee e LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id WHERE e.employee_id=?');
        $nameStmt->execute([$supervisorId]);
        $teacherName = trim((string)$nameStmt->fetchColumn());
    }

    $allowedModes = ['Onsite', 'Online'];
    $values = [];
    foreach (['visit1_date','visit1_time','visit2_date','visit2_time'] as $field) {
        $values[$field] = trim((string)($input[$field] ?? '')) ?: null;
    }
    foreach (['visit1_mode','visit2_mode'] as $field) {
        $mode = trim((string)($input[$field] ?? ''));
        $values[$field] = in_array($mode, $allowedModes, true) ? $mode : null;
    }

    $expectedVersion = max(0, (int) ($input['expected_lock_version'] ?? 0));
    $pdo->beginTransaction();
    try {
        $currentStmt = $pdo->prepare('SELECT lock_version FROM internship_supervision WHERE internship_profile_id=? FOR UPDATE');
        $currentStmt->execute([$profileId]);
        $currentVersion = $currentStmt->fetchColumn();

        if ($currentVersion === false) {
            if ($expectedVersion !== 0) {
                throw new RuntimeException('แผนนิเทศนี้มีการเปลี่ยนแปลงแล้ว กรุณาโหลดข้อมูลล่าสุดก่อนบันทึก');
            }
            $insertStmt = $pdo->prepare('INSERT INTO internship_supervision
                (internship_profile_id,supervisor_employee_id,visit1_date,visit1_time,visit1_mode,
                 visit2_date,visit2_time,visit2_mode,supervisor_names,updated_by,lock_version)
                VALUES (?,?,?,?,?,?,?,?,?,?,1)');
            $insertStmt->execute([
                $profileId, $supervisorId > 0 ? $supervisorId : null,
                $values['visit1_date'], $values['visit1_time'], $values['visit1_mode'],
                $values['visit2_date'], $values['visit2_time'], $values['visit2_mode'],
                $teacherName, (int)$actor['user_id'],
            ]);
        } else {
            if ((int) $currentVersion !== $expectedVersion) {
                throw new RuntimeException('แผนนิเทศนี้ถูกแก้ไขโดยผู้ใช้อื่นแล้ว กรุณาโหลดข้อมูลล่าสุดก่อนบันทึก');
            }
            $updateStmt = $pdo->prepare('UPDATE internship_supervision SET
                supervisor_employee_id=?,visit1_date=?,visit1_time=?,visit1_mode=?,
                visit2_date=?,visit2_time=?,visit2_mode=?,supervisor_names=?,updated_by=?,
                lock_version=lock_version+1
                WHERE internship_profile_id=?');
            $updateStmt->execute([
                $supervisorId > 0 ? $supervisorId : null,
                $values['visit1_date'], $values['visit1_time'], $values['visit1_mode'],
                $values['visit2_date'], $values['visit2_time'], $values['visit2_mode'],
                $teacherName, (int)$actor['user_id'], $profileId,
            ]);
        }
        $pdo->commit();
        logActivity(
            $pdo,
            (int) $actor['user_id'],
            'supervision_saved',
            'บันทึกแผนนิเทศของนักศึกษารายการ #' . $profileId
            . ' (อาจารย์นิเทศ: ' . ($teacherName ?: 'ยังไม่กำหนด') . ')'
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
