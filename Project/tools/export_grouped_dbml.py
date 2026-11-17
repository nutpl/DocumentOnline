"""Create a grouped DBML ER source for dbdiagram.io from the current schema export."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
SQL = ROOT / "output" / "online_request_er.sql"
OUT = ROOT / "output" / "online_request_er_grouped.dbml"

GROUPS = [
    ("group_1_identity", "บัญชีผู้ใช้ สิทธิ์ และการแจ้งเตือน", "#3B82F6",
     ["users", "user_roles", "password_reset_otp", "activity_log", "notifications"]),
    ("group_2_organization", "คณะ สาขา บุคลากร กลุ่มเรียน และนักศึกษา", "#8B5CF6",
     ["faculty", "department", "prefixes", "positions", "employee", "employee_position", "office_holders", "group_class", "student"]),
    ("group_3_document_configuration", "ประเภทเอกสาร แม่แบบ และลำดับพิจารณา", "#F97316",
     ["document_type", "document_template", "template_fields", "review_workflows", "review_workflow_steps"]),
    ("group_4_requests", "การจัดทำและส่งคำร้อง", "#EF4444",
     ["documents", "document_field_values", "document_attachments", "r01_request", "r12_request", "r12_request_courses", "r14_request", "r18_request", "r24_request"]),
    ("group_5_review_history", "การพิจารณาเอกสารและประวัติ", "#A855F7",
     ["document_review_processes", "document_review_step_instances", "document_events"]),
    ("group_6_internship", "งานฝึกประสบการณ์วิชาชีพและแผนนิเทศ", "#10B981",
     ["internship_calendar", "internship_profile", "internship_application", "internship_response_document", "internship_tracking", "internship_supervision"]),
    ("group_7_official_output", "หนังสือราชการและการส่งออกข้อมูล", "#EAB308",
     ["internship_official_document", "internship_official_student", "internship_excel_sync"]),
]


def parse_schema():
    text = SQL.read_text(encoding="utf-8-sig")
    tables = {}
    for match in re.finditer(r"CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=", text, re.S):
        table_name, body = match.groups()
        columns = []
        for column_name, definition in re.findall(r"^\s*`([^`]+)`\s+(.+?)(?:,)?$", body, re.M):
            data_type = re.match(r"([A-Za-z]+)", definition).group(1).lower()
            columns.append((column_name, data_type, definition))
        primary = set()
        primary_match = re.search(r"PRIMARY KEY \(([^)]+)\)", body)
        if primary_match:
            primary = set(re.findall(r"`([^`]+)`", primary_match.group(1)))
        unique = set()
        for key_columns in re.findall(r"UNIQUE KEY `[^`]+` \(([^)]+)\)", body):
            names = re.findall(r"`([^`]+)`", key_columns)
            if len(names) == 1:
                unique.add(names[0])
        tables[table_name] = {"columns": columns, "primary": primary, "unique": unique}
    clean_refs = []
    for match in re.finditer(r"CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=", text, re.S):
        child_table, body = match.groups()
        for local_column, parent_table, parent_column in re.findall(
            r"FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)", body
        ):
            clean_refs.append((child_table, local_column, parent_table, parent_column))
    return tables, clean_refs


def column_settings(column_name, definition, schema):
    settings = []
    if column_name in schema["primary"]:
        settings.append("pk")
    if "AUTO_INCREMENT" in definition:
        settings.append("increment")
    if "NOT NULL" in definition:
        settings.append("not null")
    if column_name in schema["unique"]:
        settings.append("unique")
    return f" [{', '.join(settings)}]" if settings else ""


TABLES, REFS = parse_schema()
lines = [
    "Project online_request_er {",
    "  database_type: 'MySQL'",
    "  Note: 'ER ฐานข้อมูล online_request: 40 ตาราง แบ่งตามขอบเขตการทำงาน 7 กลุ่ม'",
    "}",
    "",
]

for table_name in sorted(TABLES):
    schema = TABLES[table_name]
    lines.append(f"Table {table_name} {{")
    for column_name, data_type, definition in schema["columns"]:
        lines.append(f"  {column_name} {data_type}{column_settings(column_name, definition, schema)}")
    lines.append("}")
    lines.append("")

for group_name, note, color, table_names in GROUPS:
    lines.append(f"TableGroup {group_name} [color: {color}, note: '{note}'] {{")
    lines.extend(f"  {table_name}" for table_name in table_names)
    lines.append("}")
    lines.append("")

lines.append("// ความสัมพันธ์: ฝั่งซ้ายเป็นตารางที่เก็บ FK และชี้ไปยัง PK/FK ฝั่งขวา")
for child_table, local_column, parent_table, parent_column in REFS:
    lines.append(f"Ref: {child_table}.{local_column} > {parent_table}.{parent_column}")

OUT.write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"Created {OUT} with {len(TABLES)} tables, {len(GROUPS)} groups, and {len(REFS)} relationships.")
