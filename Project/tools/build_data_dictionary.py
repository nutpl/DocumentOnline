import re
from pathlib import Path
from docx import Document
from docx.shared import Cm, Pt
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

SRC = Path(r"C:\Users\mewww\Downloads\online_request (5).sql")
OUT = Path(r"C:\xampp\htdocs\Project\output\database-data-dictionary.docx")

TABLE_NOTE = {
 'users':'เก็บบัญชีสำหรับเข้าสู่ระบบของผู้ใช้ทุกกลุ่มในระบบ',
 'user_roles':'เก็บบทบาทและสิทธิ์การเข้าถึงของบัญชีผู้ใช้',
 'student':'เก็บข้อมูลประจำตัวและกลุ่มเรียนของนักศึกษา',
 'employee':'เก็บข้อมูลอาจารย์และเจ้าหน้าที่ของคณะ',
 'documents':'เก็บเอกสารคำร้องที่ผู้ใช้สร้างและส่งในระบบ',
 'internship_profile':'เก็บข้อมูลหลักของการฝึกประสบการณ์วิชาชีพของนักศึกษา',
 'internship_response_document':'เก็บใบตอบรับจากสถานประกอบการและผลการตรวจสอบ',
 'internship_supervision':'เก็บแผนนิเทศและรายละเอียดการนิเทศนักศึกษา',
 'internship_tracking':'เก็บสถานะภาพรวมของกระบวนการสหกิจศึกษา',
 'internship_official_document':'เก็บข้อมูลหนังสือทางการ เช่น หนังสือสอบถามและหนังสือส่งตัว',
 'review_workflows':'เก็บรูปแบบลำดับการพิจารณาเอกสาร',
 'review_workflow_steps':'เก็บรายละเอียดของแต่ละขั้นตอนการพิจารณา',
 'document_review_processes':'เก็บกระบวนการพิจารณาที่เกิดขึ้นจริงของเอกสารแต่ละฉบับ',
 'document_review_step_instances':'เก็บสถานะผู้พิจารณาในแต่ละขั้นตอนของเอกสาร',
}

TABLE_ORDER = ['users','user_roles','password_reset_otp','activity_log','prefixes','faculty','department','positions','employee','employee_position','office_holders','group_class','student','document_type','document_template','template_fields','documents','document_field_values','document_attachments','notifications','review_workflows','review_workflow_steps','document_review_processes','document_review_step_instances','document_events','r01_request','r12_request','r12_request_courses','r14_request','r18_request','r24_request','internship_calendar','internship_profile','internship_application','internship_response_document','internship_tracking','internship_supervision','internship_official_document','internship_official_student','internship_excel_sync']

