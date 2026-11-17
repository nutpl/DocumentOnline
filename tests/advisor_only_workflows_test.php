<?php
require_once __DIR__ . '/../config/database.php';

$expected = [
    'INTERNSHIP_INFO' => [2, 1],
    'R01' => [1, 1],
    'R12' => [1, 1],
    'R14' => [1, 1],
    'R18' => [1, 1],
    'R24' => [1, 1],
];
$statement = $pdo->query(
    "SELECT REPLACE(UPPER(dt.document_code),'.','') document_code,
            COUNT(rws.workflow_step_id) step_count,
            SUM(rws.reviewer_source='student_advisor') advisor_count
       FROM review_workflows rw
       JOIN document_type dt ON dt.document_type_id=rw.document_type_id
       JOIN review_workflow_steps rws ON rws.workflow_id=rw.workflow_id
      WHERE rw.workflow_status='active'
      GROUP BY rw.workflow_id,dt.document_code"
);
$actual = [];
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $actual[$row['document_code']] = [(int) $row['step_count'], (int) $row['advisor_count']];
}

$passed = true;
foreach ($expected as $code => $expectedCounts) {
    $ok = ($actual[$code] ?? null) === $expectedCounts;
    echo $code . '_workflow=' . ($ok ? 'passed' : 'failed') . PHP_EOL;
    $passed = $passed && $ok;
}
exit($passed ? 0 : 1);
