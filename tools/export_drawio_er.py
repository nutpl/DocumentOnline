"""Export the current MySQL schema to an editable seven-page diagrams.net ER file."""
from datetime import datetime, timezone
from html import escape
from pathlib import Path
import re
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
SQL = ROOT / "output" / "online_request_er.sql"
OUT = ROOT / "output" / "online_request_er.drawio"

GROUPS = [
    ("กลุ่ม 1 บัญชีผู้ใช้ สิทธิ์ และการแจ้งเตือน",
     ["users", "user_roles", "password_reset_otp", "activity_log", "notifications"],
     {"users": (520, 100), "user_roles": (960, 60), "password_reset_otp": (960, 360),
      "activity_log": (960, 650), "notifications": (1400, 430)}),
    ("กลุ่ม 2 คณะ สาขา บุคลากร กลุ่มเรียน และนักศึกษา",
     ["faculty", "department", "prefixes", "positions", "employee", "employee_position", "office_holders", "group_class", "student"],
     {"faculty": (60, 80), "department": (450, 80), "prefixes": (60, 420), "positions": (450, 420),
      "employee": (850, 250), "employee_position": (1250, 120), "office_holders": (1650, 100),
      "group_class": (1250, 590), "student": (1650, 570)}),
    ("กลุ่ม 3 ประเภทเอกสาร แม่แบบ และลำดับพิจารณา",
     ["document_type", "document_template", "template_fields", "review_workflows", "review_workflow_steps"],
     {"document_type": (80, 250), "document_template": (520, 80), "template_fields": (960, 70),
      "review_workflows": (520, 600), "review_workflow_steps": (960, 550)}),
    ("กลุ่ม 4 การจัดทำและส่งคำร้อง",
     ["documents", "document_field_values", "document_attachments", "r01_request", "r12_request", "r12_request_courses", "r14_request", "r18_request", "r24_request"],
     {"documents": (80, 420), "document_field_values": (520, 70), "document_attachments": (520, 520),
      "r01_request": (960, 50), "r12_request": (1350, 50), "r12_request_courses": (1740, 70),
      "r14_request": (960, 520), "r18_request": (1350, 520), "r24_request": (1740, 520)}),
    ("กลุ่ม 5 การพิจารณาเอกสารและประวัติ",
     ["documents", "document_review_processes", "document_review_step_instances", "document_events"],
     {"documents": (80, 360), "document_review_processes": (520, 170),
      "document_review_step_instances": (980, 70), "document_events": (980, 570)}),
    ("กลุ่ม 6 งานฝึกประสบการณ์วิชาชีพและแผนนิเทศ",
     ["internship_calendar", "internship_profile", "internship_application", "internship_response_document", "internship_tracking", "internship_supervision"],
     {"internship_calendar": (50, 90), "internship_profile": (440, 50), "internship_application": (860, 60),
      "internship_response_document": (1260, 60), "internship_tracking": (1260, 470), "internship_supervision": (1680, 470)}),
    ("กลุ่ม 7 หนังสือราชการและการส่งออกข้อมูล",
     ["internship_official_document", "internship_official_student", "internship_excel_sync", "internship_profile", "department", "users"],
     {"internship_official_document": (80, 230), "internship_official_student": (500, 260),
      "internship_profile": (920, 80), "internship_excel_sync": (920, 650), "department": (1340, 600), "users": (1340, 130)}),
]


def parse_schema():
    text = SQL.read_text(encoding="utf-8-sig")
    tables, foreign_keys = {}, []
    for match in re.finditer(r"CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=", text, re.S):
        name, body = match.groups()
        columns = []
        for column, definition in re.findall(r"^\s*`([^`]+)`\s+(.+?)(?:,)?$", body, re.M):
            data_type = re.match(r"([A-Za-z]+)", definition).group(1).lower()
            columns.append((column, data_type))
        primary_match = re.search(r"PRIMARY KEY \(([^)]+)\)", body)
        primary = set(re.findall(r"`([^`]+)`", primary_match.group(1))) if primary_match else set()
        unique_single = set()
        for unique_columns in re.findall(r"UNIQUE KEY `[^`]+` \(([^)]+)\)", body):
            names = re.findall(r"`([^`]+)`", unique_columns)
            if len(names) == 1:
                unique_single.add(names[0])
        tables[name] = {"columns": columns, "primary": primary, "unique": unique_single}
        for local, parent, parent_column in re.findall(
            r"FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)", body
        ):
            foreign_keys.append((parent, parent_column, name, local))
    return tables, foreign_keys


