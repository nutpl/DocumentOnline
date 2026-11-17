<?php

function getDocumentRolePrintNames(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare('SELECT
        g.advisor_employee_id,
        g.department_id,
        dep.faculty_id
        FROM documents doc
        JOIN student s ON s.student_id=doc.student_id
        LEFT JOIN group_class g ON g.group_id=s.group_id
        LEFT JOIN department dep ON dep.department_id=g.department_id
        WHERE doc.document_id=? LIMIT 1');
    $stmt->execute([$documentId]);
    $context = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $fieldStmt = $pdo->prepare('SELECT v.field_value
        FROM document_field_values v
        JOIN template_fields f ON f.field_id=v.field_id
        WHERE v.document_id=? AND f.field_name IN ("instructor_employee_id","teacher_employee_id")
        AND v.field_value<>"" ORDER BY v.value_id DESC LIMIT 1');
    $fieldStmt->execute([$documentId]);
    $instructorId = (int)$fieldStmt->fetchColumn();

    $instructorContextStmt=$pdo->prepare('SELECT e.department_id,dep.faculty_id FROM employee e LEFT JOIN department dep ON dep.department_id=e.department_id WHERE e.employee_id=? LIMIT 1');
    $instructorContextStmt->execute([$instructorId]);
    $instructorContext=$instructorContextStmt->fetch(PDO::FETCH_ASSOC)?:[];

    $findHead = static function(int $departmentId) use ($pdo): int {
        $headStmt = $pdo->prepare('SELECT e.employee_id FROM employee e
        JOIN employee_position ep ON ep.employee_id=e.employee_id
        JOIN positions p ON p.position_id=ep.position_id
        WHERE e.department_id=? AND p.position_name LIKE "หัวหน้าสาขา%"
        ORDER BY ep.is_primary DESC,e.employee_id LIMIT 1');
        $headStmt->execute([$departmentId]);return (int)$headStmt->fetchColumn();
    };
    $headId=$findHead((int)($context['department_id']??0));
    $responsibleHeadId=$findHead((int)($instructorContext['department_id']??0));

    $deanStmt = $pdo->prepare('SELECT e.employee_id FROM employee e
        JOIN department dep ON dep.department_id=e.department_id
        JOIN employee_position ep ON ep.employee_id=e.employee_id
        JOIN positions p ON p.position_id=ep.position_id
        WHERE dep.faculty_id=? AND p.position_name LIKE "%คณบดี%"
        ORDER BY ep.is_primary DESC,e.employee_id LIMIT 1');
    $deanStmt->execute([(int)($context['faculty_id'] ?? 0)]);
    $deanId = (int)$deanStmt->fetchColumn();
    $deanStmt->execute([(int)($instructorContext['faculty_id']??0)]);
    $responsibleDeanId=(int)$deanStmt->fetchColumn();

    $employeeIds = array_filter([
        'advisor_print_name' => (int)($context['advisor_employee_id'] ?? 0),
        'instructor_print_name' => $instructorId,
        'department_head_print_name' => $headId,
        'dean_print_name' => $deanId,
        'responsible_department_head_print_name_p2' => $responsibleHeadId,
        'responsible_dean_print_name_p2' => $responsibleDeanId,
    ]);
    if (!$employeeIds) return [];

    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $employeeStmt = $pdo->prepare("SELECT e.employee_id,e.firstname,e.lastname,pr.prefix_short,pr.prefix_name
        FROM employee e LEFT JOIN prefixes pr ON pr.prefix_id=e.prefix_id
        WHERE e.employee_id IN ($placeholders)");
    $employeeStmt->execute(array_values($employeeIds));
    $namesById = [];
    foreach ($employeeStmt->fetchAll(PDO::FETCH_ASSOC) as $employee) {
        $prefix = trim((string)($employee['prefix_short'] ?: $employee['prefix_name']));
        $namesById[(int)$employee['employee_id']] = $prefix . trim($employee['firstname'] . ' ' . $employee['lastname']);
    }

    $result = [];
    foreach ($employeeIds as $fieldName => $employeeId) {
        $result[$fieldName] = $namesById[$employeeId] ?? '';
    }

    // R18 page 2 repeats the same related people in separate approval paths.
    // Keep distinct template field names while resolving them from the same
    // authoritative role records.
    $result['advisor_print_name_p2'] = $result['advisor_print_name'] ?? '';
    $result['student_department_head_print_name_p2'] = $result['department_head_print_name'] ?? '';
    $result['responsible_department_head_print_name_p2'] = $result['responsible_department_head_print_name_p2'] ?? '';
    $result['student_dean_print_name_p2'] = $result['dean_print_name'] ?? '';
    // R18 case 2 leaves the instructor's parenthesized name blank for manual completion.
    $result['instructor_print_name_p2'] = '';
    $result['responsible_dean_print_name_p2'] = $result['responsible_dean_print_name_p2'] ?? '';
    return $result;
}
