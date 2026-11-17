USE online_request_system;

UPDATE document_type SET document_name='คำร้องขอลาป่วย/ลากิจ' WHERE document_code='R24';
UPDATE document_type SET document_name='คำร้องขอเปิดรายวิชาเรียน' WHERE document_code='R18';

INSERT INTO document_template
    (document_type_id, template_name, pdf_template, original_file, total_pages, file_type, status)
SELECT document_type_id, 'R.18 คำร้องขอเปิดรายวิชาเรียน', 'R18_official.pdf', 'R18_official.pdf', 3, 'pdf', 'active'
FROM document_type WHERE document_code='R18'
AND NOT EXISTS (SELECT 1 FROM document_template t WHERE t.document_type_id=document_type.document_type_id);

INSERT INTO document_template
    (document_type_id, template_name, pdf_template, original_file, total_pages, file_type, status)
SELECT document_type_id, 'R.24 คำร้องขอลาป่วย/ลากิจ', 'R24_official.pdf', 'R24_official.pdf', 1, 'pdf', 'active'
FROM document_type WHERE document_code='R24'
AND NOT EXISTS (SELECT 1 FROM document_template t WHERE t.document_type_id=document_type.document_type_id);

SET @r18 := (SELECT t.template_id FROM document_template t JOIN document_type d ON d.document_type_id=t.document_type_id WHERE d.document_code='R18' ORDER BY t.template_id DESC LIMIT 1);
SET @r24 := (SELECT t.template_id FROM document_template t JOIN document_type d ON d.document_type_id=t.document_type_id WHERE d.document_code='R24' ORDER BY t.template_id DESC LIMIT 1);

DELETE FROM template_fields WHERE template_id IN (@r18,@r24);

INSERT INTO template_fields
    (template_id,field_name,field_label,field_type,page_no,pos_x,pos_y,width,height,font_size,required_field,default_value)
VALUES
(@r18,'request_date','วันที่ยื่นคำร้อง','date',1,525,150,280,24,11,1,NULL),
(@r18,'subject_semester','ภาคการศึกษาที่ขอเปิด','text',1,330,202,90,24,11,1,NULL),
(@r18,'subject_academic_year','ปีการศึกษาที่ขอเปิด','text',1,610,202,110,24,11,1,NULL),
(@r18,'recipient_faculty','เรียน คณบดีคณะ','text',1,260,235,520,24,11,1,NULL),
(@r18,'student_name','ชื่อ-นามสกุลผู้ยื่น','text',1,300,275,300,24,11,1,NULL),
(@r18,'student_number','รหัสนักศึกษา','text',1,650,275,150,24,11,1,NULL),
(@r18,'telephone','โทรศัพท์','text',1,785,275,100,24,11,1,NULL),
(@r18,'education_level','ระดับการศึกษา','text',1,225,315,24,24,11,1,'undergraduate'),
(@r18,'year_level','ชั้นปี','text',1,655,315,55,24,11,1,NULL),
(@r18,'study_type','ภาคการศึกษา','text',1,745,315,100,24,11,1,'normal'),
(@r18,'request_semester','ภาคการศึกษาที่ประสงค์ขอเปิด','text',1,500,352,80,24,11,1,NULL),
(@r18,'request_academic_year','ปีการศึกษาที่ประสงค์ขอเปิด','text',1,700,352,100,24,11,1,NULL),
(@r18,'course_code','รหัสวิชา','text',1,245,385,180,24,11,1,NULL),
(@r18,'course_name','ชื่อรายวิชา','text',1,500,385,260,24,11,1,NULL),
(@r18,'theory_credits','หน่วยกิตทฤษฎี','number',1,820,385,50,24,11,0,NULL),
(@r18,'practical_credits','หน่วยกิตปฏิบัติ','number',1,245,415,50,24,11,0,NULL),
(@r18,'total_credits','หน่วยกิตรวม','number',1,405,415,50,24,11,0,NULL),
(@r18,'theory_hours','ชั่วโมงทฤษฎีต่อสัปดาห์','number',1,650,415,50,24,11,0,NULL),
(@r18,'practical_hours','ชั่วโมงปฏิบัติต่อสัปดาห์','number',1,820,415,50,24,11,0,NULL),
(@r18,'total_hours','ชั่วโมงรวมต่อสัปดาห์','number',1,230,446,50,24,11,0,NULL),
(@r18,'planned_year_level','ชั้นปีตามแผนการเรียน','text',1,600,446,60,24,11,0,NULL),
(@r18,'planned_semester','ภาคการศึกษาตามแผน','text',1,790,446,60,24,11,0,NULL),
(@r18,'reason','เหตุผลในการขอเปิดรายวิชา','textarea',1,180,480,690,48,10,1,NULL),
(@r18,'responsible_faculty','คณะที่รับผิดชอบรายวิชา','text',1,365,535,250,24,11,1,NULL),
(@r18,'responsible_department','สาขาวิชาที่รับผิดชอบ','text',1,690,535,180,24,11,1,NULL),
(@r18,'student_count','จำนวนนักศึกษาที่แนบรายชื่อ','number',1,670,570,70,24,11,1,NULL),
(@r18,'contact_phone','เบอร์โทรศัพท์สำหรับติดต่อ','text',1,190,635,180,24,11,1,NULL),
(@r18,'signature_name','ชื่อสำหรับลงนาม','text',1,555,620,260,24,11,1,NULL),
(@r18,'instructor_employee_id','อาจารย์ผู้สอน','number',1,0,0,1,1,1,0,NULL),
(@r18,'advisor_print_name','ชื่ออาจารย์ที่ปรึกษาในวงเล็บ','text',1,168,834,238,22,10,0,NULL),
(@r18,'instructor_print_name','ชื่ออาจารย์ผู้สอนในวงเล็บ','text',1,572,861,238,22,10,0,NULL),
(@r18,'department_head_print_name','ชื่อหัวหน้าสาขาในวงเล็บ','text',1,168,1041,238,22,10,0,NULL),
(@r18,'dean_print_name','ชื่อคณบดีในวงเล็บ','text',1,572,1068,238,22,10,0,NULL),