TABLE_NOTE.update({
 'user_roles':'เก็บบทบาทหรือสิทธิ์ของบัญชี เช่น นักศึกษา อาจารย์ เจ้าหน้าที่ และผู้ดูแลระบบ เชื่อมกับ users ผ่าน user_id เพื่อให้ระบบตรวจสอบสิทธิ์การเข้าถึงเมนูและการดำเนินการ',
 'password_reset_otp':'เก็บรหัส OTP สำหรับลืมรหัสผ่าน วันหมดอายุ และสถานะการใช้งาน เชื่อมกับ users เพื่อยืนยันตัวตนและป้องกันการใช้ OTP ซ้ำ',
 'activity_log':'เก็บประวัติการกระทำสำคัญของผู้ใช้ เช่น เพิ่ม แก้ไข อนุมัติ หรือลบข้อมูล เชื่อมกับ users ผ่าน user_id เพื่อตรวจสอบว่าใครทำอะไรและเมื่อใด',
 'prefixes':'เก็บคำนำหน้าชื่อ เช่น นาย นางสาว หรือ ดร. เชื่อมกับ student และ employee เพื่อลดการเก็บข้อมูลคำนำหน้าซ้ำ',
 'faculty':'เก็บตัวย่อและชื่อคณะ เชื่อมกับ department เพราะหนึ่งคณะมีหลายสาขา',
 'department':'เก็บข้อมูลสาขาวิชาและคณะที่สังกัด เชื่อมกับ faculty และเป็นขอบเขตของกลุ่มเรียน บุคลากร และการซิงก์ Excel',
 'positions':'เก็บชื่อตำแหน่งหรือหน้าที่ เช่น หัวหน้าสาขาและอาจารย์ประสานงาน เชื่อมกับ employee_position, office_holders และ review_workflow_steps',
 'employee':'เก็บข้อมูลอาจารย์และเจ้าหน้าที่ เชื่อมกับ users, prefixes และ department เพื่อระบุบัญชี คำนำหน้า และสาขาที่สังกัด',
 'employee_position':'เก็บความสัมพันธ์ระหว่างบุคลากรกับตำแหน่ง รองรับบุคลากรหนึ่งคนมีหลายตำแหน่ง และ is_primary ใช้ระบุตำแหน่งหลัก',
 'office_holders':'เก็บผู้ดำรงตำแหน่งในแต่ละช่วงเวลา โดยอ้างอิงสาขาผ่าน department_id เพื่อกำหนดผู้พิจารณาและผู้ลงนามในหนังสือทางการ',
 'group_class':'เก็บกลุ่มเรียน สาขา และอาจารย์ที่ปรึกษาประจำกลุ่ม เชื่อมกับ department และ employee ผ่าน advisor_employee_id',
 'student':'เก็บข้อมูลนักศึกษา กลุ่มเรียน และบัญชีผู้ใช้ เชื่อมกับ users, prefixes และ group_class เพื่อระบุอาจารย์ที่ปรึกษา',
 'document_type':'เก็บประเภทเอกสาร เช่น R.01 R.12 R.14 R.18 R.24 และเอกสารฝึกประสบการณ์ เพื่อกำหนดรหัส ชื่อ และสถานะการเปิดใช้',
 'document_template':'เก็บแม่แบบเอกสาร ไฟล์ PDF/DOCX ไฟล์ต้นฉบับ รูปตัวอย่าง และสถานะ เชื่อมกับ document_type และ users ผู้นำเข้า',
 'template_fields':'เก็บโครงสร้างช่องกรอก เช่น ชื่อช่อง ป้ายชื่อ ตำแหน่ง ขนาดตัวอักษร และสถานะบังคับกรอก เชื่อมกับ document_template',
 'documents':'เก็บเอกสารที่สร้างจริงของนักศึกษา เชื่อมกับ student และ document_template เป็นตารางหลักของระบบเอกสารคำร้อง',
 'document_field_values':'เก็บค่าที่ผู้ใช้กรอกในแต่ละช่องของเอกสาร เชื่อมกับ documents และ template_fields เพื่อระบุค่า ช่อง และเอกสารเจ้าของข้อมูล',
 'document_attachments':'เก็บไฟล์แนบของเอกสาร เชื่อมกับ documents และ users ผ่าน uploaded_by เพื่อระบุเอกสารเจ้าของและผู้อัปโหลด',
 'notifications':'เก็บการแจ้งเตือน เช่น เอกสารรอพิจารณา ส่งกลับแก้ไข หรืออนุมัติ เชื่อมกับ users และ documents ที่เกี่ยวข้อง',
 'review_workflows':'เก็บลำดับการพิจารณาของเอกสารแต่ละประเภท เช่น อาจารย์ที่ปรึกษาแล้วจึงเจ้าหน้าที่ เชื่อมกับ document_type',
 'review_workflow_steps':'เก็บแต่ละขั้นของ Workflow และแหล่งกำหนดผู้พิจารณา เชื่อมกับ review_workflows, employee และ positions',
 'document_review_processes':'เก็บกระบวนการพิจารณาที่เกิดขึ้นจริงเมื่อส่งเอกสาร เพื่อบอก Workflow ขั้นตอนปัจจุบัน และสถานะรวมของเอกสาร',
 'document_review_step_instances':'เก็บขั้นตอนจริงของเอกสารแต่ละฉบับ ผู้พิจารณา สถานะ และเวลา เพื่อเก็บประวัติแม้ Workflow หรือผู้ดำรงตำแหน่งเปลี่ยนภายหลัง',
 'document_events':'เก็บเหตุการณ์ของเอกสาร เช่น สร้าง ส่ง อนุมัติ ไม่อนุมัติ หรือส่งกลับแก้ไข เชื่อมกับ documents ขั้นตอนพิจารณา และผู้ดำเนินการ',
 'r01_request':'เก็บรายละเอียดเฉพาะของคำร้องทั่วไป R.01 เช่น เรื่อง ผู้รับ เหตุผล เบอร์โทรศัพท์ และอาจารย์ที่ปรึกษา เชื่อมกับ documents',
 'r12_request':'เก็บรายละเอียดคำร้องขอแก้ไขกลุ่มวิชาตามโครงสร้างหลักสูตร R.12 เชื่อมกับ documents และ employee',
 'r12_request_courses':'เก็บรายวิชาในคำร้อง R.12 เพราะหนึ่งคำร้องมีได้หลายรายวิชา เชื่อมกับ r12_request ผ่าน r12_id',
 'r14_request':'เก็บรายละเอียดคำร้องลงทะเบียนต่ำกว่า/เกินกว่าหน่วยกิต เช่น ภาคการศึกษา ปีการศึกษา หน่วยกิต และ GPA เชื่อมกับ documents',
 'r18_request':'เก็บรายละเอียดคำร้องขอเปิดรายวิชา เช่น รหัสวิชา ชื่อวิชา หน่วยกิต และอาจารย์ผู้เกี่ยวข้อง เชื่อมกับ documents และ employee',
 'r24_request':'เก็บรายละเอียดคำร้องลาป่วย/ลากิจ เช่น ประเภทการลา วันที่ลา เหตุผล รายวิชา และอาจารย์ผู้สอน เชื่อมกับ documents และ employee',
 'internship_calendar':'เก็บช่วงเวลาฝึกประสบการณ์ เช่น ปีการศึกษา ภาคการศึกษา วันเริ่มและสิ้นสุดฝึก เชื่อมกับ internship_profile',
 'internship_profile':'เก็บข้อมูลหลักการฝึกงาน เช่น สถานประกอบการ ผู้ติดต่อ ตำแหน่งงาน คุณสมบัติ และสถานะคำร้อง เชื่อมกับ student และ internship_calendar รวมถึงข้อมูลเดิมกรณีย้ายที่ฝึก',
 'internship_application':'เก็บการสมัครงานในระบบ เช่น วันที่สมัคร สถานะติดต่อ วิธีส่งใบสมัคร และไฟล์ลงนาม เชื่อมกับ internship_profile และ documents',
 'internship_response_document':'เก็บใบตอบรับที่นักศึกษาอัปโหลด สถานะตรวจสอบ หมายเหตุ ผู้ตรวจ และวันตรวจ เชื่อมกับ internship_profile และ users',
 'internship_tracking':'เก็บสถานะภาพรวมสหกิจ เช่น หนังสือสอบถาม ใบตอบรับ สมัครงาน และหนังสือส่งตัว เชื่อมกับ internship_profile และ users',
 'internship_supervision':'เก็บแผนนิเทศ เช่น วัน เวลา รูปแบบนิเทศ และรายชื่ออาจารย์นิเทศ เชื่อมกับ internship_profile, employee และ users',
 'internship_official_document':'เก็บหนังสือทางการที่เจ้าหน้าที่สร้าง เช่น หนังสือสอบถามหรือหนังสือส่งตัว เชื่อมกับ users ผ่าน created_by',
 'internship_official_student':'เป็นตารางเชื่อมหนังสือทางการกับนักศึกษา รองรับหนังสือหนึ่งฉบับมีนักศึกษาหลายคน และนักศึกษาหนึ่งคนอยู่ได้หลายหนังสือ',
 'internship_excel_sync':'เก็บข้อมูลเชื่อมต่อ Excel ของแต่ละสาขา เช่น ชื่อไฟล์ ชีต ลิงก์ และสถานะซิงก์ เชื่อมกับ department และ users ผู้เชื่อมต่อ'
})

