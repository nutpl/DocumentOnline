-- เปลี่ยนลำดับ RMUTI_Pro-Ex_02 ให้ผ่านอาจารย์ที่ปรึกษาก่อนพิมพ์และลงชื่อ

ALTER TABLE internship_application
    MODIFY status ENUM('draft','in_review','waiting_signature','submitted')
    NOT NULL DEFAULT 'draft';

-- เอกสารจาก Workflow เดิมที่กำลังรออาจารย์ ให้แสดงสถานะกำลังพิจารณา
-- โดยไม่ลบไฟล์หรือข้อมูลเดิมของผู้ใช้
UPDATE internship_application ia
INNER JOIN documents d ON d.document_id = ia.document_id
SET ia.status = 'in_review'
WHERE ia.status = 'submitted'
  AND d.document_status = 'in_review';
