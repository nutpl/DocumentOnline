-- Repair requests that were returned before the full re-review rule existed.
-- Run once after deploying the PHP changes.  It returns only unsigned,
-- previously returned requests to draft/revision; valid signed submissions
-- are not affected.

UPDATE document_review_step_instances dsi
INNER JOIN document_review_processes drp ON drp.process_id = dsi.process_id
INNER JOIN internship_application ia ON ia.document_id = drp.document_id
INNER JOIN documents d ON d.document_id = ia.document_id
SET dsi.step_status = CASE WHEN dsi.step_order = 1 THEN 'revision_requested' ELSE 'waiting' END,
    dsi.activated_at = NULL,
    dsi.reviewed_at = NULL
WHERE ia.status = 'waiting_signature'
  AND ia.signed_file IS NULL
  AND d.document_status IN ('approved', 'submitted')
  AND EXISTS (SELECT 1 FROM document_events de WHERE de.document_id = d.document_id AND de.event_type = 'signed_file_deleted');

UPDATE document_review_processes drp
INNER JOIN internship_application ia ON ia.document_id = drp.document_id
INNER JOIN documents d ON d.document_id = ia.document_id
SET drp.current_step_order = 1,
    drp.process_status = 'revision_requested',
    drp.completed_at = NULL,
    drp.revision_count = drp.revision_count + 1
WHERE ia.status = 'waiting_signature'
  AND ia.signed_file IS NULL
  AND d.document_status IN ('approved', 'submitted')
  AND EXISTS (SELECT 1 FROM document_events de WHERE de.document_id = d.document_id AND de.event_type = 'signed_file_deleted');

UPDATE internship_application ia
INNER JOIN documents d ON d.document_id = ia.document_id
SET ia.status = 'draft',
    ia.submitted_at = NULL,
    d.document_status = 'revision_requested',
    d.updated_at = NOW()
WHERE ia.status = 'waiting_signature'
  AND ia.signed_file IS NULL
  AND d.document_status IN ('approved', 'submitted')
  AND EXISTS (SELECT 1 FROM document_events de WHERE de.document_id = d.document_id AND de.event_type = 'signed_file_deleted');
