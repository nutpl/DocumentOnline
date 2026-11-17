-- ปรับ Workflow ตามขอบเขตฉบับล่าสุด: อาจารย์ที่ปรึกษาอนุมัติเพียงคนเดียว
-- สร้างเวอร์ชันใหม่และรักษาเวอร์ชันเดิมไว้เป็นประวัติ

DELIMITER $$

DROP PROCEDURE IF EXISTS activate_advisor_only_workflow$$
CREATE PROCEDURE activate_advisor_only_workflow(IN p_document_code VARCHAR(50), IN p_admin_user_id INT)
BEGIN
    DECLARE v_document_type_id INT DEFAULT NULL;
    DECLARE v_active_workflow_id INT DEFAULT NULL;
    DECLARE v_active_step_count INT DEFAULT 0;
    DECLARE v_active_advisor_count INT DEFAULT 0;
    DECLARE v_next_version INT DEFAULT 1;
    DECLARE v_new_workflow_id INT DEFAULT NULL;
    DECLARE v_document_name VARCHAR(255) DEFAULT '';

    SELECT document_type_id, document_name
      INTO v_document_type_id, v_document_name
      FROM document_type
     WHERE REPLACE(UPPER(document_code), '.', '') = REPLACE(UPPER(p_document_code), '.', '')
       AND status = 'active'
     LIMIT 1;

    IF v_document_type_id IS NOT NULL THEN
        SELECT workflow_id
          INTO v_active_workflow_id
          FROM review_workflows
         WHERE document_type_id = v_document_type_id AND workflow_status = 'active'
         ORDER BY version_no DESC
         LIMIT 1;

        IF v_active_workflow_id IS NOT NULL THEN
            SELECT COUNT(*), SUM(reviewer_source = 'student_advisor')
              INTO v_active_step_count, v_active_advisor_count
              FROM review_workflow_steps
             WHERE workflow_id = v_active_workflow_id;
        END IF;

        IF v_active_workflow_id IS NULL OR v_active_step_count <> 1 OR v_active_advisor_count <> 1 THEN
            SELECT COALESCE(MAX(version_no), 0) + 1
              INTO v_next_version
              FROM review_workflows
             WHERE document_type_id = v_document_type_id;

            UPDATE review_workflows
               SET workflow_status = 'inactive'
             WHERE document_type_id = v_document_type_id AND workflow_status = 'active';

            INSERT INTO review_workflows
                (document_type_id, workflow_name, version_no, workflow_status, revision_return_policy, created_by)
            VALUES
                (v_document_type_id, CONCAT(p_document_code, ' - ', v_document_name), v_next_version, 'active', 'same_step', p_admin_user_id);

            SET v_new_workflow_id = LAST_INSERT_ID();
            INSERT INTO review_workflow_steps
                (workflow_id, step_order, role_code, step_name, reviewer_source,
                 reviewer_employee_id, reviewer_position_id, scope_type, is_required)
            VALUES
                (v_new_workflow_id, 1, 'step_1', 'อาจารย์ที่ปรึกษา', 'student_advisor',
                 NULL, NULL, 'department', 1);
        END IF;
    END IF;
END$$

DELIMITER ;

START TRANSACTION;
CALL activate_advisor_only_workflow('R01', 1);
CALL activate_advisor_only_workflow('R12', 1);
CALL activate_advisor_only_workflow('R14', 1);
CALL activate_advisor_only_workflow('R18', 1);
CALL activate_advisor_only_workflow('R24', 1);
CALL activate_advisor_only_workflow('INTERNSHIP_INFO', 1);

-- เอกสารที่ยังอยู่ขั้นอาจารย์ที่ปรึกษา: ยกเลิกเฉพาะขั้นถัดไปที่ยังไม่เริ่ม
INSERT INTO document_events (document_id, document_step_id, actor_user_id, event_type, detail)
SELECT drp.document_id, dsi.document_step_id, 1, 'skipped',
       'ยกเลิกขั้นตอนที่ยังไม่เริ่ม เนื่องจากปรับขอบเขตให้ผ่านอาจารย์ที่ปรึกษาเพียงคนเดียว'
  FROM document_review_processes drp
  JOIN document_review_step_instances dsi ON dsi.process_id = drp.process_id
  JOIN documents d ON d.document_id = drp.document_id
  JOIN document_template t ON t.template_id = d.template_id
  JOIN document_type dt ON dt.document_type_id = t.document_type_id
 WHERE drp.process_status IN ('in_review', 'revision_requested')
   AND drp.current_step_order = 1
   AND dsi.step_order > 1
   AND dsi.step_status = 'waiting'
   AND REPLACE(UPPER(dt.document_code), '.', '') IN ('R01','R12','R14','R18','R24','INTERNSHIP_INFO');

UPDATE document_review_step_instances dsi
JOIN document_review_processes drp ON drp.process_id = dsi.process_id
JOIN documents d ON d.document_id = drp.document_id
JOIN document_template t ON t.template_id = d.template_id
JOIN document_type dt ON dt.document_type_id = t.document_type_id
   SET dsi.step_status = 'skipped', dsi.reviewed_at = NOW()
 WHERE drp.process_status IN ('in_review', 'revision_requested')
   AND drp.current_step_order = 1
   AND dsi.step_order > 1
   AND dsi.step_status = 'waiting'
   AND REPLACE(UPPER(dt.document_code), '.', '') IN ('R01','R12','R14','R18','R24','INTERNSHIP_INFO');

COMMIT;
DROP PROCEDURE IF EXISTS activate_advisor_only_workflow;