TABLE_THAI = {
 'activity_log':'ประวัติการดำเนินงาน','department':'สาขาวิชา','document_attachments':'ไฟล์แนบเอกสาร','document_events':'เหตุการณ์เอกสาร','document_field_values':'ค่าช่องกรอกเอกสาร','document_review_processes':'กระบวนการพิจารณาเอกสาร','document_review_step_instances':'ขั้นตอนพิจารณาเอกสาร','document_template':'แม่แบบเอกสาร','document_type':'ประเภทเอกสาร','documents':'เอกสารคำร้อง','employee':'บุคลากร','employee_position':'ตำแหน่งของบุคลากร','faculty':'คณะ','group_class':'กลุ่มเรียน','internship_application':'การสมัครงานสหกิจ','internship_calendar':'ปฏิทินฝึกประสบการณ์','internship_excel_sync':'การเชื่อมต่อ Excel สหกิจ','internship_official_document':'หนังสือทางการสหกิจ','internship_official_student':'รายชื่อนักศึกษาในหนังสือทางการ','internship_profile':'ข้อมูลการฝึกประสบการณ์','internship_response_document':'หนังสือตอบรับสถานประกอบการ','internship_supervision':'แผนนิเทศ','internship_tracking':'ติดตามสถานะสหกิจ','notifications':'การแจ้งเตือน','office_holders':'ผู้ดำรงตำแหน่ง','password_reset_otp':'รหัส OTP รีเซ็ตรหัสผ่าน','positions':'ตำแหน่ง','prefixes':'คำนำหน้าชื่อ','r01_request':'คำร้องทั่วไป','r12_request':'คำร้องแก้ไขกลุ่มวิชา','r12_request_courses':'รายวิชาในคำร้อง R.12','r14_request':'คำร้องลงทะเบียนหน่วยกิต','r18_request':'คำร้องขอเปิดรายวิชา','r24_request':'คำร้องลาป่วยหรือลากิจ','review_workflow_steps':'ขั้นตอน Workflow','review_workflows':'รูปแบบ Workflow','student':'นักศึกษา','template_fields':'ช่องกรอกในแม่แบบ','user_roles':'สิทธิ์ผู้ใช้','users':'บัญชีผู้ใช้'
}