def table_html(name, schema):
    rows = [
        "<tr><td colspan='3' style='background:#ede9fe;font-weight:bold;text-align:center;padding:6px'>"
        f"{escape(name)}</td></tr>"
    ]
    for column, data_type in schema["columns"]:
        key = "PK" if column in schema["primary"] else "FK" if any(
            local == column and child == name for _parent, _parent_col, child, local in FOREIGN_KEYS
        ) else ""
        rows.append(
            "<tr><td style='width:72px;padding:4px;border-top:1px solid #c4b5fd'>"
            f"{escape(data_type)}</td><td style='padding:4px;border-top:1px solid #c4b5fd'>{escape(column)}</td>"
            f"<td style='width:38px;padding:4px;border-top:1px solid #c4b5fd;text-align:center'>{key}</td></tr>"
        )
    return "<table style='width:100%;border-collapse:collapse;font-size:12px'>" + "".join(rows) + "</table>"


def add_cell(root, cell_id, value, style, x, y, width, height):
    cell = ET.SubElement(root, "mxCell", {"id": cell_id, "value": value, "style": style, "vertex": "1", "parent": "1"})
    ET.SubElement(cell, "mxGeometry", {"x": str(x), "y": str(y), "width": str(width), "height": str(height), "as": "geometry"})


def diagram_xml(group_no, title, names, positions):
    model = ET.Element("mxGraphModel", {"dx": "1600", "dy": "1000", "grid": "1", "gridSize": "10", "guides": "1", "tooltips": "1", "connect": "1", "arrows": "1", "fold": "1", "page": "1", "pageScale": "1", "pageWidth": "1169", "pageHeight": "827", "math": "0", "shadow": "0"})
    root = ET.SubElement(model, "root")
    ET.SubElement(root, "mxCell", {"id": "0"})
    ET.SubElement(root, "mxCell", {"id": "1", "parent": "0"})
    add_cell(root, f"title_{group_no}", title, "text;html=1;fontSize=20;fontStyle=1;align=left;verticalAlign=middle;", 40, 15, 1300, 30)
    node_ids = {}
    for name in names:
        node_id = f"g{group_no}_{name}"
        node_ids[name] = node_id
        x, y = positions[name]
        height = 38 + len(TABLES[name]["columns"]) * 25
        add_cell(root, node_id, table_html(name, TABLES[name]), "rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#8b5cf6;align=left;verticalAlign=top;spacing=0;", x, y, 340, height)
    edge_no = 0
    for parent, parent_column, child, fk_column in FOREIGN_KEYS:
        if parent not in node_ids or child not in node_ids:
            continue
        edge_no += 1
        edge = ET.SubElement(root, "mxCell", {
            "id": f"edge_{group_no}_{edge_no}", "value": "",
            "style": "edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;startArrow=ERone;endArrow=ERmany;",
            "edge": "1", "parent": "1", "source": node_ids[parent], "target": node_ids[child],
        })
        ET.SubElement(edge, "mxGeometry", {"relative": "1", "as": "geometry"})
    return ET.tostring(model, encoding="unicode")


TABLES, FOREIGN_KEYS = parse_schema()
root = ET.Element("mxfile", {
    "host": "app.diagrams.net", "modified": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    "agent": "Codex", "version": "26.0.0", "type": "device",
})
for number, (title, names, positions) in enumerate(GROUPS, 1):
    diagram = ET.SubElement(root, "diagram", {"id": f"er-group-{number}", "name": title})
    diagram.append(ET.fromstring(diagram_xml(number, title, names, positions)))

ET.indent(root, space="  ")
OUT.write_text(ET.tostring(root, encoding="unicode"), encoding="utf-8")
print(f"Created {OUT} with {len(GROUPS)} pages, {len(TABLES)} tables, and {len(FOREIGN_KEYS)} foreign keys.")
