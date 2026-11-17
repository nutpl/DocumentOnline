<?php

function teacherCoordinatorProfile(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT DISTINCT e.employee_id,e.department_id,e.firstname,e.lastname
         FROM employee e
         INNER JOIN employee_position ep ON ep.employee_id=e.employee_id
         INNER JOIN positions p ON p.position_id=ep.position_id
         INNER JOIN users u ON u.user_id=e.user_id AND u.status="active"
         WHERE e.user_id=? AND e.employee_type="teacher"
           AND p.position_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ"
         LIMIT 1'
    );
    $statement->execute([$userId]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);
    return $profile ?: null;
}

function teacherCoordinatorRequests(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare(
        'SELECT d.document_id,d.document_status,d.submitted_at,
                s.student_number,s.firstname,s.lastname,
                COALESCE(sp.prefix_short,sp.prefix_name,"") student_prefix,
                dt.document_code,dt.document_name,dsi.step_status,
                dsi.activated_at,dsi.reviewed_at,
                (SELECT de.detail FROM document_events de
                 WHERE de.document_id=d.document_id
                   AND de.document_step_id=dsi.document_step_id
                   AND de.event_type="revision_requested"
                 ORDER BY de.event_id DESC LIMIT 1) revision_comment
         FROM document_review_step_instances dsi
         INNER JOIN document_review_processes drp ON drp.process_id=dsi.process_id
         INNER JOIN documents d ON d.document_id=drp.document_id
         INNER JOIN student s ON s.student_id=d.student_id
         LEFT JOIN prefixes sp ON sp.prefix_id=s.prefix_id
         INNER JOIN document_template t ON t.template_id=d.template_id
         INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
         WHERE dsi.reviewer_user_id=?
           AND UPPER(dt.document_code)="INTERNSHIP_INFO"
           AND (dsi.role_code="internship_coordinator"
                OR dsi.step_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ")
           AND dsi.step_status IN ("current","approved","revision_requested")
         ORDER BY (dsi.step_status="current") DESC,
                  COALESCE(dsi.activated_at,d.submitted_at) DESC,d.document_id DESC'
    );
    $statement->execute([$userId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function teacherCoordinatorPendingCount(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM document_review_step_instances dsi
         INNER JOIN document_review_processes drp ON drp.process_id=dsi.process_id
         INNER JOIN documents d ON d.document_id=drp.document_id
         INNER JOIN document_template t ON t.template_id=d.template_id
         INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
         WHERE dsi.reviewer_user_id=? AND dsi.step_status="current"
           AND drp.process_status="in_review"
           AND UPPER(dt.document_code)="INTERNSHIP_INFO"
           AND (dsi.role_code="internship_coordinator"
                OR dsi.step_name="อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ")'
    );
    $statement->execute([$userId]);
    return (int) $statement->fetchColumn();
}
