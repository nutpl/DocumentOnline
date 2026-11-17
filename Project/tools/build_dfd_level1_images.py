from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).resolve().parents[1] / "output"
FONT_PATH = Path(r"C:\Windows\Fonts\tahoma.ttf")
FONT_BOLD_PATH = Path(r"C:\Windows\Fonts\tahomabd.ttf")

WIDTH, HEIGHT = 2200, 1280
INK = "#0f172a"
MUTED = "#475569"
LINE = "#334155"
RED = "#dc2626"
RED_SOFT = "#fff7f7"
STORE_FILL = "#f8fafc"


def font(size, bold=False):
    path = FONT_BOLD_PATH if bold else FONT_PATH
    return ImageFont.truetype(str(path), size)


def text_center(draw, box, text, fnt, color=INK):
    left, top, right, bottom = box
    words = text.split("\n")
    line_height = fnt.getbbox("ก")[3] + 8
    total = line_height * len(words)
    y = (top + bottom - total) / 2
    for line in words:
        bounds = draw.textbbox((0, 0), line, font=fnt)
        draw.text(((left + right - (bounds[2] - bounds[0])) / 2, y), line, font=fnt, fill=color)
        y += line_height


def rounded_box(draw, box, fill, outline, radius=22, width=3):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def arrow_head(draw, x, y, direction, fill=LINE):
    size = 13
    if direction == "right":
        points = [(x, y), (x - size, y - size // 2), (x - size, y + size // 2)]
    elif direction == "left":
        points = [(x, y), (x + size, y - size // 2), (x + size, y + size // 2)]
    elif direction == "down":
        points = [(x, y), (x - size // 2, y - size), (x + size // 2, y - size)]
    else:
        points = [(x, y), (x - size // 2, y + size), (x + size // 2, y + size)]
    draw.polygon(points, fill=fill)


def double_arrow(draw, start, end):
    x1, y1 = start
    x2, y2 = end
    draw.line((x1, y1, x2, y2), fill=LINE, width=3)
    if abs(x2 - x1) >= abs(y2 - y1):
        arrow_head(draw, x1, y1, "left" if x2 > x1 else "right")
        arrow_head(draw, x2, y2, "right" if x2 > x1 else "left")
    else:
        arrow_head(draw, x1, y1, "up" if y2 > y1 else "down")
        arrow_head(draw, x2, y2, "down" if y2 > y1 else "up")


def draw_store(draw, x, y, code, name):
    w, h = 390, 76
    draw.rectangle((x, y, x + w, y + h), fill=STORE_FILL, outline=MUTED, width=2)
    draw.line((x, y + 23, x + w, y + 23), fill=MUTED, width=2)
    draw.text((x + 16, y + 1), code, font=font(18, True), fill=INK)
    text_center(draw, (x + 28, y + 23, x + w - 8, y + h), name, font(17), MUTED)
    return (x, y + h // 2), (x + w, y + h // 2)


def draw_entity(draw, box, title, detail):
    rounded_box(draw, box, "#ffffff", LINE, 10, 3)
    left, top, right, bottom = box
    text_center(draw, (left + 12, top + 18, right - 12, top + 70), title, font(23, True))
    draw.line((left + 24, top + 82, right - 24, top + 82), fill="#cbd5e1", width=2)
    text_center(draw, (left + 15, top + 92, right - 15, bottom - 12), detail, font(17), MUTED)


def create_diagram(filename, number, title, subtitle, entities, process_lines, stores, result):
    image = Image.new("RGB", (WIDTH, HEIGHT), "white")
    draw = ImageDraw.Draw(image)
    text_center(draw, (80, 32, WIDTH - 80, 88), f"DFD Level 1 : กระบวนการ {number}", font(32, True))
    text_center(draw, (80, 92, WIDTH - 80, 135), title, font(25, True))
    text_center(draw, (80, 142, WIDTH - 80, 180), subtitle, font(18), MUTED)

    process_box = (650, 405, 1200, 810)
    rounded_box(draw, process_box, RED_SOFT, RED, 34, 4)
    text_center(draw, (680, 438, 1170, 500), number, font(24, True), RED)
    text_center(draw, (680, 520, 1170, 635), "\n".join(process_lines), font(24, True))
    text_center(draw, (690, 680, 1160, 760), "รับข้อมูล · ตรวจสอบ · บันทึก · ส่งผลลัพธ์", font(18), MUTED)

    entity_boxes = []
    for idx, (entity_title, entity_detail) in enumerate(entities):
        y = 270 + idx * 245
        box = (70, y, 490, y + 155)
        draw_entity(draw, box, entity_title, entity_detail)
        entity_boxes.append(box)
        middle_y = (box[1] + box[3]) // 2
        connect_y = 465 + min(idx * 110, 250)
        double_arrow(draw, (box[2], middle_y), (process_box[0], connect_y))
        draw.text((510, min(middle_y, connect_y) - 28), "ข้อมูลเข้า / ผลลัพธ์", font=font(15), fill=MUTED)

    positions = []
    for idx, (code, name) in enumerate(stores):
        col = idx % 2
        row = idx // 2
        x = 1390 + col * 410
        y = 245 + row * 120
        positions.append((x, y, code, name))
    for idx, (x, y, code, name) in enumerate(positions):
        store_left, _ = draw_store(draw, x, y, code, name)
        source_y = 460 + min(idx * 47, 300)
        elbow_x = 1260 + (idx % 2) * 40
        draw.line((process_box[2], source_y, elbow_x, source_y, elbow_x, store_left[1], store_left[0], store_left[1]), fill=LINE, width=3)
        arrow_head(draw, process_box[2], source_y, "left")
        arrow_head(draw, store_left[0], store_left[1], "right")
        draw.text((1230, min(source_y, store_left[1]) - 23), "อ่าน / บันทึก", font=font(14), fill=MUTED)

    draw.line((80, 1090, WIDTH - 80, 1090), fill="#cbd5e1", width=2)
    draw.text((95, 1130), result, font=font(18), fill=MUTED)
    draw.text((95, 1180), "ลูกศรสองหัวแสดงการอ่านข้อมูลจากคลังข้อมูลและการบันทึก/ปรับปรุงข้อมูลกลับเข้าสู่คลังข้อมูล", font=font(16), fill=MUTED)
    image.save(OUT / filename, "PNG")


def main():
    create_diagram(
        "dfd-level-1-process-1-account-access.png", "1.0", "จัดการบัญชี ผู้ใช้ และการแจ้งเตือน",
        "ผู้ใช้งาน 4 กลุ่มเข้าใช้งานระบบ และผู้ดูแลระบบกำหนดสิทธิ์", [
            ("ผู้ใช้งาน", "นักศึกษา · อาจารย์\nเจ้าหน้าที่สาขา · ผู้ดูแลระบบ"),
            ("ผู้ดูแลระบบ", "จัดการบัญชี\nและกำหนดสิทธิ์ผู้ใช้"),
        ], ["จัดการบัญชี ผู้ใช้", "สิทธิ์ และการแจ้งเตือน"], [
            ("D1", "users"), ("D2", "user_roles"), ("D3", "password_reset_otp"),
            ("D4", "prefixes"), ("D5", "activity_log"), ("D6", "notifications"),
        ], "ผลลัพธ์: ผู้ใช้เข้าสู่ระบบตามสิทธิ์ และได้รับการแจ้งเตือนที่เกี่ยวข้อง")
    create_diagram(
        "dfd-level-1-process-2-master-data.png", "2.0", "จัดการข้อมูลพื้นฐาน",
        "ผู้ดูแลระบบจัดการโครงสร้างคณะ สาขา บุคลากร กลุ่มเรียน และนักศึกษา", [
            ("ผู้ดูแลระบบ", "เพิ่ม · แก้ไข · ลบ · ค้นหา\nข้อมูลพื้นฐานของระบบ"),
        ], ["จัดการข้อมูลพื้นฐาน", "คณะ สาขา บุคลากร", "กลุ่มเรียน และนักศึกษา"], [
            ("D7", "faculty"), ("D8", "department"), ("D9", "positions"), ("D10", "employee"),
            ("D11", "employee_position"), ("D12", "office_holders"), ("D13", "group_class"), ("D14", "student"),
        ], "ผลลัพธ์: ข้อมูลอ้างอิงที่ถูกต้องสำหรับกำหนดผู้ใช้ กลุ่มเรียน ผู้พิจารณา และเอกสาร")
    create_diagram(
        "dfd-level-1-process-3-document-configuration.png", "3.0", "กำหนดเอกสารและลำดับผู้พิจารณา",
        "กำหนดประเภทเอกสารที่เปิดใช้งาน แบบฟอร์ม ช่องกรอก และเส้นทางการพิจารณา", [
            ("ผู้ดูแลระบบ", "กำหนดประเภทเอกสาร\nและลำดับผู้พิจารณา"),
        ], ["กำหนดเอกสาร", "แม่แบบ ช่องกรอก", "และ Workflow"], [
            ("D15", "document_type"), ("D16", "document_template"), ("D17", "template_fields"),
            ("D18", "review_workflows"), ("D19", "review_workflow_steps"),
        ], "ผลลัพธ์: นักศึกษาเห็นเอกสารที่เปิดใช้งาน และระบบกำหนดผู้พิจารณาได้ตามขั้นตอน")
    create_diagram(
        "dfd-level-1-process-4-request-submission.png", "4.0", "จัดทำและส่งคำร้อง",
        "นักศึกษาเลือกเอกสาร กรอกข้อมูล แนบไฟล์ บันทึกแบบร่าง และส่งคำร้อง", [
            ("นักศึกษา", "ข้อมูลคำร้อง\nและเอกสารแนบ"),
        ], ["จัดทำและส่งคำร้อง", "บันทึกแบบร่าง", "ตรวจทานและส่งพิจารณา"], [
            ("D20", "documents"), ("D21", "document_field_values"), ("D22", "document_attachments"),
            ("D23", "r01_request"), ("D24", "r12_request"), ("D25", "r12_request_courses"),
            ("D26", "r14_request"), ("D27", "r18_request"), ("D28", "r24_request"),
        ], "ผลลัพธ์: คำร้องและข้อมูลประกอบถูกส่งต่อเข้าสู่กระบวนการพิจารณา")
    create_diagram(
        "dfd-level-1-process-5-document-review.png", "5.0", "พิจารณาเอกสารและบันทึกประวัติ",
        "อาจารย์ตรวจสอบ อนุมัติ หรือส่งกลับแก้ไข พร้อมเก็บลำดับและเหตุการณ์", [
            ("อาจารย์ที่ปรึกษา", "ผลพิจารณา\nและข้อเสนอแนะ"),
            ("นักศึกษา", "รับผลการพิจารณา\nและแก้ไขเอกสาร"),
        ], ["พิจารณาเอกสาร", "อนุมัติ หรือส่งกลับ", "และบันทึกประวัติ"], [
            ("D20", "documents"), ("D29", "document_review_processes"),
            ("D30", "document_review_step_instances"), ("D31", "document_events"),
        ], "หมายเหตุ: D20 แสดงซ้ำจากกระบวนการ 4.0 เพราะเป็นเอกสารฉบับเดียวกันที่ถูกนำมาพิจารณา")
    create_diagram(
        "dfd-level-1-process-6-internship-management.png", "6.0", "จัดการฝึกประสบการณ์วิชาชีพ",
        "จัดการคำร้องสหกิจ เอกสารลงนาม หนังสือตอบรับ สถานะการรับเข้าฝึก และแผนนิเทศ", [
            ("นักศึกษา", "กรอกข้อมูลสหกิจ\nและอัปโหลดเอกสาร"),
            ("เจ้าหน้าที่สาขา", "ตรวจเอกสาร\nและบันทึกสถานะ"),
            ("อาจารย์", "พิจารณาเอกสาร\nและจัดทำแผนนิเทศ"),
        ], ["จัดการงานฝึกประสบการณ์", "เอกสาร สถานะ และ", "แผนนิเทศ"], [
            ("D32", "internship_calendar"), ("D33", "internship_profile"),
            ("D34", "internship_application"), ("D35", "internship_response_document"),
            ("D36", "internship_tracking"), ("D37", "internship_supervision"),
        ], "ผลลัพธ์: ติดตามความพร้อมของนักศึกษาและดำเนินการด้านฝึกประสบการณ์ตามสิทธิ์")
    create_diagram(
        "dfd-level-1-process-7-official-documents.png", "7.0", "สร้างหนังสือราชการและส่งออกข้อมูล",
        "เจ้าหน้าที่ใช้ข้อมูลฝึกประสบการณ์เพื่อสร้างหนังสือ PDF และไฟล์ Excel", [
            ("เจ้าหน้าที่สาขา", "เลือกนักศึกษา\nและสั่งสร้างเอกสาร"),
            ("ผลลัพธ์", "หนังสือ PDF\nและไฟล์ Microsoft Excel"),
        ], ["สร้างหนังสือราชการ", "และส่งออกข้อมูล"], [
            ("D38", "internship_official_document"), ("D39", "internship_official_student"),
            ("D40", "internship_excel_sync"),
        ], "ผลลัพธ์: หนังสือขอความอนุเคราะห์ ใบส่งตัว และรายงานข้อมูลสำหรับเจ้าหน้าที่")


if __name__ == "__main__":
    main()
