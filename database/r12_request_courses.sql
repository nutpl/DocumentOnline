CREATE TABLE IF NOT EXISTS r12_request_courses (
    r12_course_id INT NOT NULL AUTO_INCREMENT,
    r12_id INT NOT NULL,
    item_no TINYINT UNSIGNED NOT NULL,
    semester VARCHAR(50) DEFAULT NULL,
    course_code VARCHAR(50) DEFAULT NULL,
    course_name VARCHAR(255) DEFAULT NULL,
    section VARCHAR(100) DEFAULT NULL,
    old_group VARCHAR(100) DEFAULT NULL,
    new_group VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (r12_course_id),
    UNIQUE KEY uq_r12_course_item (r12_id, item_no),
    CONSTRAINT fk_r12_course_request FOREIGN KEY (r12_id)
        REFERENCES r12_request (r12_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
