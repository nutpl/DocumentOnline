-- บุคลากรประจำสาขาวิชาวิศวกรรมคอมพิวเตอร์ (department_id = 1)
-- รหัส CPE-xxx เป็นรหัสภายในที่กำหนดเพื่อใช้งานในระบบ และยังไม่สร้างบัญชีเข้าสู่ระบบ

INSERT IGNORE INTO positions (position_name) VALUES
('หัวหน้าสาขาวิชาวิศวกรรมคอมพิวเตอร์'),
('อาจารย์ประจำสาขา'),
('อาจารย์กิจกรรม'),
('ประธานหลักสูตร'),
('ธุรการประจำสาขา');

INSERT INTO prefixes (prefix_name, prefix_short, status)
SELECT 'นาย', 'นาย', 'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_name='นาย');
INSERT INTO prefixes (prefix_name, prefix_short, status)
SELECT 'นาง', 'นาง', 'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_name='นาง');
INSERT INTO prefixes (prefix_name, prefix_short, status)
SELECT 'นางสาว', 'นางสาว', 'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_name='นางสาว');
INSERT INTO prefixes (prefix_name, prefix_short, status)
SELECT 'ผู้ช่วยศาสตราจารย์', 'ผศ.', 'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_short='ผศ.');
INSERT INTO prefixes (prefix_name, prefix_short, status)
SELECT 'ผู้ช่วยศาสตราจารย์ ดร.', 'ผศ.ดร.', 'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_short='ผศ.ดร.');
INSERT INTO prefixes (prefix_name, prefix_short, status)
SELECT 'ดร.', 'ดร.', 'active' WHERE NOT EXISTS (SELECT 1 FROM prefixes WHERE prefix_name='ดร.');

INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-001', 'สนั่น', 'จันทร์พรม', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-001');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-002', 'วิทยา', 'ศรีกุล', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-002');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-003', 'ธรรมกร', 'ครองไตรภพ', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-003');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-004', 'ชิติสรรค์', 'วิชิโต', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-004');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-005', 'วุฒิพงษ์', 'พิชิตวงศ์', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-005');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-006', 'ประกาย', 'นาดี', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-006');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-007', 'สุภัทรา', 'เกิดเมฆ', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-007');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-008', 'ทิพา', 'กองศรีมา', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-008');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-009', 'เกตุกาญจน์', 'โพธิจิตติกานต์', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-009');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-010', 'อภิชาต', 'ติรประเสริฐสิน', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-010');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-011', 'พุทธาวุฒิ', 'ลีกุลธร', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-011');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-012', 'พรภัสสร', 'อ่อนเกิด', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-012');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-013', 'กีระชาติ', 'สุขสิทธิ์', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-013');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-014', 'รติพร', 'จันทร์กลั่น', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-014');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-015', 'ปริญญา', 'กิตติสุทธิ์', NULL, 1, 'teacher' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-015');
INSERT INTO employee (user_id, employee_code, firstname, lastname, email, department_id, employee_type)
SELECT NULL, 'CPE-016', 'สุกัญญา', 'สังข์กลาง', NULL, 1, 'staff' WHERE NOT EXISTS (SELECT 1 FROM employee WHERE employee_code='CPE-016');

INSERT INTO employee_position (employee_id, position_id, is_primary)
SELECT e.employee_id, p.position_id, 1 FROM employee e JOIN positions p
WHERE e.employee_code='CPE-001' AND p.position_name='หัวหน้าสาขาวิชาวิศวกรรมคอมพิวเตอร์'
AND NOT EXISTS (SELECT 1 FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.position_id=p.position_id);
INSERT INTO employee_position (employee_id, position_id, is_primary)
SELECT e.employee_id, p.position_id, 1 FROM employee e JOIN positions p
WHERE e.employee_code IN ('CPE-002','CPE-003','CPE-005','CPE-006','CPE-007','CPE-008','CPE-009','CPE-010','CPE-011','CPE-013','CPE-014','CPE-015') AND p.position_name='อาจารย์ประจำสาขา'
AND NOT EXISTS (SELECT 1 FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.position_id=p.position_id);
INSERT INTO employee_position (employee_id, position_id, is_primary)
SELECT e.employee_id, p.position_id, 1 FROM employee e JOIN positions p
WHERE e.employee_code='CPE-004' AND p.position_name='อาจารย์กิจกรรม'
AND NOT EXISTS (SELECT 1 FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.position_id=p.position_id);
INSERT INTO employee_position (employee_id, position_id, is_primary)
SELECT e.employee_id, p.position_id, 1 FROM employee e JOIN positions p
WHERE e.employee_code='CPE-012' AND p.position_name='ประธานหลักสูตร'
AND NOT EXISTS (SELECT 1 FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.position_id=p.position_id);
INSERT INTO employee_position (employee_id, position_id, is_primary)
SELECT e.employee_id, p.position_id, 1 FROM employee e JOIN positions p
WHERE e.employee_code='CPE-016' AND p.position_name='ธุรการประจำสาขา'
AND NOT EXISTS (SELECT 1 FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.position_id=p.position_id);
