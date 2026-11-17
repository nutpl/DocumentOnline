from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).resolve().parents[1] / "output"
FONT = Path(r"C:\Windows\Fonts\tahoma.ttf")
BOLD = Path(r"C:\Windows\Fonts\tahomabd.ttf")
W, H = 2400, 1480


def f(size, bold=False):
    return ImageFont.truetype(str(BOLD if bold else FONT), size)


def centered(draw, box, value, font, color="#0f172a"):
    x1, y1, x2, y2 = box
    lines = value.split("\n")
    height = font.getbbox("ก")[3] + 7
    y = (y1 + y2 - len(lines) * height) / 2
    for line in lines:
        left, top, right, bottom = draw.textbbox((0, 0), line, font=font)
        draw.text(((x1 + x2 - (right - left)) / 2, y), line, font=font, fill=color)
        y += height


def box(draw, node):
    x, y, name, attrs = node
    width, height = 390, 58 + 31 * len(attrs)
    draw.rounded_rectangle((x, y, x + width, y + height), radius=12, fill="#ffffff", outline="#334155", width=3)
    draw.rounded_rectangle((x, y, x + width, y + 52), radius=12, fill="#fff1f2", outline="#dc2626", width=2)
    draw.rectangle((x, y + 38, x + width, y + 52), fill="#fff1f2")
    centered(draw, (x + 10, y + 5, x + width - 10, y + 47), name, f(20, True), "#991b1b")
    for idx, attr in enumerate(attrs):
        draw.text((x + 18, y + 64 + idx * 30), attr, font=f(15), fill="#334155")
    return (x, y, x + width, y + height)


def rect(node):
    x, y, _name, attrs = node
    return (x, y, x + 390, y + 58 + 31 * len(attrs))


