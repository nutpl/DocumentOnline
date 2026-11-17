-- Structured assignment for the cooperative-education supervision plan.
ALTER TABLE internship_supervision
    ADD COLUMN IF NOT EXISTS supervisor_employee_id INT NULL AFTER internship_profile_id,
    ADD INDEX IF NOT EXISTS idx_internship_supervisor_employee (supervisor_employee_id);

-- Run this only once on servers whose MariaDB version does not support
-- ADD CONSTRAINT IF NOT EXISTS.
-- ALTER TABLE internship_supervision
--   ADD CONSTRAINT fk_internship_supervisor_employee
--   FOREIGN KEY (supervisor_employee_id) REFERENCES employee(employee_id)
--   ON DELETE SET NULL ON UPDATE CASCADE;