(@r18,'attachment_semester','ภาคการศึกษาในใบรายชื่อ','text',3,350,95,90,24,10,1,NULL),
(@r18,'attachment_academic_year','ปีการศึกษาในใบรายชื่อ','text',3,650,95,100,24,10,1,NULL),
(@r18,'attachment_course_code','รหัสวิชาในใบรายชื่อ','text',3,210,125,180,24,10,1,NULL),
(@r18,'attachment_course_name','ชื่อรายวิชาในใบรายชื่อ','text',3,525,125,280,24,10,1,NULL),

(@r24,'request_date','วันที่ยื่นคำร้อง','date',1,530,155,280,24,11,1,NULL),
(@r24,'instructor_employee_id','อาจารย์ผู้สอนรายวิชา','number',1,0,0,1,1,1,1,NULL),
(@r24,'instructor_course','รายวิชาที่ขอลา','text',1,270,205,570,24,11,1,NULL),
(@r24,'student_name','ชื่อ-นามสกุลผู้ยื่น','text',1,300,250,310,24,11,1,NULL),
(@r24,'student_number','รหัสนักศึกษา','text',1,680,250,160,24,11,1,NULL),
(@r24,'education_level','ระดับการศึกษา','text',1,205,290,24,24,11,1,'undergraduate'),
(@r24,'faculty','คณะ','text',1,120,330,260,24,11,1,NULL),
(@r24,'curriculum','หลักสูตร','text',1,415,330,250,24,11,1,NULL),
(@r24,'telephone','โทรศัพท์','text',1,710,330,130,24,11,1,NULL),
(@r24,'leave_type','ประเภทการลา','text',1,200,375,80,24,11,1,'sick'),
(@r24,'sick_reason','เหตุผลการลาป่วย','textarea',1,350,375,470,45,10,0,NULL),
(@r24,'personal_reason','เหตุผลการลากิจ','textarea',1,350,445,470,45,10,0,NULL),
(@r24,'leave_days','จำนวนวันลา','number',1,250,505,60,24,11,1,NULL),
(@r24,'leave_from','ตั้งแต่วันที่','date',1,440,505,150,24,11,1,NULL),
(@r24,'leave_to','ถึงวันที่','date',1,680,505,150,24,11,1,NULL),
(@r24,'signature_name','ชื่อสำหรับลงนามนักศึกษา','text',1,540,570,260,24,11,1,NULL),
(@r24,'guardian_name','ชื่อ-นามสกุลผู้ปกครอง','text',1,300,655,290,24,11,0,NULL),
(@r24,'guardian_relationship','ความเกี่ยวข้องกับนักศึกษา','text',1,690,655,130,24,11,0,NULL),
(@r24,'guardian_signature_name','ชื่อสำหรับลงนามผู้ปกครอง','text',1,540,720,260,24,11,0,NULL),
(@r24,'advisor_print_name','ชื่ออาจารย์ที่ปรึกษา','text',1,160,856,230,22,10,0,NULL),
(@r24,'department_head_print_name','ชื่อหัวหน้าสาขา','text',1,530,856,230,22,10,0,NULL),
(@r24,'instructor_print_name','ชื่ออาจารย์ผู้สอน','text',1,330,965,240,22,10,0,NULL);