FIELD_NOTE = {
 'user_id':'รหัสประจำบัญชีผู้ใช้', 'student_id':'รหัสข้อมูลนักศึกษา',
 'employee_id':'รหัสข้อมูลบุคลากร', 'document_id':'รหัสเอกสาร',
 'created_at':'วันที่และเวลาที่สร้างข้อมูล', 'updated_at':'วันที่และเวลาที่แก้ไขข้อมูลล่าสุด',
 'firstname':'ชื่อ', 'lastname':'นามสกุล', 'email':'อีเมล', 'phone':'หมายเลขโทรศัพท์',
 'password':'รหัสผ่านที่เข้ารหัสแล้ว', 'username':'ชื่อสำหรับเข้าสู่ระบบ',
 'status':'สถานะการดำเนินงานหรือสถานะข้อมูล', 'document_status':'สถานะเอกสาร',
 'department_id':'รหัสสาขาวิชา', 'faculty_id':'รหัสคณะ', 'group_id':'รหัสกลุ่มเรียน',
 'prefix_id':'รหัสคำนำหน้าชื่อ', 'template_id':'รหัสแม่แบบเอกสาร',
 'document_type_id':'รหัสประเภทเอกสาร', 'workflow_id':'รหัสรูปแบบการพิจารณา',
 'position_id':'รหัสตำแหน่ง', 'academic_year':'ปีการศึกษา', 'semester':'ภาคการศึกษา',
 'start_date':'วันที่เริ่มต้น', 'end_date':'วันที่สิ้นสุด', 'company_name':'ชื่อสถานประกอบการ',
 'original_filename':'ชื่อไฟล์เดิม', 'stored_filename':'ชื่อไฟล์ที่จัดเก็บในระบบ',
 'review_status':'สถานะการตรวจสอบ', 'review_note':'หมายเหตุจากผู้ตรวจสอบ',
 'reviewed_by':'ผู้ตรวจสอบ', 'reviewed_at':'วันที่และเวลาที่ตรวจสอบ',
 'log_id':'รหัสรายการบันทึกกิจกรรม', 'activity':'ชื่อหรือประเภทกิจกรรมที่ผู้ใช้ดำเนินการ', 'detail':'รายละเอียดเพิ่มเติมของกิจกรรม', 'activity_time':'วันที่และเวลาที่เกิดกิจกรรม',
 'faculty_abbreviation':'รหัสคณะ', 'faculty_name':'ชื่อคณะ', 'department_abbreviation':'รหัสหรือตัวย่อสาขาวิชา', 'department_name':'ชื่อสาขาวิชา',
 'document_code':'รหัสประเภทเอกสาร', 'document_name':'ชื่อประเภทเอกสาร', 'form_file':'ไฟล์แบบฟอร์มเอกสาร', 'description':'คำอธิบายรายละเอียดของข้อมูล',
 'template_name':'ชื่อแม่แบบเอกสาร', 'pdf_template':'ไฟล์แม่แบบ PDF', 'docx_template':'ไฟล์แม่แบบ Word', 'original_file':'ไฟล์ต้นฉบับของแม่แบบ', 'preview_image':'รูปภาพตัวอย่างเอกสาร', 'total_pages':'จำนวนหน้าเอกสาร', 'file_type':'ชนิดไฟล์ของแม่แบบ', 'version':'เลขรุ่นของแม่แบบ', 'imported_by':'รหัสผู้ใช้งานที่นำเข้าแม่แบบ', 'imported_at':'วันที่และเวลาที่นำเข้าแม่แบบ',
 'field_name':'ชื่ออ้างอิงของช่องกรอก', 'field_label':'ชื่อที่แสดงหน้าช่องกรอก', 'field_type':'ชนิดของช่องกรอกข้อมูล', 'page_no':'เลขหน้าที่แสดงช่องกรอก', 'pos_x':'ตำแหน่งแนวนอนของช่องกรอก', 'pos_y':'ตำแหน่งแนวตั้งของช่องกรอก', 'width':'ความกว้างของช่องกรอก', 'height':'ความสูงของช่องกรอก', 'font_size':'ขนาดตัวอักษรที่ใช้แสดง', 'required_field':'สถานะบังคับกรอกข้อมูล', 'default_value':'ค่าเริ่มต้นของช่องกรอก',
 'original_name':'ชื่อไฟล์ก่อนจัดเก็บ', 'stored_name':'ชื่อไฟล์ที่ระบบจัดเก็บ', 'file_path':'ตำแหน่งจัดเก็บไฟล์', 'mime_type':'ชนิดข้อมูลของไฟล์', 'file_size':'ขนาดไฟล์', 'uploaded_by':'รหัสผู้ใช้งานที่อัปโหลดไฟล์', 'uploaded_at':'วันที่และเวลาที่อัปโหลดไฟล์',
 'event_id':'รหัสเหตุการณ์เอกสาร', 'document_step_id':'รหัสขั้นตอนพิจารณาเอกสาร', 'actor_user_id':'รหัสผู้ใช้งานผู้ดำเนินการ', 'event_type':'ประเภทเหตุการณ์ที่เกิดขึ้น',
 'value_id':'รหัสค่าข้อมูลในช่องกรอก', 'field_value':'ค่าที่กรอกลงในช่องเอกสาร',
 'process_id':'รหัสกระบวนการพิจารณาเอกสาร', 'workflow_version_no':'เลขรุ่น Workflow ที่ใช้กับเอกสาร', 'current_step_order':'ลำดับขั้นตอนที่กำลังดำเนินการ', 'process_status':'สถานะรวมของกระบวนการพิจารณา', 'revision_count':'จำนวนครั้งที่ส่งกลับแก้ไข', 'started_at':'วันที่และเวลาเริ่มกระบวนการ', 'completed_at':'วันที่และเวลาสิ้นสุดกระบวนการ',
 'source_workflow_step_id':'รหัสขั้นตอนต้นแบบของ Workflow', 'step_order':'ลำดับของขั้นตอนพิจารณา', 'role_code':'รหัสบทบาทผู้พิจารณา', 'step_name':'ชื่อขั้นตอนการพิจารณา', 'reviewer_employee_id':'รหัสบุคลากรผู้พิจารณา', 'reviewer_user_id':'รหัสบัญชีผู้พิจารณา', 'step_status':'สถานะของขั้นตอนพิจารณา', 'attempt_count':'จำนวนครั้งที่ดำเนินการในขั้นตอน', 'activated_at':'วันที่และเวลาที่เปิดใช้ขั้นตอน',
 'employee_code':'รหัสบุคลากร', 'employee_type':'ประเภทบุคลากร เช่น อาจารย์หรือเจ้าหน้าที่', 'position_name':'ชื่อตำแหน่ง', 'is_primary':'สถานะตำแหน่งหลักของบุคลากร', 'group_name':'ชื่อกลุ่มเรียน', 'student_prefix':'อักษรนำหน้าชื่อกลุ่มเรียน', 'advisor_employee_id':'รหัสอาจารย์ที่ปรึกษาประจำกลุ่ม', 'student_number':'รหัสนักศึกษา',
 'notification_id':'รหัสการแจ้งเตือน', 'recipient_user_id':'รหัสผู้รับการแจ้งเตือน', 'notification_type':'ประเภทการแจ้งเตือน', 'title':'หัวข้อการแจ้งเตือน', 'message':'ข้อความแจ้งเตือน', 'target_url':'ลิงก์ปลายทางเมื่อเปิดการแจ้งเตือน', 'is_read':'สถานะการอ่านการแจ้งเตือน', 'read_at':'วันที่และเวลาที่อ่านการแจ้งเตือน',
 'workflow_name':'ชื่อรูปแบบ Workflow', 'workflow_status':'สถานะการเปิดใช้งาน Workflow', 'revision_return_policy':'นโยบายการส่งเอกสารกลับเพื่อแก้ไข', 'reviewer_source':'แหล่งข้อมูลที่ใช้กำหนดผู้พิจารณา', 'reviewer_position_id':'รหัสตำแหน่งผู้พิจารณา', 'scope_type':'ขอบเขตการพิจารณา', 'is_required':'สถานะบังคับต้องพิจารณา',
 'calendar_id':'รหัสปฏิทินฝึกประสบการณ์', 'request_status':'สถานะคำร้องสหกิจ', 'request_reason':'เหตุผลของการยื่นคำร้อง', 'previous_profile_id':'รหัสข้อมูลฝึกงานเดิมกรณียื่นใหม่หรือย้ายสถานที่', 'closed_reason':'เหตุผลการปิดข้อมูลฝึกงาน', 'closed_at':'วันที่และเวลาปิดข้อมูลฝึกงาน', 'student_phone':'เบอร์โทรศัพท์นักศึกษา', 'year_level':'ชั้นปีของนักศึกษา',
 'recipient_name':'ชื่อผู้รับหนังสือ', 'company_address':'ที่อยู่สถานประกอบการ', 'subdistrict':'ตำบลหรือแขวง', 'district':'อำเภอหรือเขต', 'province':'จังหวัด', 'postal_code':'รหัสไปรษณีย์', 'company_phone':'หมายเลขโทรศัพท์สถานประกอบการ', 'company_fax':'หมายเลขโทรสารสถานประกอบการ', 'contact_prefix':'คำนำหน้าชื่อผู้ติดต่อ', 'contact_name':'ชื่อผู้ติดต่อสถานประกอบการ', 'contact_position':'ตำแหน่งผู้ติดต่อ', 'contact_phone':'หมายเลขโทรศัพท์ผู้ติดต่อ', 'job_position':'ตำแหน่งงานที่นักศึกษาฝึก', 'job_description':'รายละเอียดงานที่ได้รับมอบหมาย',
 'response_document_id':'รหัสเอกสารตอบรับ', 'submission_type':'ประเภทการส่งเอกสารสหกิจ', 'document_number':'เลขที่หนังสือ', 'document_date':'วันที่ในหนังสือ', 'signed_file':'ไฟล์เอกสารที่ลงนามแล้ว', 'submitted_at':'วันที่และเวลาส่งเอกสาร',
 'supervisor_employee_id':'รหัสอาจารย์นิเทศ', 'visit1_date':'วันที่นิเทศครั้งที่ 1', 'visit1_time':'เวลานิเทศครั้งที่ 1', 'visit1_mode':'รูปแบบนิเทศครั้งที่ 1', 'visit2_date':'วันที่นิเทศครั้งที่ 2', 'visit2_time':'เวลานิเทศครั้งที่ 2', 'visit2_mode':'รูปแบบนิเทศครั้งที่ 2', 'supervisor_names':'รายชื่ออาจารย์นิเทศ', 'lock_version':'เลขรุ่นข้อมูลสำหรับป้องกันการแก้ไขพร้อมกัน',
 'request_letter_status':'สถานะหนังสือสอบถาม', 'response_status':'สถานะหนังสือตอบรับ', 'application_status':'สถานะการสมัครงาน', 'send_letter_status':'สถานะหนังสือส่งตัว', 'employment_status':'สถานะการรับเข้าฝึกงาน', 'notes':'หมายเหตุเพิ่มเติม',
 'official_document_id':'รหัสหนังสือทางการ', 'document_type':'ประเภทหนังสือทางการ', 'reference_text':'ข้อความอ้างถึงในหนังสือ', 'response_due_date':'วันที่ครบกำหนดส่งใบตอบรับ', 'signatory_name':'ชื่อผู้ลงนาม', 'signatory_title_line1':'ตำแหน่งผู้ลงนามบรรทัดที่ 1', 'signatory_title_line2':'ตำแหน่งผู้ลงนามบรรทัดที่ 2', 'sequence_no':'ลำดับรายชื่อนักศึกษาในหนังสือ',
 'sync_id':'รหัสการเชื่อมต่อ Excel', 'connected_by_user_id':'รหัสผู้ใช้งานผู้เชื่อมต่อ Excel', 'refresh_token_encrypted':'ข้อมูลสำหรับต่ออายุการเชื่อมต่อที่เข้ารหัส', 'access_token_encrypted':'ข้อมูลสิทธิ์เข้าถึงที่เข้ารหัส', 'access_token_expires_at':'วันหมดอายุของสิทธิ์เข้าถึง', 'drive_id':'รหัสพื้นที่จัดเก็บไฟล์', 'item_id':'รหัสไฟล์ Excel', 'workbook_name':'ชื่อสมุดงาน Excel', 'worksheet_name':'ชื่อแผ่นงาน Excel', 'share_url':'ลิงก์แชร์ไฟล์ Excel', 'last_synced_at':'วันที่และเวลาซิงก์ล่าสุด', 'last_sync_status':'สถานะการซิงก์ล่าสุด', 'last_sync_message':'ข้อความผลการซิงก์ล่าสุด',
 'office_holder_id':'รหัสผู้ดำรงตำแหน่ง', 'holder_name':'ชื่อผู้ดำรงตำแหน่ง', 'effective_from':'วันที่เริ่มดำรงตำแหน่ง', 'effective_to':'วันที่สิ้นสุดการดำรงตำแหน่ง', 'is_active':'สถานะการใช้งานข้อมูล',
 'otp_id':'รหัสรายการ OTP', 'otp_code':'รหัส OTP สำหรับยืนยันตัวตน', 'expire_at':'วันและเวลาหมดอายุ OTP', 'used':'สถานะการใช้ OTP',
 'prefix_name':'ชื่อคำนำหน้าชื่อ', 'prefix_short':'คำนำหน้าชื่อแบบย่อ', 'subject':'เรื่องของคำร้อง', 'recipient':'ผู้รับคำร้อง', 'request_detail':'รายละเอียดหรือเหตุผลของคำร้อง',
 'r01_id':'รหัสคำร้องทั่วไป', 'r12_id':'รหัสคำร้องแก้ไขกลุ่มวิชา', 'r12_course_id':'รหัสรายการรายวิชาในคำร้อง R.12', 'item_no':'ลำดับรายการ', 'course_code':'รหัสรายวิชา', 'course_name':'ชื่อรายวิชา', 'old_group':'กลุ่มเรียนเดิม', 'new_group':'กลุ่มเรียนใหม่', 'reason':'เหตุผลของคำร้อง',
 'r14_id':'รหัสคำร้องลงทะเบียนหน่วยกิต', 'request_type':'ประเภทคำร้อง', 'enrolled_credits':'จำนวนหน่วยกิตที่ลงทะเบียน', 'cumulative_gpa':'คะแนนเฉลี่ยสะสม',
 'r18_id':'รหัสคำร้องขอเปิดรายวิชา', 'credits':'จำนวนหน่วยกิตของรายวิชา', 'teacher_employee_id':'รหัสอาจารย์ผู้สอน',
 'r24_id':'รหัสคำร้องลาป่วยหรือลากิจ', 'leave_type':'ประเภทการลา',
 'user_role_id':'รหัสรายการสิทธิ์ผู้ใช้', 'role':'บทบาทหรือสิทธิ์ของผู้ใช้', 'first_login':'สถานะการเข้าใช้ระบบครั้งแรก', 'active_session_token_hash':'รหัสอ้างอิง Session ที่เข้ารหัส', 'active_session_at':'วันและเวลาใช้งาน Session ล่าสุด',
 'capacity_note':'หมายเหตุเกี่ยวกับจำนวนหรือความพร้อมรับนักศึกษา', 'recipient_position':'ตำแหน่งผู้รับหนังสือ', 'recipient_department':'หน่วยงานของผู้รับหนังสือ', 'company_number':'เลขที่ตั้งสถานประกอบการ', 'company_moo':'หมู่ที่ตั้งสถานประกอบการ', 'company_building':'ชื่ออาคารสถานประกอบการ', 'company_floor':'ชั้นของสถานประกอบการ', 'company_road':'ถนนที่ตั้งสถานประกอบการ', 'company_alley':'ซอยที่ตั้งสถานประกอบการ', 'company_email':'อีเมลสถานประกอบการ', 'contact_department':'หน่วยงานของผู้ติดต่อ', 'contact_email':'อีเมลผู้ติดต่อ', 'qualification_department':'คุณสมบัติด้านสาขาวิชาที่ต้องการ', 'qualification_preparation':'คุณสมบัติด้านการเตรียมความพร้อม', 'qualification_gpa':'คุณสมบัติด้านผลการเรียน', 'qualification_company':'คุณสมบัติอื่นตามที่สถานประกอบการกำหนด',
}

