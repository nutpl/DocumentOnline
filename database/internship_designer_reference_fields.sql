-- Reference overlays for the three fixed-layout internship PDFs.
-- These rows are intentionally metadata only: PDF downloads continue to use
-- the established coordinates in helpers/internship_pdf_helper.php and
-- helpers/official_internship_pdf_helper.php.

USE online_request_system;
START TRANSACTION;

SET @request_template := (
    SELECT t.template_id FROM document_template t
    JOIN document_type d ON d.document_type_id=t.document_type_id
    WHERE d.document_code='INTERNSHIP_REQUEST'
    ORDER BY (t.status='active') DESC,t.template_id DESC LIMIT 1
);
SET @send_template := (
    SELECT t.template_id FROM document_template t
    JOIN document_type d ON d.document_type_id=t.document_type_id
    WHERE d.document_code='INTERNSHIP_SEND'
    ORDER BY (t.status='active') DESC,t.template_id DESC LIMIT 1
);
SET @info_template := (
    SELECT t.template_id FROM document_template t
    JOIN document_type d ON d.document_type_id=t.document_type_id
    WHERE d.document_code='INTERNSHIP_INFO'
    ORDER BY (t.status='active') DESC,t.template_id DESC LIMIT 1
);

INSERT IGNORE INTO template_fields
    (template_id,field_name,field_label,field_type,page_no,pos_x,pos_y,width,height,font_size,required_field,default_value)
VALUES
(@request_template,'document_number','เลขหนังสือ','text',1,123,229,340,28,12,0,NULL),
(@request_template,'document_date','วันที่ออกหนังสือ','date',1,385,368,298,28,12,0,NULL),
(@request_template,'subject','เรื่อง','text',1,200,406,608,28,12,0,NULL),
(@request_template,'recipient','ผู้รับหนังสือ','text',1,200,443,608,28,12,0,NULL),
(@request_template,'attachments','สิ่งที่ส่งมาด้วย','textarea',1,238,479,480,82,11,0,NULL),
(@request_template,'introduction','ย่อหน้าเกริ่นนำ','textarea',1,123,572,680,82,11,0,NULL),
(@request_template,'request_detail','รายละเอียดขอความอนุเคราะห์','textarea',1,123,654,680,105,11,0,NULL),
(@request_template,'student_names','รายชื่อนักศึกษา','textarea',1,230,760,510,82,11,0,NULL),
(@request_template,'response_due_date','กำหนดส่งแบบตอบรับ','date',1,129,842,680,28,11,0,NULL),
(@request_template,'closing','ข้อความลงท้าย','textarea',1,123,871,680,65,11,0,NULL),
(@request_template,'respectfully','คำลงท้ายหนังสือ','text',1,382,929,340,28,12,0,NULL),
(@request_template,'signatory_name','ชื่อผู้ลงนาม','text',1,353,1011,417,28,12,0,NULL),
(@request_template,'signatory_title_line1','ตำแหน่งผู้ลงนามบรรทัดที่ 1','text',1,336,1038,451,28,11,0,NULL),
(@request_template,'signatory_title_line2','ตำแหน่งผู้ลงนามบรรทัดที่ 2','text',1,336,1065,451,28,11,0,NULL),
(@request_template,'faculty_contact','ข้อมูลติดต่อคณะ','textarea',1,123,1093,685,55,11,0,NULL),

(@send_template,'document_number','เลขหนังสือ','text',1,123,196,340,28,12,0,NULL),
(@send_template,'document_date','วันที่ออกหนังสือ','date',1,404,332,298,28,12,0,NULL),
(@send_template,'subject','เรื่อง','text',1,170,374,638,28,12,0,NULL),
(@send_template,'recipient','ผู้รับหนังสือ','text',1,170,412,638,28,12,0,NULL),
(@send_template,'reference_text','หนังสืออ้างถึง','text',1,170,451,638,28,11,0,NULL),
(@send_template,'attachments','สิ่งที่ส่งมาด้วย','text',1,221,489,587,28,11,0,NULL),
(@send_template,'introduction','รายละเอียดการส่งตัว','textarea',1,123,527,706,120,11,0,NULL),
(@send_template,'student_names','รายชื่อนักศึกษา','textarea',1,230,646,510,82,11,0,NULL),
(@send_template,'evaluation_detail','รายละเอียดการประเมิน','textarea',1,123,701,706,185,11,0,NULL),
(@send_template,'closing','ข้อความลงท้าย','textarea',1,123,887,706,55,11,0,NULL),
(@send_template,'respectfully','คำลงท้ายหนังสือ','text',1,391,897,340,28,12,0,NULL),
(@send_template,'signatory_name','ชื่อผู้ลงนาม','text',1,353,974,417,28,12,0,NULL),
(@send_template,'signatory_title_line1','ตำแหน่งผู้ลงนามบรรทัดที่ 1','text',1,336,1004,451,28,11,0,NULL),
(@send_template,'signatory_title_line2','ตำแหน่งผู้ลงนามบรรทัดที่ 2','text',1,336,1034,451,28,11,0,NULL),

