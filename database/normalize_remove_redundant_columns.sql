-- Database normalization for online_request
-- Run after the application code has been updated to read the canonical source.
-- Backup created before this migration:
-- database/backups/online_request_before_normalization_20260822.sql

START TRANSACTION;

-- Canonical source of email: users.email
-- Canonical source of a student's advisor: group_class.advisor_employee_id
-- Canonical source of internship term and dates: internship_calendar
ALTER TABLE student
    DROP FOREIGN KEY fk_student_advisor,
    DROP INDEX fk_student_advisor,
    DROP COLUMN email,
    DROP COLUMN advisor_employee_id;

ALTER TABLE employee
    DROP COLUMN email;

ALTER TABLE users
    DROP FOREIGN KEY fk_users_department,
    DROP INDEX fk_users_department,
    DROP COLUMN department_id;

ALTER TABLE internship_profile
    DROP COLUMN academic_year,
    DROP COLUMN semester,
    DROP COLUMN start_date,
    DROP COLUMN end_date;

COMMIT;
