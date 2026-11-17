<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/document_workflow_helper.php';
require_once __DIR__ . '/../helpers/notification_helper.php';

$checks = [];
$pdo->beginTransaction();

try {
    $studentStatement = $pdo->query(
        "SELECT s.student_id,s.user_id,s.student_number,s.firstname,s.lastname,
                e.user_id advisor_user_id,g.department_id
         FROM student s
         INNER JOIN group_class g ON g.group_id=s.group_id
         INNER JOIN employee e ON e.employee_id=g.advisor_employee_id
         LEFT JOIN internship_profile ip ON ip.student_id=s.student_id
         WHERE ip.student_id IS NULL AND e.user_id IS NOT NULL
         LIMIT 1"
    );
    $student = $studentStatement->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        throw new RuntimeException('ไม่พบนักศึกษาทดสอบที่ยังไม่มีข้อมูลสหกิจ');
    }

    $templateId = (int) $pdo->query(
        "SELECT t.template_id FROM document_template t
         INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
         WHERE dt.document_code='INTERNSHIP_INFO' AND t.status='active'
         ORDER BY t.template_id DESC LIMIT 1"
    )->fetchColumn();
    if ($templateId <= 0) {
        throw new RuntimeException('ไม่พบแม่แบบ INTERNSHIP_INFO');
    }

    $pdo->prepare('INSERT INTO internship_profile (student_id,company_name) VALUES (?,?)')
        ->execute([(int) $student['student_id'], 'บริษัททดสอบ Workflow']);
    $profileId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO documents (student_id,template_id,document_status,submitted_at) VALUES (?,? ,"submitted",NOW())')
        ->execute([(int) $student['student_id'], $templateId]);
    $documentId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO internship_application (internship_profile_id,document_id,request_date,status) VALUES (?,?,CURDATE(),"in_review")')
        ->execute([$profileId, $documentId]);

    $started = startOrResumeDocumentWorkflow($pdo, $documentId, (int) $student['user_id']);
    $state = $pdo->prepare('SELECT d.document_status,ia.status FROM documents d INNER JOIN internship_application ia ON ia.document_id=d.document_id WHERE d.document_id=?');
    $state->execute([$documentId]);
    $firstState = $state->fetch(PDO::FETCH_ASSOC);
    $checks['submit_to_advisor'] = !empty($started['managed'])
        && $firstState['document_status'] === 'in_review'
        && $firstState['status'] === 'in_review';

    processDocumentWorkflowReview($pdo, $documentId, (int) $student['advisor_user_id'], 'revision_requested', 'กรุณาแก้ไขข้อมูลสถานประกอบการ');
    $state->execute([$documentId]);
    $revisionState = $state->fetch(PDO::FETCH_ASSOC);
    $revisionNotification = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE document_id=? AND recipient_user_id=? AND notification_type='revision_requested'");
    $revisionNotification->execute([$documentId, (int) $student['user_id']]);
    $checks['revision_to_student'] = $revisionState['document_status'] === 'revision_requested'
        && $revisionState['status'] === 'draft'
        && (int) $revisionNotification->fetchColumn() === 1;

    $pdo->prepare('UPDATE internship_application SET status="in_review" WHERE document_id=?')->execute([$documentId]);
    $pdo->prepare('UPDATE documents SET document_status="submitted" WHERE document_id=?')->execute([$documentId]);
    $resumed = startOrResumeDocumentWorkflow($pdo, $documentId, (int) $student['user_id']);
    $advisorApproval = processDocumentWorkflowReview($pdo, $documentId, (int) $student['advisor_user_id'], 'approved', 'ข้อมูลถูกต้อง');
    $state->execute([$documentId]);
    $coordinatorPendingState = $state->fetch(PDO::FETCH_ASSOC);
    $currentReviewerStatement = $pdo->prepare(
        "SELECT dsi.reviewer_user_id FROM document_review_processes drp
         INNER JOIN document_review_step_instances dsi
           ON dsi.process_id=drp.process_id AND dsi.step_order=drp.current_step_order
         WHERE drp.document_id=? AND dsi.step_status='current' LIMIT 1"
    );
    $currentReviewerStatement->execute([$documentId]);
    $coordinatorUserId = (int) $currentReviewerStatement->fetchColumn();
    $coordinatorNotification = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE document_id=? AND recipient_user_id=? AND notification_type='workflow_step_assigned'");
    $coordinatorNotification->execute([$documentId, $coordinatorUserId]);
    $checks['advisor_to_coordinator'] = ($advisorApproval['next_step_name'] ?? '') === 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ'
        && $coordinatorPendingState['document_status'] === 'in_review'
        && $coordinatorPendingState['status'] === 'in_review'
        && $coordinatorUserId > 0
        && (int) $coordinatorNotification->fetchColumn() === 1;

    processDocumentWorkflowReview($pdo, $documentId, $coordinatorUserId, 'approved', 'ผ่านการพิจารณาสหกิจ');
    $state->execute([$documentId]);
    $approvedState = $state->fetch(PDO::FETCH_ASSOC);
    $approvalNotification = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE document_id=? AND recipient_user_id=? AND notification_type='document_approved' AND target_url='/Project/student/internship_form.php'");
    $approvalNotification->execute([$documentId, (int) $student['user_id']]);
    $staffNotification = $pdo->prepare("SELECT COUNT(*) FROM notifications n INNER JOIN employee e ON e.user_id=n.recipient_user_id WHERE n.document_id=? AND e.employee_type='staff'");
    $staffNotification->execute([$documentId]);
    $checks['approval_before_signature'] = !empty($resumed['managed'])
        && $approvedState['document_status'] === 'approved'
        && $approvedState['status'] === 'waiting_signature'
        && (int) $approvalNotification->fetchColumn() === 1
        && (int) $staffNotification->fetchColumn() === 0;

    $pdo->prepare('UPDATE internship_application SET status="submitted",signed_file="test-signed.pdf",submitted_at=NOW() WHERE document_id=?')
        ->execute([$documentId]);
    notifyDepartmentStaffForInternship(
        $pdo,
        (int) $student['department_id'],
        $documentId,
        (string) $student['student_number'],
        trim((string) $student['firstname'] . ' ' . (string) $student['lastname'])
    );
    $state->execute([$documentId]);
    $submittedState = $state->fetch(PDO::FETCH_ASSOC);
    $staffNotification->execute([$documentId]);
    $checks['signed_copy_to_staff'] = $submittedState['document_status'] === 'approved'
        && $submittedState['status'] === 'submitted'
        && (int) $staffNotification->fetchColumn() > 0;

    foreach ($checks as $name => $passed) {
        echo $name . '=' . ($passed ? 'passed' : 'failed') . PHP_EOL;
    }
    $passed = !in_array(false, $checks, true);
    $pdo->rollBack();
    exit($passed ? 0 : 1);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
