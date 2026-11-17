"""Render the seven ER groups as table-style ER diagrams.

Each entity follows the requested layout: data type | attribute | PK/FK.
The schema is read from the current SQL export so all displayed attributes
match the database definition.
"""
from pathlib import Path
import re
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
SQL = Path(r"C:\Users\mewww\Downloads\online_request (5).sql")
OUT = ROOT / "output"
FONT = Path(r"C:\Windows\Fonts\tahoma.ttf")
BOLD = Path(r"C:\Windows\Fonts\tahomabd.ttf")
TABLE_W = 620
TYPE_W, MARK_W = 180, 86


def font(size, bold=False):
    return ImageFont.truetype(str(BOLD if bold else FONT), size)


def load_schema():
    text = SQL.read_text(encoding="utf-8")
    tables, foreign_keys = {}, {}
    for name, body in re.findall(r"CREATE TABLE `([^`]+)` \((.*?)\n\) ENGINE", text, re.S):
        columns = []
        for column, definition in re.findall(r"^  `([^`]+)` (.+?)(?:,)?$", body, re.M):
            data_type = re.match(r"([a-zA-Z]+)", definition).group(1).lower()
            columns.append((column, data_type))
        pk_match = re.search(r"PRIMARY KEY \(([^)]+)\)", body)
        primary = set(re.findall(r"`([^`]+)`", pk_match.group(1))) if pk_match else set()
        tables[name] = {"columns": columns, "primary": primary}
    for table_name, alter_body in re.findall(r"ALTER TABLE `([^`]+)`\s+(.*?);", text, re.S):
        pk_match = re.search(r"PRIMARY KEY \(([^)]+)\)", alter_body)
        if pk_match and table_name in tables:
            tables[table_name]["primary"] = set(re.findall(r"`([^`]+)`", pk_match.group(1)))
        for local, target_table, target_column in re.findall(
            r"FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)", alter_body
        ):
            foreign_keys.setdefault(table_name, {}).setdefault(local, []).append((target_table, target_column))
    # The exported baseline predates the identifier rename migration.  Apply
    # the same structural names here so the rendered ER mirrors the live DB.
    for table_name, renames in {
        "student": {"student_code": "student_number"},
        "department": {"department_code": "department_abbreviation"},
        "faculty": {"faculty_code": "faculty_abbreviation"},
    }.items():
        tables[table_name]["columns"] = [
            (renames.get(column, column), data_type)
            for column, data_type in tables[table_name]["columns"]
        ]
        tables[table_name]["primary"] = {renames.get(column, column) for column in tables[table_name]["primary"]}
    tables["group_class"]["columns"] = [
        (column, data_type)
        for column, data_type in tables["group_class"]["columns"]
        if column != "student_prefix"
    ]
    tables["office_holders"]["columns"] = [
        (column, data_type)
        for column, data_type in tables["office_holders"]["columns"]
        if column != "faculty_id"
    ]
    FKS_FOR_OFFICE_HOLDERS = foreign_keys.get("office_holders", {})
    FKS_FOR_OFFICE_HOLDERS.pop("faculty_id", None)
    tables["document_template"]["columns"] = [
        (column, data_type)
        for column, data_type in tables["document_template"]["columns"]
        if column != "version"
    ]
    tables.pop("internship_submission", None)
    foreign_keys.pop("internship_submission", None)
    return tables, foreign_keys


SCHEMA, FKS = load_schema()


GROUPS = [
    ("1", "บัญชีผู้ใช้ สิทธิ์ และการแจ้งเตือน", [
        ("users", 160, 320), ("user_roles", 990, 180), ("password_reset_otp", 1820, 180),
        ("activity_log", 990, 900), ("notifications", 1820, 900),
    ]),
    ("2", "คณะ สาขา บุคลากร กลุ่มเรียน และนักศึกษา", [
        ("faculty", 120, 140), ("department", 970, 140), ("group_class", 1820, 140),
        ("prefixes", 120, 850), ("positions", 970, 850), ("employee", 1820, 780),
        ("student", 2670, 280), ("employee_position", 2670, 1050), ("office_holders", 3520, 880),
    ]),
    ("3", "ประเภทเอกสาร แม่แบบ ช่องกรอก และขั้นตอนพิจารณา", [
        ("document_type", 130, 300), ("document_template", 980, 170), ("template_fields", 1830, 110),
        ("review_workflows", 980, 1050), ("review_workflow_steps", 1830, 960),
    ]),
    ("4", "ข้อมูลเอกสารคำร้องและแบบฟอร์ม R", [
        ("documents", 100, 650), ("document_field_values", 950, 110), ("document_attachments", 950, 760),
        ("r01_request", 1800, 80), ("r12_request", 2650, 80), ("r12_request_courses", 3500, 130),
        ("r14_request", 1800, 930), ("r18_request", 2650, 930), ("r24_request", 3500, 930),
    ]),
    ("5", "กระบวนการพิจารณาและประวัติเอกสาร", [
        ("documents", 130, 500), ("document_review_processes", 980, 230),
        ("document_review_step_instances", 1830, 120), ("document_events", 2680, 360),
    ]),
    ("6", "การฝึกประสบการณ์วิชาชีพและแผนนิเทศ", [
        ("internship_calendar", 100, 100), ("internship_profile", 980, 90), ("internship_application", 1830, 100),
        ("internship_response_document", 2680, 100),
        ("internship_tracking", 2680, 1300), ("internship_supervision", 3530, 1200),
    ]),
    ("7", "หนังสือราชการและการเชื่อมต่อส่งออกข้อมูล", [
        ("internship_official_document", 120, 210), ("internship_official_student", 970, 310),
        ("internship_profile", 1820, 100), ("internship_excel_sync", 2670, 200),
        ("department", 3520, 120),
    ]),
]


