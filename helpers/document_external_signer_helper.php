<?php

/**
 * Resolve the people a student must visit after printing a registration
 * request. This follows the same recipient rules used for related-teacher
 * notifications after advisor approval; it must not use the online-review
 * workflow, which intentionally has only the advisor.
 *
 * @return array<int, array{position: string, name: string}>
 */
function getDocumentExternalSigners(PDO $pdo, int $documentId): array
{
    $contextStatement = $pdo->prepare(
        'SELECT REPLACE(UPPER(type.document_code), ".", "") AS document_code,
                group_class.advisor_employee_id, group_class.department_id
         FROM documents document
         INNER JOIN student student ON student.student_id=document.student_id
         LEFT JOIN group_class group_class ON group_class.group_id=student.group_id
         INNER JOIN document_template template ON template.template_id=document.template_id
         INNER JOIN document_type type ON type.document_type_id=template.document_type_id
         WHERE document.document_id=? LIMIT 1'
    );
    $contextStatement->execute([$documentId]);
    $context = $contextStatement->fetch(PDO::FETCH_ASSOC) ?: [];
    $documentCode = (string) ($context['document_code'] ?? '');
    $departmentId = (int) ($context['department_id'] ?? 0);
    if ($departmentId <= 0 || $documentCode === '') return [];

    $requiredRecipients = [[
        'position' => 'อาจารย์ที่ปรึกษา',
        'employee_id' => (int) ($context['advisor_employee_id'] ?? 0),
    ]];
    $positionRules = match ($documentCode) {
        'R01' => [
            ['position' => 'ประธานหลักสูตร', 'match' => 'ประธานหลักสูตร'],
            ['position' => 'หัวหน้าสาขา', 'match' => 'หัวหน้าสาขา%'],
        ],
        'R12' => [['position' => 'ประธานหลักสูตร', 'match' => 'ประธานหลักสูตร']],
        'R14' => [
            ['position' => 'ประธานหลักสูตร', 'match' => 'ประธานหลักสูตร'],
            ['position' => 'หัวหน้าสาขา', 'match' => 'หัวหน้าสาขา%'],
        ],
        'R18', 'R24' => [['position' => 'หัวหน้าสาขา', 'match' => 'หัวหน้าสาขา%']],
        default => [],
    };
    $positionStatement = $pdo->prepare(
        'SELECT employee.employee_id
         FROM employee
         INNER JOIN employee_position employee_position ON employee_position.employee_id=employee.employee_id
         INNER JOIN positions position ON position.position_id=employee_position.position_id
         WHERE employee.department_id=? AND employee.employee_type="teacher" AND position.position_name LIKE ?
         ORDER BY employee_position.is_primary DESC, employee.employee_id LIMIT 1'
    );
    foreach ($positionRules as $rule) {
        $positionStatement->execute([$departmentId, $rule['match']]);
        $requiredRecipients[] = ['position' => $rule['position'], 'employee_id' => (int) $positionStatement->fetchColumn()];
    }

    // R.18/R.24 include the course instructor selected in the form. This is
    // shown to the student even when that instructor is not a notification
    // recipient in the online workflow.
    if (in_array($documentCode, ['R18', 'R24'], true)) {
        $teacherStatement = $pdo->prepare(
            'SELECT document_value.field_value
             FROM document_field_values document_value
             INNER JOIN template_fields field ON field.field_id=document_value.field_id
             WHERE document_value.document_id=?
               AND field.field_name IN ("instructor_employee_id", "teacher_employee_id")
             ORDER BY document_value.value_id DESC
             LIMIT 1'
        );
        $teacherStatement->execute([$documentId]);
        array_splice($requiredRecipients, 1, 0, [[
            'position' => 'อาจารย์ผู้รับผิดชอบรายวิชา',
            'employee_id' => (int) $teacherStatement->fetchColumn(),
        ]]);
    }

    $employeeIds = array_values(array_unique(array_filter(array_column($requiredRecipients, 'employee_id'))));
    if (!$employeeIds) return [];
    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $nameStatement = $pdo->prepare(
        'SELECT employee.employee_id, employee.firstname, employee.lastname,
                COALESCE(prefix.prefix_short, prefix.prefix_name, "") AS prefix_name
         FROM employee LEFT JOIN prefixes prefix ON prefix.prefix_id=employee.prefix_id
         WHERE employee.employee_id IN (' . $placeholders . ')'
    );
    $nameStatement->execute($employeeIds);
    $names = [];
    foreach ($nameStatement->fetchAll(PDO::FETCH_ASSOC) as $employee) {
        $prefix = trim((string) $employee['prefix_name']);
        // These recipients are teachers. A personal salutation is not the
        // title students use when taking the printed request for signature.
        if ($prefix === '' || in_array($prefix, ['นาย', 'นาง', 'นางสาว', 'Mr.', 'Mrs.', 'Miss'], true)) {
            $prefix = 'อาจารย์';
        }
        $names[(int) $employee['employee_id']] = trim($prefix . (string) $employee['firstname'] . ' ' . (string) $employee['lastname']);
    }

    $signers = [];
    foreach ($requiredRecipients as $recipient) {
        $employeeId = (int) $recipient['employee_id'];
        $name = $names[$employeeId] ?? '';
        // One person can legitimately appear in more than one signature role
        // (for example, an advisor who also teaches the selected course).
        if ($employeeId <= 0 || $name === '') continue;
        $signers[] = ['position' => $recipient['position'], 'name' => $name];
    }
    return $signers;
}
