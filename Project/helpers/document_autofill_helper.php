<?php

function getDocumentStudentProfile(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.student_number, s.firstname, s.lastname, pr.prefix_short
         FROM student s
         LEFT JOIN prefixes pr ON pr.prefix_id=s.prefix_id
         WHERE s.user_id=?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getAutomaticDocumentFieldValue(array $field, array $student, string $accessDate): ?string
{
    $name = strtolower(trim((string)($field['field_name'] ?? '')));
    $label = trim((string)($field['field_label'] ?? ''));
    $studentName = trim(($student['firstname'] ?? '') . ' ' . ($student['lastname'] ?? ''));
    $studentNameWithPrefix = trim(($student['prefix_short'] ?? '') . ' ' . $studentName);

    if (in_array($name, ['student_name', 'student_fullname', 'fullname', 'full_name', 'signature_name', 'student_signature'], true)
        || preg_match('/ชื่อ\s*[-–]?\s*นามสกุล|ลงชื่อ/u', $label)) {
        return $studentName;
    }

    if (in_array($name, ['student_name_with_prefix', 'fullname_with_prefix'], true)) {
        return $studentNameWithPrefix;
    }

    if (in_array($name, ['student_number', 'student_id', 'student_number'], true)
        || preg_match('/รหัส.*นักศึกษา/u', $label)) {
        return (string)($student['student_number'] ?? '');
    }

    if (in_array($name, ['request_date', 'document_date', 'submission_date', 'current_date', 'date'], true)
        || ($name === '' && preg_match('/^วันที่/u', $label))) {
        return $accessDate;
    }

    $date = new DateTimeImmutable($accessDate, new DateTimeZone('Asia/Bangkok'));
    if (in_array($name, ['request_day', 'document_day', 'date_day'], true)) return $date->format('d');
    if (in_array($name, ['request_month', 'document_month', 'date_month'], true)) return $date->format('m');
    if (in_array($name, ['request_year', 'document_year', 'date_year'], true)) return (string)((int)$date->format('Y') + 543);

    return null;
}