(@info_template,'request_day','วันที่ยื่นคำร้อง','number',1,536,213,34,24,10,0,NULL),
(@info_template,'request_month','เดือนที่ยื่นคำร้อง','text',1,617,213,102,24,10,0,NULL),
(@info_template,'request_year','ปีที่ยื่นคำร้อง','number',1,795,213,34,24,10,0,NULL),
(@info_template,'student_firstname','คำนำหน้าและชื่อ','text',1,115,272,132,24,10,0,NULL),
(@info_template,'student_lastname','นามสกุล','text',1,327,272,123,24,10,0,NULL),
(@info_template,'student_number','รหัสนักศึกษา','text',1,583,272,136,24,10,0,NULL),
(@info_template,'year_level','ชั้นปี','number',1,770,272,51,24,10,0,NULL),
(@info_template,'department_name','สาขาวิชา','text',1,132,298,276,24,10,0,NULL),
(@info_template,'faculty_name','คณะ','text',1,459,298,196,24,10,0,NULL),
(@info_template,'student_phone','เบอร์โทรศัพท์','text',1,723,298,102,24,10,0,NULL),
(@info_template,'semester','ภาคการศึกษา','text',1,430,323,34,24,10,0,NULL),
(@info_template,'training_period','ระยะเวลาฝึกประสบการณ์','text',1,536,323,281,24,10,0,NULL),
(@info_template,'qualification_department','คุณสมบัติผ่านสาขา','checkbox',1,133,370,20,20,9,0,NULL),
(@info_template,'qualification_preparation','ผ่านการเตรียมความพร้อม','checkbox',1,133,395,20,20,9,0,NULL),
(@info_template,'qualification_gpa','คุณสมบัติคะแนนเฉลี่ย','checkbox',1,133,421,20,20,9,0,NULL),
(@info_template,'qualification_company','คุณสมบัติสถานประกอบการ','checkbox',1,133,446,20,20,9,0,NULL),
(@info_template,'recipient_name','ชื่อผู้รับหนังสือ','text',1,319,510,153,24,10,0,NULL),
(@info_template,'recipient_position','ตำแหน่งผู้รับหนังสือ','text',1,527,510,123,24,10,0,NULL),
(@info_template,'recipient_department','หน่วยงานผู้รับหนังสือ','text',1,719,510,106,24,10,0,NULL),
(@info_template,'company_name','ชื่อสถานประกอบการ','text',1,179,537,421,24,10,0,NULL),
(@info_template,'company_number','เลขที่','text',1,655,537,47,24,10,0,NULL),
(@info_template,'company_moo','หมู่','text',1,761,537,55,24,10,0,NULL),
(@info_template,'company_building','อาคาร','text',1,145,563,119,24,10,0,NULL),
(@info_template,'company_floor','ชั้น','text',1,293,563,51,24,10,0,NULL),
(@info_template,'company_road','ถนน','text',1,349,563,149,24,10,0,NULL),
(@info_template,'company_alley','ซอย','text',1,506,563,136,24,10,0,NULL),
(@info_template,'subdistrict','ตำบล/แขวง','text',1,689,563,132,24,10,0,NULL),
(@info_template,'district','อำเภอ/เขต','text',1,170,588,132,24,10,0,NULL),
(@info_template,'province','จังหวัด','text',1,430,588,149,24,10,0,NULL),
(@info_template,'postal_code','รหัสไปรษณีย์','text',1,697,588,94,24,10,0,NULL),
(@info_template,'company_phone','โทรศัพท์สถานประกอบการ','text',1,153,614,115,24,10,0,NULL),
(@info_template,'company_fax','โทรสาร','text',1,323,614,106,24,10,0,NULL),
(@info_template,'company_email','อีเมลสถานประกอบการ','text',1,536,614,285,24,10,0,NULL),
(@info_template,'contact_name','ชื่อผู้ประสานงาน','text',1,617,640,204,24,10,0,NULL),
(@info_template,'contact_position','ตำแหน่งผู้ประสานงาน','text',1,153,666,149,24,10,0,NULL),
(@info_template,'contact_department','แผนกผู้ประสานงาน','text',1,446,666,191,24,10,0,NULL),
(@info_template,'contact_phone','โทรศัพท์ผู้ประสานงาน','text',1,153,691,153,24,10,0,NULL),
(@info_template,'contact_email','อีเมลผู้ประสานงาน','text',1,459,691,357,24,10,0,NULL),
(@info_template,'student_print_name','ชื่อในวงเล็บของนักศึกษา','text',1,493,749,204,24,10,0,NULL),
(@info_template,'advisor_name','ชื่ออาจารย์ที่ปรึกษา','text',1,145,893,230,24,10,0,NULL),
(@info_template,'coordinator_name','ชื่อผู้ประสานงานสาขา','text',1,519,893,230,24,10,0,NULL);

COMMIT;
