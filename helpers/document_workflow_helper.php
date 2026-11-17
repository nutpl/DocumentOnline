<?php

require_once __DIR__ . '/notification_helper.php';

/**
 * Starts a configured sequential review, or resumes the same step after the
 * student has corrected a returned document. Returns managed=false when the
 * document type has no active workflow so the caller can stop the submission.
 * Call this function inside the caller's database transaction.
 */
function startOrResumeDocumentWorkflow(PDO $pdo, int $documentId, int $studentUserId): array
{
    $processStatement = $pdo->prepare(
        "SELECT drp.*, dsi.document_step_id, dsi.reviewer_user_id, dsi.step_name
         FROM document_review_processes drp
         LEFT JOIN document_review_step_instances dsi
           ON dsi.process_id = drp.process_id AND dsi.step_order = drp.current_step_order
         WHERE drp.document_id = ?
         LIMIT 1 FOR UPDATE"
    );
    $processStatement->execute([$documentId]);
    $process = $processStatement->fetch(PDO::FETCH_ASSOC);

    if ($process) {
        if ($process['process_status'] !== 'revision_requested' || !$process['document_step_id']) {
            throw new RuntimeException('เอกสารอยู่ระหว่างการพิจารณาและไม่สามารถเริ่มกระบวนการซ้ำได้');
        }

        $pdo->prepare(
            "UPDATE document_review_step_instances
             SET step_status = 'current', attempt_count = attempt_count + 1,
                 activated_at = NOW(), reviewed_at = NULL
             WHERE document_step_id = ?"
        )->execute([(int) $process['document_step_id']]);
        $pdo->prepare(
            "UPDATE document_review_processes SET process_status = 'in_review' WHERE process_id = ?"
        )->execute([(int) $process['process_id']]);
        $pdo->prepare("UPDATE documents SET document_status = 'in_review' WHERE document_id = ?")
            ->execute([$documentId]);
        recordWorkflowAction($pdo, (int) $process['document_step_id'], $documentId, $studentUserId, 'resubmitted', 'นักศึกษาแก้ไขและส่งเอกสารใหม่');

        $meta = workflowDocumentMeta($pdo, $documentId);
        notifyWorkflowReviewer(
            $pdo,
            (int) $process['reviewer_user_id'],
            $documentId,
            (string) $meta['document_code'],
            (string) $process['step_name'],
            true
        );
        return ['managed' => true, 'process_id' => (int) $process['process_id'], 'resumed' => true];
    }

    $meta = workflowDocumentMeta($pdo, $documentId);
    $workflowStatement = $pdo->prepare(
        "SELECT rw.*
         FROM review_workflows rw
         WHERE rw.document_type_id = ? AND rw.workflow_status = 'active'
         ORDER BY rw.version_no DESC LIMIT 1"
    );
    $workflowStatement->execute([(int) $meta['document_type_id']]);
    $workflow = $workflowStatement->fetch(PDO::FETCH_ASSOC);
    if (!$workflow) {
        return ['managed' => false];
    }

    $stepStatement = $pdo->prepare(
        "SELECT * FROM review_workflow_steps WHERE workflow_id = ? ORDER BY step_order"
    );
    $stepStatement->execute([(int) $workflow['workflow_id']]);
    $configuredSteps = $stepStatement->fetchAll(PDO::FETCH_ASSOC);
    if (!$configuredSteps) {
        throw new RuntimeException('Workflow ของเอกสารนี้ยังไม่มีลำดับผู้พิจารณา');
    }

    $resolvedSteps = [];
    foreach ($configuredSteps as $step) {
        $reviewer = resolveWorkflowReviewer($pdo, $meta, $step);
        if (!$reviewer) {
            throw new RuntimeException('ไม่พบผู้พิจารณาสำหรับขั้นตอน “' . $step['step_name'] . '” กรุณาแจ้งผู้ดูแลระบบ');
        }
        $resolvedSteps[] = ['config' => $step, 'reviewer' => $reviewer];
    }

    $insertProcess = $pdo->prepare(
        "INSERT INTO document_review_processes
            (document_id, workflow_id, workflow_version_no, current_step_order, process_status)
         VALUES (?, ?, ?, 1, 'in_review')"
    );
    $insertProcess->execute([$documentId, $workflow['workflow_id'], $workflow['version_no']]);
    $processId = (int) $pdo->lastInsertId();

    $insertStep = $pdo->prepare(
        "INSERT INTO document_review_step_instances
            (process_id, source_workflow_step_id, step_order, role_code, step_name,
             reviewer_employee_id, reviewer_user_id, step_status, attempt_count, activated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $firstStepId = 0;
    foreach ($resolvedSteps as $index => $resolved) {
        $isFirst = $index === 0;
        $step = $resolved['config'];
        $reviewer = $resolved['reviewer'];
        $insertStep->execute([
            $processId,
            $step['workflow_step_id'],
            $step['step_order'],
            $step['role_code'],
            $step['step_name'],
            $reviewer['employee_id'],
            $reviewer['user_id'],
            $isFirst ? 'current' : 'waiting',
            $isFirst ? 1 : 0,
            $isFirst ? date('Y-m-d H:i:s') : null,
        ]);
        if ($isFirst) {
            $firstStepId = (int) $pdo->lastInsertId();
        }
    }

    $pdo->prepare("UPDATE documents SET document_status = 'in_review' WHERE document_id = ?")
        ->execute([$documentId]);
    recordWorkflowAction($pdo, $firstStepId, $documentId, null, 'activated', 'เริ่มขั้นตอนพิจารณา');
    notifyWorkflowReviewer(
        $pdo,
        (int) $resolvedSteps[0]['reviewer']['user_id'],
        $documentId,
        (string) $meta['document_code'],
        (string) $resolvedSteps[0]['config']['step_name'],
        false
    );

    return ['managed' => true, 'process_id' => $processId, 'resumed' => false];
}

/**
 * Returns an already-completed workflow to its first reviewer.  This is used
 * when the request itself has materially changed (for example a replacement
 * signed request), so every reviewer must consider the new version again.
 * Call this function inside the caller's database transaction.
 */
function resetDocumentWorkflowForFullReview(PDO $pdo, int $documentId): void
{
    $processStatement = $pdo->prepare(
        'SELECT process_id FROM document_review_processes WHERE document_id = ? LIMIT 1 FOR UPDATE'
    );
    $processStatement->execute([$documentId]);
    $processId = (int) $processStatement->fetchColumn();
    if ($processId < 1) {
        throw new RuntimeException('ไม่พบกระบวนการพิจารณาของคำร้องนี้');
    }

    $pdo->prepare(
        "UPDATE document_review_step_instances
         SET step_status = CASE WHEN step_order = 1 THEN 'revision_requested' ELSE 'waiting' END,
             activated_at = NULL, reviewed_at = NULL
         WHERE process_id = ?"
    )->execute([$processId]);
    $pdo->prepare(
        "UPDATE document_review_processes
         SET current_step_order = 1, process_status = 'revision_requested',
             completed_at = NULL, revision_count = revision_count + 1
         WHERE process_id = ?"
    )->execute([$processId]);
}

/** Processes an approval or revision request for the current workflow step. */
function processDocumentWorkflowReview(PDO $pdo, int $documentId, int $reviewerUserId, string $decision, string $comment): array
{
    if (!in_array($decision, ['approved', 'revision_requested'], true)) {
        throw new InvalidArgumentException('ผลการพิจารณาไม่ถูกต้อง');
    }

    $statement = $pdo->prepare(
        "SELECT drp.process_id, drp.current_step_order, dsi.document_step_id,
                dsi.step_name, dsi.reviewer_user_id, s.user_id AS student_user_id,
                dt.document_code
         FROM document_review_processes drp
         INNER JOIN document_review_step_instances dsi
           ON dsi.process_id = drp.process_id AND dsi.step_order = drp.current_step_order
         INNER JOIN documents d ON d.document_id = drp.document_id
         INNER JOIN student s ON s.student_id = d.student_id
         INNER JOIN document_template t ON t.template_id = d.template_id
         INNER JOIN document_type dt ON dt.document_type_id = t.document_type_id
         WHERE drp.document_id = ? AND drp.process_status = 'in_review'
           AND dsi.step_status = 'current'
         LIMIT 1 FOR UPDATE"
    );
    $statement->execute([$documentId]);
    $current = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$current || (int) $current['reviewer_user_id'] !== $reviewerUserId) {
        throw new RuntimeException('เอกสารไม่ได้อยู่ในขั้นตอนที่คุณมีสิทธิ์พิจารณา');
    }

    $stepId = (int) $current['document_step_id'];
    if ($decision === 'revision_requested') {
        $pdo->prepare(
            "UPDATE document_review_step_instances
             SET step_status = 'revision_requested', reviewed_at = NOW()
             WHERE document_step_id = ?"
        )->execute([$stepId]);
        $pdo->prepare(
            "UPDATE document_review_processes
             SET process_status = 'revision_requested', revision_count = revision_count + 1
             WHERE process_id = ?"
        )->execute([(int) $current['process_id']]);
        $pdo->prepare("UPDATE documents SET document_status = 'revision_requested' WHERE document_id = ?")
            ->execute([$documentId]);
        if (strtoupper((string) $current['document_code']) === 'INTERNSHIP_INFO') {
            $pdo->prepare(
                "UPDATE internship_application
                 SET status = 'draft', signed_file = NULL, submitted_at = NULL
                 WHERE document_id = ?"
            )->execute([$documentId]);
        }
        recordWorkflowAction($pdo, $stepId, $documentId, $reviewerUserId, 'revision_requested', $comment);
        createNotification(
            $pdo,
            (int) $current['student_user_id'],
            $documentId,
            'revision_requested',
            'คำร้องถูกส่งกลับให้แก้ไข',
            'คำร้อง ' . $current['document_code'] . ' ต้องแก้ไขในขั้น “' . $current['step_name'] . '”: ' . $comment,
            strtoupper((string) $current['document_code']) === 'INTERNSHIP_INFO'
                ? '/Project/student/internship_form.php'
                : '/Project/student/document_form.php?document=' . $documentId . '&from=history'
        );
        markWorkflowNotificationRead($pdo, $reviewerUserId, $documentId);
        return ['completed' => false, 'revision_requested' => true, 'step_name' => $current['step_name']];
    }

    $pdo->prepare(
        "UPDATE document_review_step_instances SET step_status = 'approved', reviewed_at = NOW()
         WHERE document_step_id = ?"
    )->execute([$stepId]);
    recordWorkflowAction($pdo, $stepId, $documentId, $reviewerUserId, 'approved', $comment !== '' ? $comment : null);

    $nextStatement = $pdo->prepare(
        "SELECT document_step_id, step_order, step_name, reviewer_user_id
         FROM document_review_step_instances
         WHERE process_id = ? AND step_order > ? AND step_status = 'waiting'
         ORDER BY step_order LIMIT 1"
    );
    $nextStatement->execute([(int) $current['process_id'], (int) $current['current_step_order']]);
    $next = $nextStatement->fetch(PDO::FETCH_ASSOC);

    markWorkflowNotificationRead($pdo, $reviewerUserId, $documentId);

    if ($next) {
        createNotification(
            $pdo,
            (int) $current['student_user_id'],
            $documentId,
            'review_step_approved',
            'คำร้องผ่านการพิจารณาแล้ว 1 ขั้นตอน',
            'คำร้อง ' . $current['document_code'] . ' ผ่านขั้น “' . $current['step_name'] . '” แล้ว',
            '/Project/student/history.php'
        );
        $pdo->prepare(
            "UPDATE document_review_step_instances
             SET step_status = 'current', attempt_count = attempt_count + 1, activated_at = NOW()
             WHERE document_step_id = ?"
        )->execute([(int) $next['document_step_id']]);
        $pdo->prepare(
            "UPDATE document_review_processes SET current_step_order = ?, process_status = 'in_review'
             WHERE process_id = ?"
        )->execute([(int) $next['step_order'], (int) $current['process_id']]);
        $pdo->prepare("UPDATE documents SET document_status = 'in_review' WHERE document_id = ?")
            ->execute([$documentId]);
        recordWorkflowAction($pdo, (int) $next['document_step_id'], $documentId, null, 'activated', 'ส่งต่อจากขั้นก่อนหน้า');
        notifyWorkflowReviewer($pdo, (int) $next['reviewer_user_id'], $documentId, (string) $current['document_code'], (string) $next['step_name'], false);
        return ['completed' => false, 'revision_requested' => false, 'step_name' => $current['step_name'], 'next_step_name' => $next['step_name']];
    }

    $pdo->prepare(
        "UPDATE document_review_processes
         SET current_step_order = NULL, process_status = 'approved', completed_at = NOW()
         WHERE process_id = ?"
    )->execute([(int) $current['process_id']]);
    $pdo->prepare("UPDATE documents SET document_status = 'approved' WHERE document_id = ?")
        ->execute([$documentId]);
    $isInternship = strtoupper((string) $current['document_code']) === 'INTERNSHIP_INFO';
    if ($isInternship) {
        $pdo->prepare(
            "UPDATE internship_application
             SET status = 'waiting_signature', signed_file = NULL, submitted_at = NULL
             WHERE document_id = ?"
        )->execute([$documentId]);
    }
    createNotification(
        $pdo,
        (int) $current['student_user_id'],
        $documentId,
        'document_approved',
        $isInternship ? 'คำร้องสหกิจผ่านการพิจารณาครบแล้ว' : 'คำร้องผ่านอาจารย์ที่ปรึกษาแล้ว',
        $isInternship
            ? 'คำร้องสหกิจผ่านการพิจารณาจากอาจารย์ที่ปรึกษาและอาจารย์ประสานงานแล้ว กรุณาพิมพ์เอกสารและนำไปลงนามภายนอกระบบให้ครบ 2 คน ก่อนอัปโหลดฉบับลงนามให้เจ้าหน้าที่'
            : 'คำร้อง ' . $current['document_code'] . ' ผ่านการพิจารณาจากอาจารย์ที่ปรึกษาแล้ว สามารถดาวน์โหลดและพิมพ์เอกสารเพื่อนำไปให้ผู้ที่เกี่ยวข้องลงนามนอกระบบได้',
        $isInternship ? '/Project/student/internship_form.php' : '/Project/student/history.php'
    );
    if (!$isInternship) {
        notifyRelatedTeachersAfterAdvisorApproval($pdo, $documentId, $reviewerUserId);
    }
    return ['completed' => true, 'revision_requested' => false, 'step_name' => $current['step_name']];
}

function workflowReviewForUser(PDO $pdo, int $documentId, int $userId): ?array
{
    $statement = $pdo->prepare(
        "SELECT dsi.document_step_id, dsi.step_name, dsi.step_order, drp.process_id
         FROM document_review_processes drp
         INNER JOIN document_review_step_instances dsi
           ON dsi.process_id = drp.process_id AND dsi.step_order = drp.current_step_order
         WHERE drp.document_id = ? AND drp.process_status = 'in_review'
           AND dsi.step_status = 'current' AND dsi.reviewer_user_id = ?
         LIMIT 1"
    );
    $statement->execute([$documentId, $userId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function workflowDocumentMeta(PDO $pdo, int $documentId): array
{
    $statement = $pdo->prepare(
        "SELECT d.document_id, d.student_id, s.user_id AS student_user_id,
                s.student_number, s.firstname, s.lastname,
                g.advisor_employee_id,
                g.department_id, dep.faculty_id, dt.document_type_id, dt.document_code
         FROM documents d
         INNER JOIN student s ON s.student_id = d.student_id
         LEFT JOIN group_class g ON g.group_id = s.group_id
         LEFT JOIN department dep ON dep.department_id = g.department_id
         INNER JOIN document_template t ON t.template_id = d.template_id
         INNER JOIN document_type dt ON dt.document_type_id = t.document_type_id
         WHERE d.document_id = ? LIMIT 1"
    );
    $statement->execute([$documentId]);
    $meta = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$meta) {
        throw new RuntimeException('ไม่พบเอกสารที่ต้องการส่ง');
    }
    return $meta;
}

function resolveWorkflowReviewer(PDO $pdo, array $meta, array $step): ?array
{
    $employeeId = 0;
    switch ($step['reviewer_source']) {
        case 'student_advisor':
            $employeeId = (int) ($meta['advisor_employee_id'] ?? 0);
            break;
        case 'document_teacher':
            $code = str_replace('.', '', (string) $meta['document_code']);
            $maps = [
                'R01' => ['r01_request', 'advisor_employee_id'],
                'R12' => ['r12_request', 'advisor_employee_id'],
                'R18' => ['r18_request', 'teacher_employee_id'],
                'R24' => ['r24_request', 'teacher_employee_id'],
            ];
            if (isset($maps[$code])) {
                [$table, $column] = $maps[$code];
                $statement = $pdo->prepare("SELECT {$column} FROM {$table} WHERE document_id = ? LIMIT 1");
                $statement->execute([(int) $meta['document_id']]);
                $employeeId = (int) $statement->fetchColumn();
            }
            break;
        case 'employee':
            $employeeId = (int) ($step['reviewer_employee_id'] ?? 0);
            break;
        case 'office_position':
            $positionId = (int) ($step['reviewer_position_id'] ?? 0);
            $params = [$positionId];
            $scopeSql = '';
            if ($step['scope_type'] === 'department') {
                $scopeSql = ' AND e.department_id = ?';
                $params[] = (int) $meta['department_id'];
            } elseif ($step['scope_type'] === 'faculty') {
                $scopeSql = ' AND dep.faculty_id = ?';
                $params[] = (int) $meta['faculty_id'];
            }
            $statement = $pdo->prepare(
                "SELECT e.employee_id
                 FROM employee_position ep
                 INNER JOIN employee e ON e.employee_id = ep.employee_id
                 INNER JOIN users u ON u.user_id = e.user_id AND u.status = 'active'
                 LEFT JOIN department dep ON dep.department_id = e.department_id
                 WHERE ep.position_id = ? {$scopeSql}
                 ORDER BY ep.is_primary DESC, ep.employee_position_id LIMIT 1"
            );
            $statement->execute($params);
            $employeeId = (int) $statement->fetchColumn();
            break;
    }

    if ($employeeId < 1) {
        return null;
    }
    $statement = $pdo->prepare(
        "SELECT e.employee_id, e.user_id
         FROM employee e INNER JOIN users u ON u.user_id = e.user_id
         WHERE e.employee_id = ? AND e.employee_type = 'teacher' AND u.status = 'active' LIMIT 1"
    );
    $statement->execute([$employeeId]);
    $reviewer = $statement->fetch(PDO::FETCH_ASSOC);
    return $reviewer ?: null;
}

function recordWorkflowAction(PDO $pdo, int $stepId, int $documentId, ?int $actorUserId, string $action, ?string $comment): void
{
    $statement = $pdo->prepare(
        "INSERT INTO document_events
            (document_step_id, document_id, actor_user_id, event_type, detail)
         VALUES (?, ?, ?, ?, ?)"
    );
    $statement->execute([$stepId, $documentId, $actorUserId, $action, $comment]);
}

function notifyWorkflowReviewer(PDO $pdo, int $userId, int $documentId, string $documentCode, string $stepName, bool $resubmitted): void
{
    createNotification(
        $pdo,
        $userId,
        $documentId,
        $resubmitted ? 'document_resubmitted' : 'workflow_step_assigned',
        $resubmitted ? 'นักศึกษาส่งคำร้องที่แก้ไขแล้ว' : 'มีคำร้องรอการพิจารณา',
        'คำร้อง ' . $documentCode . ' เลขที่ #' . $documentId . ' รอพิจารณาในขั้น “' . $stepName . '”',
        '/Project/teacher/request_review.php?document=' . $documentId
    );
}

function markWorkflowNotificationRead(PDO $pdo, int $userId, int $documentId): void
{
    $pdo->prepare(
        "UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, NOW())
         WHERE recipient_user_id = ? AND document_id = ? AND is_read = 0"
    )->execute([$userId, $documentId]);
}