def table_height(name):
    return 58 + len(SCHEMA[name]["columns"]) * 38


def box_for(node):
    name, x, y = node
    return (x, y, x + TABLE_W, y + table_height(name))


def label(draw, xy, text, size=19, color="#111827", bold=False):
    draw.text(xy, text, fill=color, font=font(size, bold))


def draw_table(draw, node):
    name, x, y = node
    cols = SCHEMA[name]["columns"]
    height = table_height(name)
    draw.rectangle((x, y, x + TABLE_W, y + height), fill="#ffffff", outline="#8b5cf6", width=2)
    draw.rectangle((x, y, x + TABLE_W, y + 58), fill="#f5f3ff", outline="#8b5cf6", width=2)
    header_box = draw.textbbox((0, 0), name, font=font(22, True))
    label(draw, (x + (TABLE_W - (header_box[2] - header_box[0])) / 2, y + 15), name, 22, "#111827", True)
    draw.line((x + TYPE_W, y + 58, x + TYPE_W, y + height), fill="#8b5cf6", width=1)
    draw.line((x + TABLE_W - MARK_W, y + 58, x + TABLE_W - MARK_W, y + height), fill="#8b5cf6", width=1)
    for index, (column, data_type) in enumerate(cols):
        row_y = y + 58 + index * 38
        draw.line((x, row_y, x + TABLE_W, row_y), fill="#8b5cf6", width=1)
        marker = []
        if column in SCHEMA[name]["primary"]:
            marker.append("PK")
        if column in FKS.get(name, {}):
            marker.append("FK")
        label(draw, (x + 15, row_y + 8), data_type, 18)
        label(draw, (x + TYPE_W + 15, row_y + 8), column, 18)
        if marker:
            label(draw, (x + TABLE_W - MARK_W + 12, row_y + 8), ",".join(marker), 16, "#6d28d9", True)
    draw.line((x, y + height, x + TABLE_W, y + height), fill="#8b5cf6", width=2)


def relation(draw, source, target, source_label="1", target_label="N"):
    sx1, sy1, sx2, sy2 = source
    tx1, ty1, tx2, ty2 = target
    if sx2 <= tx1:
        p1, p2 = (sx2, (sy1 + sy2) // 2), (tx1, (ty1 + ty2) // 2)
    elif tx2 <= sx1:
        p1, p2 = (sx1, (sy1 + sy2) // 2), (tx2, (ty1 + ty2) // 2)
    elif sy2 <= ty1:
        p1, p2 = ((sx1 + sx2) // 2, sy2), ((tx1 + tx2) // 2, ty1)
    else:
        p1, p2 = ((sx1 + sx2) // 2, sy1), ((tx1 + tx2) // 2, ty2)
    midx, midy = (p1[0] + p2[0]) // 2, (p1[1] + p2[1]) // 2
    if p1[0] == p2[0]:
        path = (p1[0], p1[1], p1[0], midy, p2[0], midy, p2[0], p2[1])
    else:
        path = (p1[0], p1[1], midx, p1[1], midx, p2[1], p2[0], p2[1])
    draw.line(path, fill="#475569", width=3)
    return p1, p2, source_label, target_label


def cardinality(draw, relation_data):
    p1, p2, source_label, target_label = relation_data
    for point, cardinality in ((p1, source_label), (p2, target_label)):
        draw.ellipse((point[0] - 7, point[1] - 7, point[0] + 7, point[1] + 7), fill="#7c3aed")
        label(draw, (point[0] + 12, point[1] - 27), cardinality, 17, "#6d28d9", True)


def create(group, title, nodes):
    boxes = {node[0]: box_for(node) for node in nodes}
    max_y = max(box[3] for box in boxes.values())
    max_x = max(box[2] for box in boxes.values())
    width, height = max(4300, max_x + 160), max(1850, max_y + 210)
    image = Image.new("RGB", (width, height), "white")
    draw = ImageDraw.Draw(image)
    title_text = f"ER Diagram กลุ่มที่ {group} — {title}"
    bbox = draw.textbbox((0, 0), title_text, font=font(32, True))
    label(draw, ((width - (bbox[2] - bbox[0])) / 2, 28), title_text, 32, "#111827", True)
    label(draw, (90, 82), "รูปแบบตาราง: ชนิดข้อมูล | แอททริบิวต์ | คีย์ (PK/FK)", 18, "#475569")
    # Actual foreign keys are the relationship source.  Only lines where both
    # entities belong to the current group are drawn; shared entities repeat in
    # the diagram where their details are needed.
    names = set(boxes)
    markers = []
    for local_table, refs_by_column in FKS.items():
        if local_table not in names:
            continue
        for refs in refs_by_column.values():
            for target, _target_col in refs:
                if target in names:
                    markers.append(relation(draw, boxes[target], boxes[local_table]))
    for node in nodes:
        draw_table(draw, node)
    for marker in markers:
        cardinality(draw, marker)
    label(draw, (90, height - 70), "สัญลักษณ์: PK = Primary Key, FK = Foreign Key, 1 = หนึ่งรายการ, N = หลายรายการ", 18, "#475569")
    image.save(OUT / f"er-table-group-{group}.png", "PNG")


def main():
    OUT.mkdir(exist_ok=True)
    for group, title, nodes in GROUPS:
        create(group, title, nodes)


if __name__ == "__main__":
    main()