def thai_name(name):
    if name in FIELD_NOTE: return FIELD_NOTE[name]
    if name.endswith('_id'): return 'รหัสอ้างอิงข้อมูลที่เกี่ยวข้อง'
    return 'ข้อมูลสำหรับการทำงานของระบบ'

def thai_type(db_type):
    value=db_type.lower()
    if value.startswith(('int','bigint','tinyint','smallint')): return 'จำนวนเต็ม'
    if value.startswith(('decimal','float','double')): return 'จำนวนทศนิยม'
    if value.startswith(('datetime','timestamp')): return 'วันที่และเวลา'
    if value.startswith('date'): return 'วันที่'
    if value.startswith('time'): return 'เวลา'
    if value.startswith(('varchar','char','text','enum')): return 'ข้อความ'
    return db_type.upper()

def set_font(run, size=14, bold=False):
    run.font.name = 'TH Sarabun PSK'; run.font.size = Pt(size); run.bold = bold
    rpr = run._element.get_or_add_rPr(); fonts = rpr.rFonts
    fonts.set(qn('w:ascii'),'TH Sarabun PSK'); fonts.set(qn('w:hAnsi'),'TH Sarabun PSK'); fonts.set(qn('w:eastAsia'),'TH Sarabun PSK')

def shade(cell, fill):
    tcPr=cell._tc.get_or_add_tcPr(); shd=OxmlElement('w:shd'); shd.set(qn('w:fill'),fill); tcPr.append(shd)

