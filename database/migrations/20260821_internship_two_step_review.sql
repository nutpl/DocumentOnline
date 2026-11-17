-- คำร้องสหกิจต้องผ่านอาจารย์ที่ปรึกษา และอาจารย์ประสานงานตามลำดับ
-- เอกสารที่สร้างไปแล้วจะคงประวัติเดิม และเปิดขั้นอาจารย์ประสานงานที่เคยถูกข้าม

START TRANSACTION;

SET @internship_type_id := (
    SELECT document_type_id FROM document_type
    WHERE UPPER(document_code) = 'INTERNSHIP_INFO' LIMIT 1
);
SET @coordinator_position_id := (
    SELECT position_id FROM positions
    WHERE position_name = 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ' LIMIT 1
);
SET @has_two_step_active := (
    SELECT COUNT(*) FROM review_workflows rw
    WHERE rw.document_type_id = @internship_type_id
      AND rw.workflow_status = 'active'
      AND (SELECT COUNT(*) FROM review_workflow_steps rws WHERE rws.workflow_id=rw.workflow_id) = 2
      AND EXISTS (
          SELECT 1 FROM review_workflow_steps rws
          WHERE rws.workflow_id=rw.workflow_id AND rws.step_order=1
            AND rws.reviewer_source='student_advisor'
      )
      AND EXISTS (
          SELECT 1 FROM review_workflow_steps rws
          WHERE rws.workflow_id=rw.workflow_id AND rws.step_order=2
            AND rws.reviewer_source='office_position'
            AND rws.reviewer_position_id=@coordinator_position_id
      )
);
SET @needs_new_workflow := IF(@has_two_step_active=0,1,0);
SET @new_version := (
    SELECT COALESCE(MAX(version_no),0)+1 FROM review_workflows
    WHERE document_type_id=@internship_type_id
);

UPDATE review_workflows
SET workflow_status='inactive'
WHERE document_type_id=@internship_type_id
  AND workflow_status='active'
  AND @needs_new_workflow=1;

INSERT INTO review_workflows
    (document_type_id,workflow_name,version_no,workflow_status,revision_return_policy,created_by)
SELECT @internship_type_id,'พิจารณาคำร้องสหกิจ 2 ขั้น',@new_version,'active','same_step',1
WHERE @needs_new_workflow=1;

SET @new_workflow_id := IF(
    @needs_new_workflow=1,
    LAST_INSERT_ID(),
    (SELECT workflow_id FROM review_workflows
     WHERE document_type_id=@internship_type_id AND workflow_status='active'
     ORDER BY version_no DESC LIMIT 1)
);

INSERT INTO review_workflow_steps
    (workflow_id,step_order,role_code,step_name,reviewer_source,reviewer_employee_id,reviewer_position_id,scope_type,is_required)
SELECT @new_workflow_id,1,'advisor','อาจารย์ที่ปรึกษา','student_advisor',NULL,NULL,'department',1
WHERE @needs_new_workflow=1;

INSERT INTO review_workflow_steps
    (workflow_id,step_order,role_code,step_name,reviewer_source,reviewer_employee_id,reviewer_position_id,scope_type,is_required)
SELECT @new_workflow_id,2,'internship_coordinator','อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ','office_position',NULL,@coordinator_position_id,'department',1
WHERE @needs_new_workflow=1;

-- เอกสารเดิมที่ยังรออาจารย์ที่ปรึกษา ให้ส่งต่ออาจารย์ประสานงานหลังผ่านขั้นแรก
UPDATE document_review_step_instances dsi
INNER JOIN document_review_processes drp ON drp.process_id=dsi.process_id
INNER JOIN documents d ON d.document_id=drp.document_id
INNER JOIN document_template t ON t.template_id=d.template_id
INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
SET dsi.step_status='waiting',dsi.reviewed_at=NULL
WHERE UPPER(dt.document_code)='INTERNSHIP_INFO'
  AND drp.process_status='in_review'
  AND drp.current_step_order=1
  AND dsi.step_order=2
  AND dsi.step_status='skipped';

-- เอกสารที่เคยผ่านเพียงอาจารย์ที่ปรึกษา แต่ยังไม่ได้อัปโหลดให้เจ้าหน้าที่
-- ให้กลับมารออาจารย์ประสานงานตรวจในระบบก่อนปลดล็อกการพิมพ์
UPDATE document_review_step_instances dsi
INNER JOIN document_review_processes drp ON drp.process_id=dsi.process_id
INNER JOIN documents d ON d.document_id=drp.document_id
INNER JOIN internship_application ia ON ia.document_id=d.document_id
INNER JOIN document_template t ON t.template_id=d.template_id
INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
SET dsi.step_status='current',dsi.attempt_count=dsi.attempt_count+1,
    dsi.activated_at=NOW(),dsi.reviewed_at=NULL
WHERE UPPER(dt.document_code)='INTERNSHIP_INFO'
  AND drp.process_status='approved'
  AND ia.status='waiting_signature'
  AND dsi.step_order=2
  AND dsi.step_status='skipped';

