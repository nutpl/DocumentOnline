<?php
session_start();
require_once '../middleware/student.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/alert_response_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: internship_form.php');
    exit;
}
verify_csrf_or_abort();
renderSweetAlertResponse('ไม่สามารถแก้ไขได้', 'เอกสารถูกส่งให้เจ้าหน้าที่แล้ว หากต้องแก้ไขกรุณาติดต่อเจ้าหน้าที่', 'info', 'internship_form.php', 409);
