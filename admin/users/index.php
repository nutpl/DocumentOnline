<?php

require_once __DIR__ . '/../../middleware/admin.php';

// The combined user-management screen was retired in favor of the
// role-specific student, teacher, and staff management screens.
header('Location: ../dashboard.php');
exit;