def parse():
    raw=SRC.read_text(encoding='utf-8', errors='ignore')
    tables=[]
    for name, body in re.findall(r'CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=', raw, re.S):
        fields=[]; primary=set(); foreign=set()
        for keys in re.findall(r'PRIMARY KEY \(([^)]+)\)', body): primary.update(re.findall(r'`([^`]+)`',keys))
        for keys in re.findall(r'FOREIGN KEY \(`([^`]+)`\)', body): foreign.add(keys)
        for line in body.splitlines():
            m=re.match(r'\s*`([^`]+)`\s+([^\s,]+(?:\([^)]*\))?)(.*)', line)
            if not m: continue
            col, typ, rest=m.groups()
            nullable='YES' if 'DEFAULT NULL' in rest or re.search(r'\bNULL\b',rest) and 'NOT NULL' not in rest else 'NO'
            key='PK' if col in primary else ('FK' if col in foreign else '')
            fields.append((col,typ,nullable,key))
        tables.append((name,fields))
    return tables

doc=Document(); sec=doc.sections[0]; sec.top_margin=Cm(2.54); sec.bottom_margin=Cm(2.54); sec.left_margin=Cm(2.54); sec.right_margin=Cm(2.54)
style=doc.styles['Normal']; style.font.name='TH Sarabun PSK'; style.font.size=Pt(14); style._element.rPr.rFonts.set(qn('w:eastAsia'),'TH Sarabun PSK')
p=doc.add_paragraph(); p.alignment=WD_ALIGN_PARAGRAPH.CENTER; r=p.add_run('พจนานุกรมข้อมูลระบบจัดการเอกสารคำร้อง\nและการฝึกประสบการณ์วิชาชีพ'); set_font(r,20,True)
p=doc.add_paragraph(); p.alignment=WD_ALIGN_PARAGRAPH.CENTER; r=p.add_run('รายละเอียดโครงสร้างฐานข้อมูล จำนวน 41 ตาราง'); set_font(r,14)