UPDATE document_review_processes drp
INNER JOIN documents d ON d.document_id=drp.document_id
INNER JOIN internship_application ia ON ia.document_id=d.document_id
INNER JOIN document_review_step_instances dsi
    ON dsi.process_id=drp.process_id AND dsi.step_order=2 AND dsi.step_status='current'
INNER JOIN document_template t ON t.template_id=d.template_id
INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
SET drp.current_step_order=2,drp.process_status='in_review',drp.completed_at=NULL,
    d.document_status='in_review',ia.status='in_review'
WHERE UPPER(dt.document_code)='INTERNSHIP_INFO'
  AND drp.process_status='approved'
  AND ia.status='waiting_signature';

INSERT INTO document_events
    (document_id,document_step_id,actor_user_id,event_type,detail)
SELECT drp.document_id,dsi.document_step_id,NULL,'activated',
       'เพิ่มขั้นอาจารย์ประสานงานตามกระบวนการสหกิจฉบับปรับปรุง'
FROM document_review_processes drp
INNER JOIN document_review_step_instances dsi
    ON dsi.process_id=drp.process_id AND dsi.step_order=2 AND dsi.step_status='current'
INNER JOIN documents d ON d.document_id=drp.document_id
INNER JOIN document_template t ON t.template_id=d.template_id
INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
WHERE UPPER(dt.document_code)='INTERNSHIP_INFO'
  AND drp.process_status='in_review'
  AND NOT EXISTS (
      SELECT 1 FROM document_events de
      WHERE de.document_id=drp.document_id
        AND de.document_step_id=dsi.document_step_id
        AND de.event_type='activated'
        AND de.detail='เพิ่มขั้นอาจารย์ประสานงานตามกระบวนการสหกิจฉบับปรับปรุง'
  );

INSERT INTO notifications
    (recipient_user_id,document_id,notification_type,title,message,target_url)
SELECT dsi.reviewer_user_id,drp.document_id,'workflow_step_assigned',
       'มีคำร้องสหกิจรอการพิจารณา',
       CONCAT('คำร้อง INTERNSHIP_INFO เลขที่ #',drp.document_id,
              ' รอพิจารณาในขั้น “อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ”'),
       CONCAT('/Project/teacher/request_review.php?document=',drp.document_id)
FROM document_review_processes drp
INNER JOIN document_review_step_instances dsi
    ON dsi.process_id=drp.process_id AND dsi.step_order=2 AND dsi.step_status='current'
INNER JOIN documents d ON d.document_id=drp.document_id
INNER JOIN document_template t ON t.template_id=d.template_id
INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
WHERE UPPER(dt.document_code)='INTERNSHIP_INFO'
  AND drp.process_status='in_review'
  AND dsi.reviewer_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.document_id=drp.document_id
        AND n.recipient_user_id=dsi.reviewer_user_id
        AND n.notification_type='workflow_step_assigned'
        AND n.is_read=0
  );

-- แก้การแจ้งเตือนเดิมที่เคยบอกว่าผ่านครบ ทั้งที่เพิ่งผ่านที่ปรึกษา
UPDATE notifications n
INNER JOIN documents d ON d.document_id=n.document_id
INNER JOIN student s ON s.student_id=d.student_id AND s.user_id=n.recipient_user_id
INNER JOIN internship_application ia ON ia.document_id=d.document_id
INNER JOIN document_review_processes drp ON drp.document_id=d.document_id
INNER JOIN document_template t ON t.template_id=d.template_id
INNER JOIN document_type dt ON dt.document_type_id=t.document_type_id
SET n.notification_type='review_step_approved',
    n.title='คำร้องสหกิจผ่านอาจารย์ที่ปรึกษาแล้ว',
    n.message='คำร้องสหกิจผ่านอาจารย์ที่ปรึกษาแล้ว และกำลังรออาจารย์ประสานงานฝึกประสบการณ์วิชาชีพพิจารณา',
    n.target_url='/Project/student/history.php',n.is_read=0,n.read_at=NULL
WHERE UPPER(dt.document_code)='INTERNSHIP_INFO'
  AND drp.process_status='in_review'
  AND ia.status='in_review'
  AND n.notification_type='document_approved';

-- ปิดการแจ้งเตือนแบบรับทราบเดิมของอาจารย์ประสานงาน เพราะมีรายการรอพิจารณาใหม่แล้ว
UPDATE notifications n
INNER JOIN document_review_processes drp ON drp.document_id=n.document_id
INNER JOIN document_review_step_instances dsi
    ON dsi.process_id=drp.process_id AND dsi.step_order=2
    AND dsi.reviewer_user_id=n.recipient_user_id AND dsi.step_status='current'
SET n.is_read=1,n.read_at=COALESCE(n.read_at,NOW())
WHERE drp.process_status='in_review'
  AND n.notification_type='document_advisor_approved';

COMMIT;
