<?php

function getDocumentWorkflowStatus(PDO $pdo, int $documentId): ?array
{
    $processStatement = $pdo->prepare(
        "SELECT drp.*, d.document_status, d.submitted_at,
                dt.document_code, dt.document_name,
                s.student_id, s.user_id AS student_user_id, s.student_number,
                COALESCE(sp.prefix_short, sp.prefix_name, '') AS student_prefix,
                s.firstname AS student_firstname, s.lastname AS student_lastname,
                rw.workflow_name
         FROM document_review_processes drp
         INNER JOIN documents d ON d.document_id = drp.document_id
         INNER JOIN student s ON s.student_id = d.student_id
         LEFT JOIN prefixes sp ON sp.prefix_id = s.prefix_id
         INNER JOIN document_template t ON t.template_id = d.template_id
         INNER JOIN document_type dt ON dt.document_type_id = t.document_type_id
         INNER JOIN review_workflows rw ON rw.workflow_id = drp.workflow_id
         WHERE drp.document_id = ? LIMIT 1"
    );
    $processStatement->execute([$documentId]);
    $process = $processStatement->fetch(PDO::FETCH_ASSOC);
    if (!$process) {
        return null;
    }

    $stepStatement = $pdo->prepare(
        "SELECT dsi.*,
                TRIM(CONCAT(
                    CASE WHEN pf.prefix_name IN ('นาย','นาง','นางสาว') THEN 'อาจารย์'
                         ELSE COALESCE(NULLIF(pf.prefix_short,''),NULLIF(pf.prefix_name,''),'') END,
                    CASE WHEN e.employee_id IS NULL THEN '' ELSE ' ' END,
                    COALESCE(e.firstname,''),
                    CASE WHEN e.employee_id IS NULL THEN '' ELSE ' ' END,
                    COALESCE(e.lastname,'')
                )) AS reviewer_name,
                (SELECT dra.detail
                 FROM document_events dra
                 WHERE dra.document_step_id = dsi.document_step_id
                   AND dra.detail IS NOT NULL AND dra.detail <> ''
                 ORDER BY dra.created_at DESC, dra.event_id DESC LIMIT 1) AS latest_comment
         FROM document_review_step_instances dsi
         LEFT JOIN employee e ON e.employee_id = dsi.reviewer_employee_id
         LEFT JOIN prefixes pf ON pf.prefix_id = e.prefix_id
         WHERE dsi.process_id = ? ORDER BY dsi.step_order"
    );
    $stepStatement->execute([(int) $process['process_id']]);
    $process['steps'] = $stepStatement->fetchAll(PDO::FETCH_ASSOC);

    $actionStatement = $pdo->prepare(
        "SELECT dra.event_type AS action_type, dra.detail AS action_comment, dra.created_at,
                COALESCE(
                    NULLIF(TRIM(CONCAT(
                        CASE WHEN ep.prefix_name IN ('นาย','นาง','นางสาว') AND ae.employee_type='teacher' THEN 'อาจารย์'
                             ELSE COALESCE(NULLIF(ep.prefix_short,''),NULLIF(ep.prefix_name,''),'') END,
                        ' ', ae.firstname, ' ', ae.lastname
                    )), ''),
                    NULLIF(TRIM(CONCAT(COALESCE(ssp.prefix_short,ssp.prefix_name,''), ss.firstname, ' ', ss.lastname)), ''),
                    u.username,
                    'ระบบ'
                ) AS actor_name,
                dsi.step_name
         FROM document_events dra
         INNER JOIN document_review_step_instances dsi ON dsi.document_step_id = dra.document_step_id
         LEFT JOIN users u ON u.user_id = dra.actor_user_id
         LEFT JOIN employee ae ON ae.user_id = u.user_id
         LEFT JOIN prefixes ep ON ep.prefix_id = ae.prefix_id
         LEFT JOIN student ss ON ss.user_id = u.user_id
         LEFT JOIN prefixes ssp ON ssp.prefix_id = ss.prefix_id
         WHERE dra.document_id = ?
         ORDER BY dra.created_at DESC, dra.event_id DESC"
    );
    $actionStatement->execute([$documentId]);
    $process['actions'] = $actionStatement->fetchAll(PDO::FETCH_ASSOC);

    return $process;
}

function workflowProcessStatusMeta(string $status): array
{
    return match ($status) {
        'pending' => ['รอเริ่มพิจารณา', 'bg-slate-100 text-slate-700', 'fa-clock'],
        'in_review' => ['อยู่ระหว่างพิจารณา', 'bg-blue-100 text-blue-700', 'fa-hourglass-half'],
        'revision_requested' => ['ส่งกลับให้แก้ไข', 'bg-red-100 text-red-700', 'fa-rotate-left'],
        'approved' => ['ผ่านการพิจารณาแล้ว', 'bg-emerald-100 text-emerald-700', 'fa-circle-check'],
        'cancelled' => ['ยกเลิก', 'bg-slate-100 text-slate-500', 'fa-ban'],
        default => [$status, 'bg-slate-100 text-slate-600', 'fa-circle'],
    };
}

function workflowStepStatusMeta(string $status): array
{
    return match ($status) {
        'waiting' => ['รอตามลำดับ', 'bg-slate-100 text-slate-500', 'fa-clock'],
        'current' => ['กำลังรอพิจารณา', 'bg-blue-100 text-blue-700', 'fa-hourglass-half'],
        'revision_requested' => ['ส่งกลับให้แก้ไข', 'bg-red-100 text-red-700', 'fa-rotate-left'],
        'approved' => ['อนุมัติแล้ว', 'bg-emerald-100 text-emerald-700', 'fa-check'],
        'skipped' => ['ข้ามขั้นตอน', 'bg-amber-100 text-amber-700', 'fa-forward'],
        default => [$status, 'bg-slate-100 text-slate-600', 'fa-circle'],
    };
}
