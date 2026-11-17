<?php

require_once __DIR__ . '/../../middleware/admin.php';

// User creation is handled by each role-specific management screen.
header('Location: ../dashboard.php');
exit;
