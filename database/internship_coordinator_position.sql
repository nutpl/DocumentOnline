INSERT INTO positions (position_name)
SELECT 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ'
WHERE NOT EXISTS (SELECT 1 FROM positions WHERE position_name='อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ');

INSERT INTO employee_position (employee_id,position_id)
SELECT e.employee_id,p.position_id
FROM employee e CROSS JOIN positions p
WHERE e.firstname='สุภัทรา' AND e.lastname='เกิดเมฆ'
  AND p.position_name='อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ'
  AND NOT EXISTS (SELECT 1 FROM employee_position ep WHERE ep.employee_id=e.employee_id AND ep.position_id=p.position_id);
