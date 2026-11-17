-- Readiness exception for explicitly imported records, not an approval or uploaded file.
CREATE TABLE IF NOT EXISTS internship_direct_send_import (
 internship_profile_id INT NOT NULL PRIMARY KEY,
 source_filename VARCHAR(255) NOT NULL,
 source_sha256 CHAR(64) NOT NULL,
 source_row INT NOT NULL,
 import_note TEXT NOT NULL,
 imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (internship_profile_id) REFERENCES internship_profile(internship_profile_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
