<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$stmt = $pdo->query("
    SELECT
        document_type_id,
        document_code,
        document_name,
        description
    FROM document_type
    WHERE status = 'active'
      AND document_code LIKE 'R%'
      AND document_name NOT IN (
          'หนังสือส่งตัวนักศึกษาฝึกงาน',
          'คำร้องขอความอนุเคราะห์สถานที่ฝึกงาน'
      )
    ORDER BY document_code
");

$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสารคำร้อง</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body class="bg-slate-50 text-slate-900">
    <?php include "sidebar.php"; ?>
        <div class="min-h-screen min-w-0 lg:ml-72">
            <?php include "navbar.php"; ?>

            <main class="p-5 sm:p-8 lg:p-10">
                <section class="mx-auto max-w-6xl">
                    <div class="border-l-[7px] border-red-700 pl-4">
                        <h4 class="text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">
                            เอกสารแบบคำร้อง (R)
                        </h4>
                        <p class="mt-1 text-sm text-slate-500 sm:text-base">
                            คลิกที่ปุ่มไอคอนเพื่อกรอกแบบฟอร์ม หรือดูขั้นตอนและรายละเอียดประกอบการยื่นคำร้อง
                        </p>
                    </div>

                    <div class="mt-7 overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-sm sm:mt-10">
                        <div class="border-b border-slate-100 px-4 py-3 lg:hidden"><p class="text-sm font-extrabold text-slate-800">รายการคำร้อง</p></div>
                        <div class="hidden overflow-x-auto lg:block">
                            <table class="w-full min-w-[650px] border-collapse">
                                <thead class="bg-slate-950 text-white">
                                    <tr>
                                        <th scope="col" class="px-8 py-3 text-left text-base font-bold">รายการคำร้อง</th>
                                        <th scope="col" class="w-36 px-4 py-3 text-center text-base font-bold">เอกสาร</th>
                                        <th scope="col" class="w-36 px-4 py-3 text-center text-base font-bold">ขั้นตอน</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (!$documents): ?>
                                        <tr>
                                            <td colspan="3" class="px-8 py-12 text-center text-slate-500">ยังไม่มีเอกสารให้ใช้งาน</td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($documents as $doc): ?>
                                        <?php
                                        $documentCode = htmlspecialchars($doc['document_code']);
                                        $documentName = htmlspecialchars($doc['document_name']);
                                        $description = trim((string) ($doc['description'] ?? ''));
                                        ?>
                                        <tr class="transition-colors hover:bg-slate-50">
                                            <td class="px-8 py-7">
                                                <div class="flex items-center gap-4">
                                                    <span class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-sm font-extrabold text-slate-700">
                                                        <?= $documentCode ?>
                                                    </span>
                                                    <span class="text-base font-bold text-slate-900"><?= $documentName ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-7 text-center">
                                                <a
                                                    href="document_create.php?type=<?= (int) $doc['document_type_id'] ?>"
                                                    class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-red-200 bg-red-50 text-red-500 shadow-sm transition hover:-translate-y-0.5 hover:bg-red-100 hover:shadow"
                                                    aria-label="กรอกเอกสาร <?= $documentCode ?>"
                                                    title="กรอกเอกสาร"
                                                >
                                                    <i class="fa-regular fa-file-pdf text-xl"></i>
                                                </a>
                                            </td>
                                            <td class="px-4 py-7 text-center">
                                                <button
                                                    type="button"
                                                    class="guide-button inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-sky-200 bg-sky-50 text-sky-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-100 hover:shadow"
                                                    data-document-name="<?= $documentName ?>"
                                                    data-description="<?= htmlspecialchars($description) ?>"
                                                    aria-label="ดูขั้นตอนของ <?= $documentCode ?>"
                                                    title="ดูขั้นตอน"
                                                >
                                                    <i class="fa-regular fa-image text-xl"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y divide-slate-100 lg:hidden">
                            <?php if (!$documents): ?>
                                <p class="px-5 py-10 text-center text-sm text-slate-500">ยังไม่มีเอกสารให้ใช้งาน</p>
                            <?php endif; ?>
                            <?php foreach ($documents as $doc): ?>
                                <?php $documentCode = htmlspecialchars($doc['document_code']); $documentName = htmlspecialchars($doc['document_name']); $description = trim((string)($doc['description'] ?? '')); ?>
                                <article class="flex items-center gap-3 p-4">
                                    <span class="shrink-0 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm font-extrabold text-slate-700"><?= $documentCode ?></span>
                                    <h2 class="min-w-0 flex-1 text-sm font-bold leading-6 text-slate-900"><?= $documentName ?></h2>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <button type="button" class="guide-button inline-flex h-10 w-10 items-center justify-center rounded-xl border border-sky-200 bg-sky-50 text-sky-700" data-document-name="<?= $documentName ?>" data-description="<?= htmlspecialchars($description) ?>" aria-label="ดูขั้นตอนของ <?= $documentCode ?>" title="ดูขั้นตอน"><i class="fa-regular fa-image"></i></button>
                                        <a href="document_create.php?type=<?= (int)$doc['document_type_id'] ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600" aria-label="กรอกเอกสาร <?= $documentCode ?>" title="กรอกเอกสาร"><i class="fa-regular fa-file-pdf"></i></a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </main>
    </div>

    <dialog id="guide-dialog" class="w-[calc(100%-2rem)] max-w-lg rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-sky-600">ขั้นตอนการใช้งาน</p>
                    <h2 id="guide-title" class="mt-1 text-xl font-extrabold text-slate-950"></h2>
                </div>
                <button type="button" class="close-guide text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="ปิด">&times;</button>
            </div>
            <p id="guide-description" class="mt-5 text-base text-slate-600"></p>
            <ol class="mt-5 list-decimal space-y-2 pl-5 text-base text-slate-700">
                <li>คลิกไอคอนเอกสารเพื่อเปิดแบบฟอร์มคำร้อง</li>
                <li>กรอกข้อมูลให้ครบถ้วน แล้วตรวจสอบความถูกต้อง</li>
                <li>บันทึกและส่งเอกสารตามขั้นตอนของระบบ</li>
            </ol>
        </div>
    </dialog>

    <script>
        const guideDialog = document.getElementById('guide-dialog');
        const guideTitle = document.getElementById('guide-title');
        const guideDescription = document.getElementById('guide-description');

        document.querySelectorAll('.guide-button').forEach((button) => {
            button.addEventListener('click', () => {
                guideTitle.textContent = button.dataset.documentName;
                guideDescription.textContent = button.dataset.description || 'โปรดเตรียมข้อมูลที่เกี่ยวข้องก่อนเริ่มกรอกแบบฟอร์ม';
                guideDialog.showModal();
            });
        });

        document.querySelector('.close-guide').addEventListener('click', () => guideDialog.close());
    </script>
</body>
</html>
