<?php

require_once 'auth.php';
require_once '../helpers/document_workflow_status_helper.php';

$documentId = (int) ($_GET['document'] ?? 0);
$workflow = getDocumentWorkflowStatus($pdo, $documentId);
if (!$workflow || (int) $workflow['student_user_id'] !== (int) $_SESSION['user_id']) {
    $_SESSION['student_history_status_notice'] = true;
    header('Location: history.php');
    exit;
}

$processMeta = workflowProcessStatusMeta((string) $workflow['process_status']);
$pageHeading = 'ติดตามสถานะคำร้อง';
function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function workflowDateTime(?string $value): string {
    if (!$value) return '-';
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) . ' น.' : $value;
}
$actionLabels = [
    'activated' => 'เริ่มขั้นตอนพิจารณา',
    'approved' => 'อนุมัติขั้นตอน',
    'revision_requested' => 'ส่งกลับให้แก้ไข',
    'resubmitted' => 'แก้ไขและส่งใหม่',
    'skipped' => 'ข้ามขั้นตอน',
];
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ติดตามสถานะคำร้อง</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
<?php include 'sidebar.php'; ?>
<div class="min-h-screen lg:ml-72">
    <?php include 'navbar.php'; ?>
    <main class="p-4 sm:p-6 lg:p-8">
        <a href="tracking.php" class="inline-flex items-center gap-2 text-sm font-bold text-sky-700"><i class="fa-solid fa-arrow-left"></i>กลับหน้าติดตามสถานะ</a>

        <section class="mt-4 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <span class="inline-flex rounded-full bg-red-500/20 px-3 py-1 text-xs font-black text-red-200"><?= h($workflow['document_code']) ?></span>
                    <h1 class="mt-3 text-2xl font-black sm:text-3xl"><?= h($workflow['document_name']) ?></h1>
                    <p class="mt-2 text-sm text-slate-300">ส่งเมื่อ <?= h(workflowDateTime($workflow['submitted_at'])) ?></p>
                </div>
                <span class="inline-flex items-center gap-2 self-start rounded-full px-4 py-2 text-sm font-black <?= h($processMeta[1]) ?>">
                    <i class="fa-solid <?= h($processMeta[2]) ?>"></i><?= h($processMeta[0]) ?>
                </span>
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-6">
                <h2 class="text-xl font-black">ลำดับการพิจารณา</h2>
                <p class="mt-1 text-sm text-gray-500">แสดงผลการพิจารณาจากอาจารย์ที่ปรึกษาและรายละเอียดการดำเนินงาน</p>
            </div>
            <div class="space-y-0">
                <?php foreach ($workflow['steps'] as $index => $step): $stepMeta = workflowStepStatusMeta((string) $step['step_status']); $isLast = $index === count($workflow['steps']) - 1; ?>
                    <article class="relative grid grid-cols-[44px_minmax(0,1fr)] gap-4 pb-7">
                        <?php if (!$isLast): ?><span class="absolute bottom-0 left-[21px] top-11 w-0.5 bg-gray-200"></span><?php endif; ?>
                        <span class="relative z-10 inline-flex h-11 w-11 items-center justify-center rounded-full <?= h($stepMeta[1]) ?> ring-4 ring-white">
                            <i class="fa-solid <?= h($stepMeta[2]) ?>"></i>
                        </span>
                        <div class="rounded-xl border <?= $step['step_status'] === 'current' ? 'border-blue-200 bg-blue-50/40' : 'border-gray-200' ?> p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wider text-gray-400">ขั้นที่ <?= (int) $step['step_order'] ?></p>
                                    <h3 class="mt-1 font-black text-gray-900"><?= h($step['step_name']) ?></h3>
                                    <p class="mt-1 text-sm text-gray-600"><?= h($step['reviewer_name'] ?: 'ยังไม่พบข้อมูลอาจารย์') ?></p>
                                </div>
                                <span class="inline-flex self-start rounded-full px-3 py-1 text-xs font-bold <?= h($stepMeta[1]) ?>"><?= h($stepMeta[0]) ?></span>
                            </div>
                            <?php if (!empty($step['latest_comment']) && $step['step_status'] === 'revision_requested'): ?>
                                <div class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700"><strong>รายละเอียดที่ต้องแก้ไข:</strong> <?= h($step['latest_comment']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($step['reviewed_at'])): ?><p class="mt-3 text-xs text-gray-400">ดำเนินการเมื่อ <?= h(workflowDateTime($step['reviewed_at'])) ?></p><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-5"><h2 class="text-lg font-black">ประวัติการดำเนินงาน</h2></div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($workflow['actions'] as $action): ?>
                    <article class="flex gap-4 px-6 py-4">
                        <span class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <div><strong><?= h($actionLabels[$action['action_type']] ?? $action['action_type']) ?></strong><p class="mt-1 text-sm text-gray-600"><?= h($action['step_name']) ?> · <?= h($action['actor_name']) ?></p><?php if ($action['action_comment']): ?><p class="mt-1 text-sm text-red-700"><?= h($action['action_comment']) ?></p><?php endif; ?><p class="mt-1 text-xs text-gray-400"><?= h(workflowDateTime($action['created_at'])) ?></p></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
