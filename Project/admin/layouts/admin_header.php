<?php

require_once __DIR__ . '/../../helpers/theme_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pageTitle)) {
    $pageTitle = "Admin Dashboard";
}

?>

<!DOCTYPE html>

<html lang="th">

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($pageTitle) ?>
    </title>

    <!-- Tailwind -->

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <!-- Admin CSS -->

    <link
        rel="stylesheet"
        href="/Project/assets/css/admin.css?v=20260911-1"
    >

    <link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20261107-1">
    <link rel="stylesheet" href="/Project/assets/css/app-ui.css?v=20260918-1">
    <?php renderThemeAssets(); ?>
    <script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>
    <script src="/Project/assets/js/unified-page-title.js?v=20260902-2" defer></script>

</head>


<body class="bg-gray-100">

<?php if (!empty($_SESSION['admin_success'])): ?>
    <div hidden data-success-alert data-success-title="ดำเนินการสำเร็จ" data-success-text="<?= htmlspecialchars((string) $_SESSION['admin_success'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php unset($_SESSION['admin_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['admin_error'])): ?>
    <div hidden data-success-alert data-success-icon="error" data-success-title="ไม่สามารถดำเนินการได้" data-success-text="<?= htmlspecialchars((string) $_SESSION['admin_error'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php unset($_SESSION['admin_error']); ?>
<?php endif; ?>


<div class="min-h-screen">
