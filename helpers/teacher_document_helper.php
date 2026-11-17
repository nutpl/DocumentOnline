<?php

function getTeacherDocumentForReview(PDO $pdo, int $userId, int $documentId): ?array
{
    $teacherStmt=$pdo->prepare('SELECT employee_id,department_id,employee_code,firstname,lastname FROM employee WHERE user_id=? AND employee_type="teacher" LIMIT 1');
    $teacherStmt->execute([$userId]);
    $teacher=$teacherStmt->fetch(PDO::FETCH_ASSOC);
    if(!$teacher)return null;

    $workflowStmt=$pdo->prepare('SELECT d.document_id,d.document_status,d.submitted_at,
        s.user_id student_user_id,s.student_number,s.firstname,s.lastname,
        dt.document_code,dt.document_name,dsi.step_name workflow_step_name,dsi.step_order workflow_step_order
        FROM document_review_processes drp
        JOIN document_review_step_instances dsi ON dsi.process_id=drp.process_id AND dsi.step_order=drp.current_step_order
        JOIN documents d ON d.document_id=drp.document_id
        JOIN student s ON s.student_id=d.student_id
        JOIN document_template t ON t.template_id=d.template_id
        JOIN document_type dt ON dt.document_type_id=t.document_type_id
        WHERE d.document_id=? AND drp.process_status="in_review" AND dsi.step_status="current"
          AND dsi.reviewer_user_id=? LIMIT 1');
    $workflowStmt->execute([$documentId,$userId]);
    $workflowDocument=$workflowStmt->fetch(PDO::FETCH_ASSOC);
    if($workflowDocument){$workflowDocument['teacher']=$teacher;return $workflowDocument;}

    return null;
}
