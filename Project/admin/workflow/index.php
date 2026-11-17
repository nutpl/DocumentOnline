<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../services/WorkflowService.php';

$service = new WorkflowService($pdo);
$documentTypes = array_values(array_filter(
    $service->getDocumentTypes(),
    static fn(array $type): bool => preg_match('/^R\.?(01|12|14|18|24)$/i', (string) $type['document_code']) === 1
        || strtoupper((string) $type['document_code']) === 'INTERNSHIP_INFO'
));
$positions = $service->getPositions();
$employees = $service->getEmployees();
$workflowReadiness = $service->getWorkflowReadiness();
$selectedDocumentTypeId = (int) ($_GET['document_type_id'] ?? $_POST['document_type_id'] ?? ($documentTypes[0]['document_type_id'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf_or_abort();
    try {
        $service->saveWorkflow(
            $selectedDocumentTypeId,
            is_array($_POST['steps'] ?? null) ? $_POST['steps'] : [],
            (int) $_SESSION['user_id']
        );
        $_SESSION['workflow_success'] = 'บันทึกลำดับผู้พิจารณาเรียบร้อยแล้ว';
    } catch (Throwable $exception) {
        $_SESSION['workflow_error'] = $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'ไม่สามารถบันทึกลำดับผู้พิจารณาได้ กรุณาลองใหม่อีกครั้ง';
    }

    header('Location: index.php?document_type_id=' . $selectedDocumentTypeId . '&edit=1');
    exit;
}

$activeWorkflow = $selectedDocumentTypeId > 0
    ? $service->getActiveWorkflow($selectedDocumentTypeId)
    : null;
$suggestedSteps = $selectedDocumentTypeId > 0
    ? $service->getSuggestedWorkflowSteps($selectedDocumentTypeId)
    : [];
$initialSteps = $activeWorkflow['steps'] ?? $suggestedSteps;
$usesPredefinedReviewers = !empty($suggestedSteps);
$pageTitle = 'กำหนดลำดับผู้พิจารณา';

include __DIR__ . '/../layouts/admin_header.php';
include __DIR__ . '/../layouts/admin_sidebar.php';
include __DIR__ . '/../layouts/admin_navbar.php';
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 px-6 py-7 text-white shadow-xl sm:px-8">
        <div class="absolute -right-14 -top-16 h-48 w-48 rounded-full border-[30px] border-white/5"></div>
        <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-600/20 text-red-300 ring-1 ring-red-400/20">
                    <i class="fa-solid fa-diagram-project text-xl"></i>
                </span>
                <div>
                    <p class="mb-1 text-xs font-bold uppercase tracking-[0.2em] text-red-300">Sequential review</p>
                    <h1 class="text-2xl font-black sm:text-3xl">กำหนดลำดับผู้พิจารณา</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">ระบบกำหนดอาจารย์ที่ปรึกษาของนักศึกษาเป็นผู้พิจารณาอนุมัติเพียงคนเดียว</p>
                </div>
            </div>
            <a href="../office_holders/index.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/20">
                <i class="fa-solid fa-user-tie"></i>จัดการผู้ดำรงตำแหน่ง
            </a>
            <a href="status.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/20">
                <i class="fa-solid fa-clock-rotate-left"></i>ประวัติการดำเนินงาน
            </a>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-black text-slate-900">ความพร้อมของคำร้อง</h2><p class="mt-1 text-sm text-slate-500">ครอบคลุมคำร้อง R และใบคำร้องขอฝึกประสบการณ์วิชาชีพ</p></div>
            <?php $configuredCount = count(array_filter($workflowReadiness, static fn(array $item): bool => $item['is_configured'])); ?>
            <span class="inline-flex self-start rounded-full px-3 py-1.5 text-xs font-black <?= $configuredCount === count($workflowReadiness) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>"><?= $configuredCount ?>/<?= count($workflowReadiness) ?> แบบฟอร์มพร้อมใช้งาน</span>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($workflowReadiness as $item): ?>
                <a href="?document_type_id=<?= (int) $item['document_type_id'] ?>&amp;edit=1" class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md <?= (int) $item['document_type_id'] === $selectedDocumentTypeId ? 'border-red-300 bg-red-50/40 ring-2 ring-red-100' : 'border-slate-200 hover:border-red-200' ?>">
                    <div class="flex items-start justify-between gap-2"><strong class="text-red-700"><?= htmlspecialchars($item['document_code'], ENT_QUOTES, 'UTF-8') ?></strong><span class="inline-flex h-7 w-7 items-center justify-center rounded-full <?= $item['is_configured'] ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600' ?>"><i class="fa-solid <?= $item['is_configured'] ? 'fa-check' : 'fa-clock' ?> text-xs"></i></span></div>
                    <p class="mt-2 line-clamp-2 min-h-10 text-xs leading-5 text-slate-600"><?= htmlspecialchars($item['document_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-xs font-bold <?= $item['is_configured'] ? 'text-emerald-700' : 'text-amber-700' ?>"><?= $item['is_configured'] ? 'กำหนดลำดับแล้ว' : 'ยังไม่ได้กำหนดลำดับ' ?></p>
                    <p class="mt-1 text-xs text-slate-400"><?= (int) $item['step_count'] ?> ขั้นตอน</p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <dialog id="workflow-dialog" class="workflow-dialog w-[calc(100%_-_2rem)] max-w-6xl overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="max-h-[calc(100dvh-5rem)] overflow-y-auto">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
                <div><p class="text-xs font-bold text-red-700">กำหนดลำดับผู้พิจารณา</p><h2 class="mt-0.5 text-lg font-black text-slate-900">ลำดับการพิจารณาปัจจุบัน</h2></div>
                <button type="button" id="close-workflow-dialog" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-red-700" aria-label="ปิดหน้าต่าง"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="post" id="workflow-form" class="space-y-4 p-4 sm:p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="document_type_id" value="<?= $selectedDocumentTypeId ?>">

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-black text-slate-900">ลำดับการพิจารณาปัจจุบัน</h2>
                        <?php if (!$activeWorkflow): ?>
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">ยังไม่ได้กำหนด</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-sm text-slate-500"><?= $usesPredefinedReviewers ? 'ระบบดึงอาจารย์ที่ปรึกษาจากข้อมูลนักศึกษาโดยอัตโนมัติ' : 'กำหนดผู้พิจารณาเอกสาร' ?></p>
                </div>
                <button type="button" id="add-step" class="<?= $usesPredefinedReviewers ? 'hidden ' : '' ?>inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 text-sm font-bold text-red-700 transition hover:bg-red-100">
                    <i class="fa-solid fa-plus"></i>เพิ่มขั้นตอน
                </button>
            </div>

            <div id="steps-container" class="space-y-3 p-5 sm:p-6"></div>

            <div class="border-t border-slate-100 bg-slate-50 px-6 py-4">
                <div class="flex items-start gap-3 text-sm text-slate-600">
                    <i class="fa-solid fa-circle-info mt-0.5 text-blue-500"></i>
                    <p><?= $usesPredefinedReviewers ? 'ชื่ออาจารย์ที่ปรึกษาจะดึงจากข้อมูลนักศึกษาหรือกลุ่มเรียน ผู้เกี่ยวข้องรายอื่นไม่ต้องอนุมัติผ่านระบบ' : 'กำหนดผู้พิจารณาตามสิทธิ์ที่ได้รับ' ?></p>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-red-700 px-7 text-sm font-bold text-white shadow-lg shadow-red-700/20 transition hover:-translate-y-0.5 hover:bg-red-600">
                <i class="fa-solid fa-floppy-disk"></i>บันทึกลำดับผู้พิจารณา
            </button>
        </div>
            </form>
        </div>
    </dialog>
</div>

<style>
    /* Keep the native modal centered even when its content has to scroll. */
    #workflow-dialog.workflow-dialog {
        position: fixed;
        inset: 0;
        height: fit-content;
        max-height: calc(100dvh - 5rem);
        margin: auto;
    }

    @media (max-width: 639px) {
        #workflow-dialog.workflow-dialog {
            width: calc(100% - 1.5rem);
            max-height: calc(100dvh - 2rem);
        }

        #workflow-dialog.workflow-dialog > div {
            max-height: calc(100dvh - 2rem);
        }
    }
</style>

<template id="step-template">
    <article class="workflow-step rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm" draggable="true">
        <div class="grid gap-4 lg:grid-cols-[56px_minmax(180px,1fr)_minmax(220px,1fr)_minmax(220px,1fr)_132px] lg:items-end">
            <div class="flex items-center gap-2 lg:flex-col lg:justify-center">
                <button type="button" class="drag-handle inline-flex h-9 w-9 cursor-grab items-center justify-center rounded-lg bg-slate-100 text-slate-400" title="ลากเพื่อจัดลำดับ"><i class="fa-solid fa-grip-vertical"></i></button>
                <span class="step-number text-sm font-black text-red-700"></span>
            </div>
            <label class="block">
                <span class="mb-2 block text-xs font-bold text-slate-600">ชื่อขั้นตอน</span>
                <input type="text" data-field="step_name" maxlength="150" required placeholder="เช่น อาจารย์ที่ปรึกษา" class="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100">
            </label>
            <label class="block">
                <span class="mb-2 block text-xs font-bold text-slate-600">กำหนดผู้พิจารณาจาก</span>
                <select data-field="reviewer_source" class="source-select h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100">
                    <option value="student_advisor">อาจารย์ที่ปรึกษาของนักศึกษา</option>
                    <option value="document_teacher">อาจารย์ที่ระบุในคำร้อง</option>
                    <option value="office_position">ผู้ดำรงตำแหน่ง</option>
                    <option value="employee">บุคลากรเฉพาะคน</option>
                </select>
            </label>
            <label class="target-field block">
                <span class="target-label mb-2 block text-xs font-bold text-slate-600">รายละเอียด</span>
                <select data-field="target_id" class="target-select h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100"></select>
                <div class="automatic-target hidden h-11 items-center rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">ระบบเลือกให้อัตโนมัติ</div>
            </label>
            <div class="flex items-center justify-end gap-2">
                <button type="button" class="move-up inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100" title="เลื่อนขึ้น"><i class="fa-solid fa-arrow-up"></i></button>
                <button type="button" class="move-down inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100" title="เลื่อนลง"><i class="fa-solid fa-arrow-down"></i></button>
                <button type="button" class="remove-step inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100" title="ลบ"><i class="fa-solid fa-trash-can"></i></button>
            </div>
        </div>
    </article>
</template>

<script>
(() => {
    const initialSteps = <?= json_encode($initialSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const usesPredefinedReviewers = <?= $usesPredefinedReviewers ? 'true' : 'false' ?>;
    const positions = <?= json_encode($positions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const employees = <?= json_encode($employees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const container = document.getElementById('steps-container');
    const template = document.getElementById('step-template');
    const workflowDialog = document.getElementById('workflow-dialog');
    const closeWorkflowDialog = document.getElementById('close-workflow-dialog');
    const shouldOpenWorkflowDialog = <?= ($_GET['edit'] ?? '') === '1' ? 'true' : 'false' ?>;
    let draggedStep = null;

    const employeeName = employee => [employee.display_prefix, employee.firstname, employee.lastname].filter(Boolean).join(' ');
    const optionHtml = (value, label, selected) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${label}</option>`;

    function refreshTarget(step, selectedValue = '') {
        const source = step.querySelector('.source-select').value;
        const select = step.querySelector('.target-select');
        const automatic = step.querySelector('.automatic-target');
        const label = step.querySelector('.target-label');
        const automaticSource = source === 'student_advisor' || source === 'document_teacher';
        select.classList.toggle('hidden', automaticSource);
        automatic.classList.toggle('hidden', !automaticSource);
        automatic.classList.toggle('flex', automaticSource);
        select.required = !automaticSource;

        if (automaticSource) {
            select.innerHTML = '<option value="">อัตโนมัติ</option>';
            label.textContent = 'รายละเอียด';
        } else if (source === 'office_position') {
            label.textContent = 'ตำแหน่ง';
            select.innerHTML = '<option value="">-- เลือกตำแหน่ง --</option>' + positions.map(position => optionHtml(position.position_id, `${position.position_name}${position.holder_names ? ' — ' + position.holder_names : ''}`, selectedValue)).join('');
        } else {
            label.textContent = 'บุคลากร';
            select.innerHTML = '<option value="">-- เลือกบุคลากร --</option>' + employees.map(employee => optionHtml(employee.employee_id, `${employeeName(employee)} — ${employee.department_name || '-'}`, selectedValue)).join('');
        }
    }

    function renumber() {
        [...container.querySelectorAll('.workflow-step')].forEach((step, index, allSteps) => {
            step.querySelector('.step-number').textContent = `ขั้นที่ ${index + 1}`;
            step.querySelectorAll('[data-field]').forEach(field => {
                field.name = `steps[${index}][${field.dataset.field}]`;
            });
            step.querySelector('.move-up').disabled = index === 0;
            step.querySelector('.move-down').disabled = index === allSteps.length - 1;
            step.querySelector('.move-up').classList.toggle('opacity-30', index === 0);
            step.querySelector('.move-down').classList.toggle('opacity-30', index === allSteps.length - 1);
        });
    }

    function addStep(data = {}) {
        const step = template.content.firstElementChild.cloneNode(true);
        step.querySelector('[data-field="step_name"]').value = data.step_name || '';
        step.querySelector('.source-select').value = data.reviewer_source || 'student_advisor';
        const selectedTarget = data.employee_id || data.position_id || '';
        refreshTarget(step, selectedTarget);

        if (usesPredefinedReviewers) {
            const nameInput = step.querySelector('[data-field="step_name"]');
            const sourceSelect = step.querySelector('.source-select');
            const targetSelect = step.querySelector('.target-select');
            nameInput.readOnly = true;
            nameInput.classList.add('bg-slate-50', 'text-slate-600');
            targetSelect.tabIndex = -1;
            targetSelect.classList.add('pointer-events-none', 'bg-slate-50', 'text-slate-600');

            const sourceLabel = sourceSelect.closest('label');
            sourceLabel.querySelector('span').textContent = 'ผู้พิจารณา';
            sourceSelect.tabIndex = -1;
            sourceSelect.classList.add('hidden');
            sourceSelect.style.display = 'none';
            const sourceDisplay = document.createElement('div');
            sourceDisplay.className = 'flex h-11 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600';
            sourceDisplay.textContent = data.reviewer_source === 'student_advisor'
                ? 'อาจารย์ที่ปรึกษาของนักศึกษา'
                : data.reviewer_source === 'document_teacher'
                    ? 'อาจารย์ที่ระบุในคำร้อง'
                    : data.reviewer_source === 'office_position'
                        ? 'ผู้ดำรงตำแหน่ง'
                        : 'บุคลากรเฉพาะคน';
            sourceSelect.after(sourceDisplay);

            [sourceSelect, targetSelect].forEach(select => {
                select.tabIndex = -1;
                select.classList.add('pointer-events-none', 'bg-slate-50', 'text-slate-600');
            });
            step.querySelector('.remove-step').classList.add('hidden');
            if (data.reviewer_display && (data.reviewer_source === 'student_advisor' || data.reviewer_source === 'document_teacher')) {
                step.querySelector('.automatic-target').textContent = data.reviewer_display;
            }
            if (data.reviewer_source === 'office_position') {
                const targetLabel = step.querySelector('.target-label');
                const automaticTarget = step.querySelector('.automatic-target');
                targetLabel.textContent = 'ตำแหน่ง';
                targetSelect.classList.add('hidden');
                targetSelect.style.display = 'none';
                automaticTarget.classList.remove('hidden');
                automaticTarget.classList.add('flex');
                automaticTarget.style.display = 'flex';
                automaticTarget.textContent = data.position_name || data.reviewer_display || 'กำหนดจากตำแหน่งในระบบ';
            }
        }

        step.querySelector('.source-select').addEventListener('change', () => refreshTarget(step));
        step.querySelector('.remove-step').addEventListener('click', () => {
            step.remove();
            if (!container.children.length) addStep();
            renumber();
        });
        step.querySelector('.move-up').addEventListener('click', () => {
            if (step.previousElementSibling) container.insertBefore(step, step.previousElementSibling);
            renumber();
        });
        step.querySelector('.move-down').addEventListener('click', () => {
            if (step.nextElementSibling) container.insertBefore(step.nextElementSibling, step);
            renumber();
        });
        step.addEventListener('dragstart', () => { draggedStep = step; step.classList.add('opacity-50'); });
        step.addEventListener('dragend', () => { draggedStep = null; step.classList.remove('opacity-50'); renumber(); });
        step.addEventListener('dragover', event => {
            event.preventDefault();
            if (!draggedStep || draggedStep === step) return;
            const box = step.getBoundingClientRect();
            container.insertBefore(draggedStep, event.clientY < box.top + box.height / 2 ? step : step.nextSibling);
        });

        container.appendChild(step);
        renumber();
    }

    (initialSteps.length ? initialSteps : [{}]).forEach(addStep);
    document.getElementById('add-step').addEventListener('click', () => addStep());

    const closeDialog = () => {
        workflowDialog?.close();
    };
    workflowDialog?.addEventListener('close', () => {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url);
    });
    closeWorkflowDialog?.addEventListener('click', closeDialog);
    workflowDialog?.addEventListener('click', event => {
        if (event.target === workflowDialog) closeDialog();
    });
    if (shouldOpenWorkflowDialog && workflowDialog && !workflowDialog.open) {
        workflowDialog.showModal();
    }
})();
</script>

<?php if (!empty($_SESSION['workflow_success'])): ?>
    <div hidden data-success-alert data-success-title="บันทึกสำเร็จ" data-success-text="<?= htmlspecialchars($_SESSION['workflow_success'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php unset($_SESSION['workflow_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['workflow_error'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon: 'error', title: 'บันทึกไม่สำเร็จ', text: <?= json_encode($_SESSION['workflow_error'], JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#b91c1c'}));</script>
    <?php unset($_SESSION['workflow_error']); ?>
<?php endif; ?>

<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