parsed = {name: fields for name, fields in parse()}
for i,name in enumerate(TABLE_ORDER,1):
    fields = parsed[name]
    doc.add_page_break() if i>1 else None
    p=doc.add_paragraph(); r=p.add_run(f'ตารางที่ 3.{i} {name} ({TABLE_THAI.get(name, name)})'); set_font(r,16,True)
    table=doc.add_table(rows=1, cols=6); table.alignment=WD_TABLE_ALIGNMENT.CENTER; table.style='Table Grid'
    headers=['ชื่อคอลัมน์','คำอธิบาย','ประเภทข้อมูล','ค่าว่าง','คีย์','หมายเหตุ']
    for cell,label in zip(table.rows[0].cells,headers):
        cell.text=''; rr=cell.paragraphs[0].add_run(label); set_font(rr,13,True); cell.paragraphs[0].alignment=WD_ALIGN_PARAGRAPH.CENTER
    for col,typ,null,key in fields:
        cells=table.add_row().cells; vals=[col,thai_name(col),typ.upper(),null,key,'']
        for cell,value in zip(cells,vals):
            cell.text=''; rr=cell.paragraphs[0].add_run(value); set_font(rr,12); cell.vertical_alignment=WD_CELL_VERTICAL_ALIGNMENT.CENTER
            if value in ['NO','YES','PK','FK']: cell.paragraphs[0].alignment=WD_ALIGN_PARAGRAPH.CENTER
    p=doc.add_paragraph(); r=p.add_run(f'คำอธิบาย: {TABLE_NOTE.get(name, "ใช้สำหรับจัดเก็บข้อมูลที่จำเป็นต่อการทำงานของระบบ") }'); set_font(r,13)

OUT.parent.mkdir(parents=True, exist_ok=True); doc.save(OUT); print(f'{len(parse())} tables written: {OUT}')
