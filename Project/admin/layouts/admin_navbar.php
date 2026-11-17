<?php

$adminName = $_SESSION['username'] ?? 'Admin';

if (isset($_SESSION['user_id'])) {

    require_once __DIR__ . "/../../config/database.php";

    $stmt = $pdo->prepare("

        SELECT firstname, lastname

        FROM employee

        WHERE user_id = ?

        LIMIT 1

    ");

    $stmt->execute([

        $_SESSION['user_id']

    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($admin) {

        $adminName = trim(

            ($admin['firstname'] ?? '')

            . ' '

            .

            ($admin['lastname'] ?? '')

        );

    }

}

?>
<!-- MAIN AREA -->

<div class="lg:ml-72 min-h-screen">


    <!-- NAVBAR -->

    <header

        class="min-h-16

               bg-white

               border-b

               border-gray-200

               flex

               items-center

               justify-between

               flex-wrap

               gap-y-2

               px-4

               md:px-6

               py-2

               sticky

               top-0

               z-30"

    >


        <!-- LEFT -->

        <div class="flex items-center gap-4">


            <!-- MOBILE MENU BUTTON -->

            <button

                id="sidebar-toggle"

                type="button"

                class="lg:hidden

                       text-gray-600

                       hover:text-red-600

                       text-xl"

                aria-label="เปิดเมนู"

                aria-expanded="false"

            >

                <i class="fa-solid fa-bars"></i>

            </button>

        </div>


        <!-- RIGHT -->

        <div class="flex items-center gap-4 <?= !empty($adminNavbarSearch) ? 'w-full lg:w-auto' : '' ?>">
            <?php require_once __DIR__ . '/../../helpers/role_switcher_helper.php'; renderRoleSwitcher(); ?>
            <?php renderThemeToggle(); ?>
            <?php require_once __DIR__ . '/../../helpers/activity_history_popover_helper.php'; renderActivityHistoryPopover($pdo); ?>

            <?php foreach (($adminNavbarActions ?? []) as $adminNavbarAction): ?>
                <a href="<?= htmlspecialchars($adminNavbarAction['href'] ?? '#') ?>" <?= !empty($adminNavbarAction['target']) ? 'data-admin-panel="' . htmlspecialchars($adminNavbarAction['target']) . '"' : '' ?> class="inline-flex items-center gap-1.5 whitespace-nowrap text-sm font-semibold text-red-700 transition hover:text-red-900">
                    <i class="fa-solid <?= htmlspecialchars($adminNavbarAction['icon'] ?? 'fa-plus') ?>"></i>
                    <span><?= htmlspecialchars($adminNavbarAction['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <?php if (!empty($adminNavbarSearch)): ?>
                <form method="GET" id="admin-user-search" data-jquery-search-form data-search-results="#user-list" class="order-3 flex w-full items-center gap-2 lg:order-none lg:w-auto">
                    <label class="relative min-w-0 flex-1 lg:flex-none">
                        <span class="sr-only">ค้นหาผู้ใช้งาน</span>
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Username, Email หรือชื่อ" class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 pl-9 pr-3 text-sm outline-none transition focus:border-red-400 focus:bg-white lg:w-56">
                    </label>
                    <label>
                        <span class="sr-only">เลือก Role</span>
                        <select name="role" class="h-10 rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm outline-none transition focus:border-red-400 focus:bg-white">
                            <option value="">ทุก Role</option>
                            <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="teacher" <?= ($role ?? '') === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="staff" <?= ($role ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="student" <?= ($role ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        </select>
                    </label>
                    <button type="submit" class="text-sm font-semibold text-red-700 transition hover:text-red-900">ค้นหา</button>
                    <?php if (($search ?? '') !== '' || ($role ?? '') !== ''): ?>
                        <a href="index.php" class="text-sm text-gray-500 transition hover:text-gray-800">รีเซ็ต</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>


            <!-- USER -->

            <div class="admin-navbar-user hidden items-center gap-3 rounded-2xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 shadow-sm sm:flex">

                <div

                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-red-700 text-white shadow-md shadow-red-200"

                >

                    <i class="fa-solid fa-user-shield"></i>

                </div>


                <div class="min-w-0 leading-tight">

                    <p

                        class="max-w-40 truncate text-sm font-bold text-slate-800"

                    >

                        <?= htmlspecialchars($adminName) ?>

                    </p>


                    <p

                        class="mt-0.5 flex items-center gap-1.5 text-xs font-semibold text-slate-500"

                    >

                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>ผู้ดูแลระบบ

                    </p>

                </div>

            </div>


        </div>

    </header>


    <!-- CONTENT -->

    <main class="p-4 md:p-6">
<link rel="stylesheet" href="/Project/assets/css/status-badges.css?v=20260815-2">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/Project/assets/js/jquery-live-search.js"></script>