def connect(draw, source, target, source_label="1", target_label="N"):
    sx1, sy1, sx2, sy2 = source
    tx1, ty1, tx2, ty2 = target
    # Route vertically when tables occupy different rows.  It keeps connector
    # paths in the whitespace between boxes instead of across table contents.
    if sy2 < ty1 or ty2 < sy1:
        source_above = sy2 < ty1
        if source_above:
            p1, p2 = ((sx1 + sx2) // 2, sy2), ((tx1 + tx2) // 2, ty1)
        else:
            p1, p2 = ((sx1 + sx2) // 2, sy1), ((tx1 + tx2) // 2, ty2)
        midy = (p1[1] + p2[1]) // 2
        path = (p1[0], p1[1], p1[0], midy, p2[0], midy, p2[0], p2[1])
        orientation = "vertical"
    else:
        if sx1 < tx1:
            p1, p2 = (sx2, (sy1 + sy2) // 2), (tx1, (ty1 + ty2) // 2)
        else:
            p1, p2 = (sx1, (sy1 + sy2) // 2), (tx2, (ty1 + ty2) // 2)
        midx = (p1[0] + p2[0]) // 2
        path = (p1[0], p1[1], midx, p1[1], midx, p2[1], p2[0], p2[1])
        orientation = "horizontal"
    draw.line(path, fill="#475569", width=3)
    return p1, p2, orientation, source_label, target_label


def cardinality(draw, marker):
    p1, p2, orientation, source_label, target_label = marker
    draw.ellipse((p1[0] - 7, p1[1] - 7, p1[0] + 7, p1[1] + 7), fill="#dc2626")
    draw.ellipse((p2[0] - 7, p2[1] - 7, p2[0] + 7, p2[1] + 7), fill="#dc2626")
    if orientation == "vertical":
        source_y = p1[1] + 8 if p1[1] < p2[1] else p1[1] - 30
        target_y = p2[1] - 30 if p1[1] < p2[1] else p2[1] + 8
        draw.text((p1[0] + 12, source_y), source_label, font=f(17, True), fill="#991b1b")
        draw.text((p2[0] + 12, target_y), target_label, font=f(17, True), fill="#991b1b")
    else:
        source_x = p1[0] + 12 if p1[0] < p2[0] else p1[0] - 30
        target_x = p2[0] - 30 if p1[0] < p2[0] else p2[0] + 12
        draw.text((source_x, p1[1] - 27), source_label, font=f(17, True), fill="#991b1b")
        draw.text((target_x, p2[1] - 27), target_label, font=f(17, True), fill="#991b1b")


def create(filename, group, title, nodes, links, note):
    image = Image.new("RGB", (W, H), "white")
    draw = ImageDraw.Draw(image)
    centered(draw, (80, 30, W - 80, 85), f"ER Diagram กลุ่มที่ {group}", f(34, True))
    centered(draw, (80, 90, W - 80, 140), title, f(26, True))
    centered(draw, (80, 145, W - 80, 182), "แสดงเฉพาะ Primary Key และ Foreign Key ที่ใช้เชื่อมความสัมพันธ์", f(17), "#475569")
    drawn = {node[2]: rect(node) for node in nodes}
    markers = [connect(draw, drawn[src], drawn[dst], sl, tl) for src, dst, sl, tl in links]
    for node in nodes:
        box(draw, node)
    for marker in markers:
        cardinality(draw, marker)
    draw.line((85, 1305, W - 85, 1305), fill="#cbd5e1", width=2)
    draw.text((100, 1340), "สัญลักษณ์: PK = คีย์หลัก, FK = คีย์ต่างประเทศ, 1 = หนึ่งรายการ, N = หลายรายการ", font=f(17), fill="#475569")
    draw.text((100, 1382), note, font=f(17), fill="#475569")
    image.save(OUT / filename, "PNG")


def main():
    create("er-group-1-identity.png", "1", "บัญชีผู้ใช้ สิทธิ์ และการแจ้งเตือน", [
        (120, 280, "users", ["PK user_id", "username, email", "password, status"]),
        (650, 220, "user_roles", ["PK user_role_id", "FK user_id", "role_code"]),
        (650, 460, "password_reset_otp", ["PK otp_id", "FK user_id", "otp_code, expires_at"]),
        (1180, 220, "activity_log", ["PK log_id", "FK user_id", "activity_type, created_at"]),
        (1180, 470, "notifications", ["PK notification_id", "FK recipient_user_id", "FK document_id"]),
        (1710, 340, "prefixes", ["PK prefix_id", "prefix_name, prefix_short"]),
    ], [("users", "user_roles", "1", "N"), ("users", "password_reset_otp", "1", "N"),
        ("users", "activity_log", "1", "N"), ("users", "notifications", "1", "N")],
        "ตาราง prefixes ถูกอ้างอิงจาก student และ employee ในกลุ่มที่ 2")
    create("er-group-2-organization.png", "2", "คณะ สาขา บุคลากร กลุ่มเรียน และนักศึกษา", [
        (100, 220, "faculty", ["PK faculty_id", "faculty_name"]),
        (590, 220, "department", ["PK department_id", "FK faculty_id", "department_name"]),
        (1080, 160, "group_class", ["PK group_id", "FK department_id", "FK advisor_employee_id"]),
        (1570, 160, "student", ["PK student_id", "FK user_id, prefix_id", "FK group_id"]),
        (1080, 510, "employee", ["PK employee_id", "FK user_id, prefix_id", "FK department_id"]),
        (1570, 510, "employee_position", ["PK employee_position_id", "FK employee_id", "FK position_id"]),
        (100, 700, "positions", ["PK position_id", "position_name"]),
        (590, 700, "office_holders", ["PK office_holder_id", "FK position_id", "FK faculty_id / department_id"]),
    ], [("faculty", "department", "1", "N"), ("department", "group_class", "1", "N"),
        ("group_class", "student", "1", "N"), ("department", "employee", "1", "N"),
        ("employee", "employee_position", "1", "N"), ("positions", "employee_position", "1", "N"),
        ("positions", "office_holders", "1", "N"), ("faculty", "office_holders", "1", "N"),
        ("employee", "group_class", "1", "N")],
        "student และ employee เชื่อมบัญชีผู้ใช้กับ users; prefixes เชื่อมเพื่อระบุคำนำหน้า")
    create("er-group-3-document-configuration.png", "3", "ประเภทเอกสาร แม่แบบ ช่องกรอก และ Workflow", [
        (130, 280, "document_type", ["PK document_type_id", "document_code", "document_name, status"]),
        (650, 180, "document_template", ["PK template_id", "FK document_type_id", "template_name, status"]),
        (1170, 130, "template_fields", ["PK field_id", "FK template_id", "field_name, field_type"]),
        (650, 530, "review_workflows", ["PK workflow_id", "FK document_type_id", "workflow_name"]),
        (1170, 530, "review_workflow_steps", ["PK workflow_step_id", "FK workflow_id", "FK position_id / employee_id"]),
    ], [("document_type", "document_template", "1", "N"), ("document_template", "template_fields", "1", "N"),
        ("document_type", "review_workflows", "1", "N"), ("review_workflows", "review_workflow_steps", "1", "N")],
        "ประเภทเอกสารหนึ่งประเภทมีแม่แบบและ Workflow ได้หลายรายการ แต่ระบบกำหนดใช้แม่แบบปัจจุบันเพียงฉบับเดียว")
    create("er-group-4-requests.png", "4", "การจัดทำและส่งคำร้อง R", [
        (120, 410, "documents", ["PK document_id", "FK student_id", "FK template_id", "document_status"]),
        (630, 160, "document_field_values", ["PK value_id", "FK document_id", "FK field_id"]),
        (630, 400, "document_attachments", ["PK attachment_id", "FK document_id", "stored_filename"]),
        (630, 640, "r01_request", ["PK r01_id", "FK document_id"]),
        (1140, 120, "r12_request", ["PK r12_id", "FK document_id"]),
        (1650, 120, "r12_request_courses", ["PK course_item_id", "FK r12_id"]),
        (1140, 350, "r14_request", ["PK r14_id", "FK document_id"]),
        (1140, 580, "r18_request", ["PK r18_id", "FK document_id"]),
        (1140, 810, "r24_request", ["PK r24_id", "FK document_id"]),
    ], [("documents", "document_field_values", "1", "N"), ("documents", "document_attachments", "1", "N"),
        ("documents", "r01_request", "1", "1"), ("documents", "r12_request", "1", "1"),
        ("r12_request", "r12_request_courses", "1", "N"), ("documents", "r14_request", "1", "1"),
        ("documents", "r18_request", "1", "1"), ("documents", "r24_request", "1", "1")],
        "documents เป็นตารางกลางของคำร้องทุกประเภท ส่วนตาราง r01–r24 เก็บรายละเอียดเฉพาะของแต่ละแบบฟอร์ม")
    create("er-group-5-review-history.png", "5", "กระบวนการพิจารณาและประวัติเอกสาร", [
        (150, 350, "documents", ["PK document_id", "FK student_id", "document_status"]),
        (680, 250, "document_review_processes", ["PK process_id", "FK document_id", "current_step_order"]),
        (1230, 130, "document_review_step_instances", ["PK step_instance_id", "FK process_id", "FK reviewer_user_id"]),
        (1230, 500, "document_events", ["PK event_id", "FK document_id", "FK actor_user_id"]),
    ], [("documents", "document_review_processes", "1", "1"),
        ("document_review_processes", "document_review_step_instances", "1", "N"),
        ("documents", "document_events", "1", "N")],
        "เมื่อเอกสารถูกส่ง ระบบสร้าง process และ step instance เพื่อเก็บผู้พิจารณาจริงของเอกสารแต่ละฉบับ")
    create("er-group-6-internship.png", "6", "งานฝึกประสบการณ์วิชาชีพและแผนนิเทศ", [
        (100, 210, "internship_calendar", ["PK calendar_id", "academic_year, semester", "start_date, end_date"]),
        (600, 210, "internship_profile", ["PK internship_profile_id", "FK student_id, calendar_id", "company_name"]),
        (1100, 120, "internship_application", ["PK application_id", "FK internship_profile_id", "FK document_id"]),
        (1100, 400, "internship_response_document", ["PK response_document_id", "FK internship_profile_id", "review_status"]),
        (1600, 400, "internship_tracking", ["PK tracking_id", "FK internship_profile_id", "FK updated_by"]),
        (1100, 680, "internship_supervision", ["PK supervision_id", "FK internship_profile_id", "FK teacher_employee_id"]),
    ], [("internship_calendar", "internship_profile", "1", "N"),
        ("internship_profile", "internship_application", "1", "N"),
        ("internship_profile", "internship_response_document", "1", "N"), ("internship_profile", "internship_tracking", "1", "1"),
        ("internship_profile", "internship_supervision", "1", "N")],
        "internship_profile เป็นตารางกลางของข้อมูลฝึกงานหนึ่งรอบของนักศึกษา และเชื่อมกับคำร้อง เอกสารตอบรับ สถานะ และแผนนิเทศ")
    create("er-group-7-official-output.png", "7", "หนังสือราชการและการส่งออกข้อมูล", [
        (120, 310, "internship_official_document", ["PK official_document_id", "FK created_by", "document_type"]),
        (610, 310, "internship_official_student", ["PK/FK official_document_id", "PK/FK internship_profile_id", "sequence_no"]),
        (1100, 310, "internship_excel_sync", ["PK sync_id", "FK department_id", "FK connected_by_user_id"]),
        (370, 690, "internship_profile", ["PK internship_profile_id", "FK student_id", "company_name"]),
        (1040, 690, "department", ["PK department_id", "department_name"]),
        (1650, 690, "users", ["PK user_id", "username"]),
    ], [("internship_official_document", "internship_official_student", "1", "N"),
        ("internship_profile", "internship_official_student", "1", "N"), ("department", "internship_excel_sync", "1", "N"),
        ("users", "internship_official_document", "1", "N"), ("users", "internship_excel_sync", "1", "N")],
        "หนังสือราชการหนึ่งฉบับเชื่อมกับนักศึกษาได้หลายคนผ่าน internship_official_student ส่วนการเชื่อม Excel เป็นค่าการเชื่อมต่อระดับสาขา")


if __name__ == "__main__":
    main()
