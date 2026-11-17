<?php

require_once __DIR__ . '/../../middleware/admin.php';

// Account removal is handled by the relevant role-specific screen.
header('Location: ../dashboard.php');
exit;
