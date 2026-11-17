<?php

require_once __DIR__ . '/../../middleware/admin.php';

// The legacy combined-user create action is no longer available.
header('Location: ../dashboard.php');
exit;
