<?php
require_once __DIR__ . '/config/database.php';

$documentTypes = $pdo->query("SELECT document_code, document_name FROM document_type WHERE status = 'active' ORDER BY document_code")
    ->fetchAll(PDO::FETCH_ASSOC);
$requestDocuments = array_values(array_filter($documentTypes, static fn(array $document): bool => str_starts_with((string) $document['document_code'], 'R.')));
$internshipDocuments = array_values(array_filter($documentTypes, static fn(array $document): bool => str_starts_with((string) $document['document_code'], 'INTERNSHIP_')));
/* หนังสือทางการสองรายการออกโดยเจ้าหน้าที่ จึงไม่แสดงเป็นบริการที่ผู้ใช้เลือกได้จากหน้าแรก */
$serviceDocuments = array_values(array_filter(
    $documentTypes,
    static fn(array $document): bool => !in_array((string) $document['document_code'], ['INTERNSHIP_REQUEST', 'INTERNSHIP_SEND'], true)
));
?>
<!doctype html>
<html lang="th" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="ระบบจัดการเอกสารคำร้อง คณะวิศวกรรมศาสตร์และเทคโนโลยี มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน">
    <title>ระบบจัดการเอกสารคำร้อง | คณะวิศวกรรมศาสตร์และเทคโนโลยี</title>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260902-6">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config={theme:{extend:{fontFamily:{sans:['TH Sarabun PSK','Tahoma','sans-serif']},colors:{fet:{50:'#fff5f5',100:'#ffe3e3',600:'#d7202f',700:'#b71926',800:'#911821',950:'#33080c'}},boxShadow:{soft:'0 24px 70px rgba(15,23,42,.10)'}}}};
    </script>
    <style>
        body{background:#f8fafc}.hero-grid{background-image:linear-gradient(rgba(255,255,255,.055) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.055) 1px,transparent 1px);background-size:42px 42px}.paper-line{height:.42rem;border-radius:999px;background:#e2e8f0}.paper-line.red{background:#fecaca}.floating-card{animation:float 5s ease-in-out infinite}@keyframes float{50%{transform:translateY(-8px)}}@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}.floating-card{animation:none}}
    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
<header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-4 py-3 sm:px-6 lg:px-8">
        <a href="index.php" class="flex min-w-0 items-center gap-3" aria-label="หน้าหลัก">
            <div class="flex shrink-0 items-center gap-2">
                <img src="assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="h-16 w-14 object-contain sm:h-20 sm:w-16">
                <img src="assets/images/logo-transparent.png" alt="โลโก้คณะวิศวกรรมศาสตร์และเทคโนโลยี" class="h-9 w-14 object-contain sm:h-11 sm:w-20">
            </div>
            <div class="min-w-0 border-l border-slate-200 pl-3"><p class="truncate text-sm font-extrabold text-slate-950 sm:text-base">ระบบจัดการเอกสารคำร้อง</p><p class="hidden truncate text-xs font-medium text-slate-500 sm:block">คณะวิศวกรรมศาสตร์และเทคโนโลยี</p></div>
        </a>
        <nav class="hidden items-center gap-1 text-sm font-semibold text-slate-600 md:flex" aria-label="เมนูหลัก">
            <a href="#services" class="rounded-xl px-4 py-2.5 transition hover:bg-red-50 hover:text-fet-700">บริการของระบบ</a>
            <a href="#how-it-works" class="rounded-xl px-4 py-2.5 transition hover:bg-red-50 hover:text-fet-700">ขั้นตอนใช้งาน</a>
            <a href="auth/login.php" class="ml-2 inline-flex items-center gap-2 rounded-xl bg-slate-950 px-5 py-3 font-bold text-white shadow-lg shadow-slate-950/15 transition hover:bg-fet-700"><i class="fa-solid fa-right-to-bracket"></i>เข้าสู่ระบบ</a>
        </nav>
        <button id="mobile-menu-button" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-700 md:hidden" aria-label="เปิดเมนู" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    </div>
    <nav id="mobile-menu" class="hidden border-t border-slate-100 bg-white px-4 py-4 md:hidden" aria-label="เมนูมือถือ">
        <div class="mx-auto grid max-w-7xl gap-1"><a href="#services" class="rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-red-50">บริการของระบบ</a><a href="#how-it-works" class="rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-red-50">ขั้นตอนใช้งาน</a><a href="auth/login.php" class="mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 font-bold text-white"><i class="fa-solid fa-right-to-bracket"></i>เข้าสู่ระบบ</a></div>
    </nav>
</header>

<main class="pt-[96px] sm:pt-[112px]">
    <section class="relative isolate overflow-hidden bg-slate-950 text-white">
        <div class="hero-grid absolute inset-0 opacity-70"></div><div class="absolute -right-32 -top-36 h-[34rem] w-[34rem] rounded-full bg-fet-700/35 blur-3xl"></div><div class="absolute -bottom-40 left-1/3 h-80 w-80 rounded-full bg-red-500/15 blur-3xl"></div>
        <div class="relative mx-auto grid min-h-[560px] max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 sm:py-16 lg:grid-cols-[minmax(0,1.08fr)_minmax(420px,.92fr)] lg:px-8 lg:py-20">
            <div class="max-w-3xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-red-300/20 bg-red-500/10 px-4 py-2 text-xs font-bold text-red-100 sm:text-sm"><span class="h-2 w-2 rounded-full bg-red-400"></span>บริการเอกสารภายในมหาวิทยาลัย</span>
                <h5 class="mt-5 text-3xl font-extrabold leading-[1.25] tracking-tight sm:text-4xl lg:text-5xl">จัดทำและติดตาม<br><span class="text-red-400">เอกสารคำร้อง</span>ได้ในที่เดียว</h5>
                <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base sm:leading-8">สำหรับนักศึกษา อาจารย์ และเจ้าหน้าที่คณะวิศวกรรมศาสตร์และเทคโนโลยี ช่วยลดขั้นตอนการกรอกเอกสาร ตรวจสอบสถานะ และรับแจ้งเตือนเมื่อต้องดำเนินการ</p>
                <div class="mt-7 flex flex-col gap-3 sm:flex-row"><a href="auth/login.php" class="inline-flex h-12 items-center justify-center gap-3 rounded-xl bg-fet-600 px-6 text-sm font-bold text-white shadow-xl shadow-red-950/30 transition hover:-translate-y-0.5 hover:bg-fet-700"><i class="fa-solid fa-arrow-right-to-bracket"></i>เข้าสู่ระบบเพื่อเริ่มใช้งาน</a><a href="auth/register.php" class="inline-flex h-12 items-center justify-center gap-3 rounded-xl border border-white/20 bg-white/5 px-6 text-sm font-bold text-white transition hover:bg-white/10"><i class="fa-solid fa-user-plus"></i>สมัครสมาชิกนักศึกษา</a></div>
                <div class="mt-7 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-300 sm:text-sm"><span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-400"></i>บันทึกฉบับร่างได้</span><span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-400"></i>ติดตามสถานะได้</span><span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-400"></i>แจ้งเตือนภายในระบบ</span></div>
            </div>
            <div class="relative mx-auto w-full max-w-lg lg:mx-0 lg:justify-self-end" aria-hidden="true">
                <div class="relative z-20 mb-3 ml-7 inline-flex rounded-2xl border border-white/20 bg-white/95 p-4 text-slate-900 shadow-2xl sm:ml-0"><div class="flex items-center gap-3"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-fet-700"><i class="fa-solid fa-users"></i></span><div><p class="text-xs text-slate-500">ผู้ใช้งานในระบบ</p><p class="text-sm font-extrabold">นักศึกษา · อาจารย์ · เจ้าหน้าที่</p></div></div></div>
                <div class="floating-card relative ml-7 overflow-hidden rounded-[2rem] border border-white/15 bg-white p-5 text-slate-900 shadow-2xl shadow-black/40 sm:ml-12 sm:p-7">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-5"><div class="flex items-center gap-3"><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-xl text-fet-700"><i class="fa-solid fa-file-lines"></i></span><div><p class="font-extrabold">เอกสารออนไลน์</p><p class="text-xs text-slate-400">เลือกประเภทเอกสารที่ต้องการ</p></div></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500"><?= count($serviceDocuments) ?> ประเภท</span></div>
                    <div class="space-y-5 py-6"><div><p class="mb-2 text-xs font-semibold text-slate-400">ประเภทเอกสาร</p><div class="paper-line red w-4/5"></div></div><div class="grid grid-cols-2 gap-5"><div><p class="mb-2 text-xs font-semibold text-slate-400">ข้อมูลผู้ยื่น</p><div class="paper-line"></div></div><div><p class="mb-2 text-xs font-semibold text-slate-400">รายละเอียด</p><div class="paper-line"></div></div></div><div><p class="mb-2 text-xs font-semibold text-slate-400">ตรวจสอบก่อนส่ง</p><div class="space-y-2"><div class="paper-line w-full"></div><div class="paper-line w-11/12"></div><div class="paper-line w-3/4"></div></div></div></div>
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4"><div class="flex items-center gap-2 text-xs font-bold text-slate-600"><i class="fa-solid fa-shield-halved text-fet-700"></i>ตรวจสอบข้อมูลก่อนส่ง</div><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-fet-600 text-white"><i class="fa-solid fa-arrow-right"></i></span></div>
                </div>
                <div class="absolute -bottom-8 right-0 z-20 rounded-2xl border border-white/20 bg-slate-900/95 p-4 text-white shadow-2xl sm:-right-7"><div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/20 text-blue-300"><i class="fa-solid fa-bell"></i></span><div><p class="text-xs text-slate-400">การแจ้งเตือน</p><p class="text-sm font-bold">มีเอกสารต้องดำเนินการ</p></div></div></div>
            </div>
        </div>
    </section>

    <section id="services" class="scroll-mt-24 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center"><p class="text-sm font-extrabold uppercase tracking-[.2em] text-fet-700">Services</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">บริการหลักสำหรับผู้ใช้งาน</h2><p class="mt-4 leading-7 text-slate-600">เลือกจัดทำเอกสารตามประเภท ส่งให้อาจารย์พิจารณา และติดตามผลได้จากบัญชีของคุณ</p></div>
            <div class="mt-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($serviceDocuments as $document): ?>
                    <?php $isInternshipDocument = str_starts_with((string) $document['document_code'], 'INTERNSHIP_'); ?>
                    <article class="flex min-h-32 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3"><span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= $isInternshipDocument ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-fet-700' ?>"><i class="fa-solid <?= $isInternshipDocument ? 'fa-briefcase' : 'fa-file-lines' ?>"></i></span><span class="rounded-lg px-2 py-1 text-xs font-black <?= $isInternshipDocument ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-fet-700' ?>"><?= htmlspecialchars($document['document_code'], ENT_QUOTES, 'UTF-8') ?></span></div>
                        <h3 class="mt-4 text-sm font-extrabold leading-6 text-slate-900"><?= htmlspecialchars($document['document_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    </article>
                <?php endforeach; ?>
                <?php if (!$serviceDocuments): ?><p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">ยังไม่มีเอกสารที่เปิดใช้งาน</p><?php endif; ?>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="scroll-mt-24 border-y border-slate-200 bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center"><p class="text-sm font-extrabold uppercase tracking-[.2em] text-fet-700">How it works</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">ผู้ใช้แต่ละกลุ่มใช้ระบบอย่างไร</h2><p class="mt-4 leading-7 text-slate-600">ผู้ใช้เข้าสู่ระบบด้วยบัญชีเดียวกัน แต่จะเห็นเมนูและทำงานตามสิทธิ์ของตน</p></div>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-100 text-red-700"><i class="fa-solid fa-user-graduate"></i></span><h3 class="mt-4 font-extrabold">นักศึกษา</h3><p class="mt-2 text-sm leading-6 text-slate-600">เลือกเอกสาร กรอกข้อมูล บันทึกแบบร่าง ส่งคำร้อง อัปโหลดเอกสารตอบรับ และติดตามผลการพิจารณา</p></article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700"><i class="fa-solid fa-chalkboard-user"></i></span><h3 class="mt-4 font-extrabold">อาจารย์</h3><p class="mt-2 text-sm leading-6 text-slate-600">ตรวจเอกสารของนักศึกษาที่อยู่ในความรับผิดชอบ อนุมัติหรือส่งกลับเพื่อแก้ไข และดูแผนนิเทศ</p></article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"><i class="fa-solid fa-user-gear"></i></span><h3 class="mt-4 font-extrabold">เจ้าหน้าที่</h3><p class="mt-2 text-sm leading-6 text-slate-600">ดูแลข้อมูลกลุ่มเรียน อาจารย์ที่ปรึกษา ปฏิทินสหกิจ เอกสารทางการ และติดตามการดำเนินงานของสาขา</p></article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-700"><i class="fa-solid fa-user-shield"></i></span><h3 class="mt-4 font-extrabold">ผู้ดูแลระบบ</h3><p class="mt-2 text-sm leading-6 text-slate-600">จัดการบัญชีผู้ใช้ คณะ สาขา กลุ่มเรียน สิทธิ์ผู้ใช้ ประเภทเอกสาร และลำดับผู้พิจารณา</p></article>
            </div>
            <div class="mt-10 overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 p-5 sm:p-7"><div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="text-xl font-extrabold">ลำดับการใช้งานเอกสาร</h3><p class="mt-1 text-sm text-slate-600">ระบบบันทึกทุกขั้นตอนเพื่อให้ตรวจสอบย้อนหลังได้</p></div><a href="auth/login.php" class="text-sm font-extrabold text-fet-700 hover:text-fet-800">เข้าสู่ระบบ <i class="fa-solid fa-arrow-right ml-1"></i></a></div><ol class="mt-6 grid gap-3 md:grid-cols-4"><li class="rounded-2xl bg-white p-4 shadow-sm"><span class="text-xs font-black text-fet-700">ขั้นที่ 1</span><h4 class="mt-2 font-extrabold">เข้าสู่ระบบ</h4><p class="mt-1 text-sm leading-6 text-slate-600">ใช้บัญชีตามบทบาทของผู้ใช้</p></li><li class="rounded-2xl bg-white p-4 shadow-sm"><span class="text-xs font-black text-fet-700">ขั้นที่ 2</span><h4 class="mt-2 font-extrabold">เลือกและจัดทำเอกสาร</h4><p class="mt-1 text-sm leading-6 text-slate-600">กรอกข้อมูล บันทึกแบบร่าง และตรวจทานก่อนส่ง</p></li><li class="rounded-2xl bg-white p-4 shadow-sm"><span class="text-xs font-black text-fet-700">ขั้นที่ 3</span><h4 class="mt-2 font-extrabold">พิจารณาตามลำดับ</h4><p class="mt-1 text-sm leading-6 text-slate-600">ระบบส่งต่อให้ผู้พิจารณาที่เกี่ยวข้อง</p></li><li class="rounded-2xl bg-white p-4 shadow-sm"><span class="text-xs font-black text-fet-700">ขั้นที่ 4</span><h4 class="mt-2 font-extrabold">ติดตามและดำเนินการต่อ</h4><p class="mt-1 text-sm leading-6 text-slate-600">ดูผล แก้ไขเมื่อถูกส่งกลับ หรือดาวน์โหลดเอกสารเมื่อเสร็จสิ้น</p></li></ol></div>
        </div>
    </section>

    <section class="py-16 sm:py-20"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="overflow-hidden rounded-[2rem] bg-fet-700 px-6 py-10 text-white shadow-xl sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-10 lg:px-14"><div><p class="text-sm font-bold text-red-100">พร้อมจัดทำเอกสารแล้วหรือยัง?</p><h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">เข้าสู่ระบบเพื่อเริ่มดำเนินการ</h2><p class="mt-3 text-sm text-red-100">นักศึกษา อาจารย์ และเจ้าหน้าที่ ใช้หน้าเข้าสู่ระบบเดียวกัน</p></div><div class="mt-7 flex flex-col gap-3 sm:flex-row lg:mt-0"><a href="auth/login.php" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 font-extrabold text-fet-700 hover:bg-red-50"><i class="fa-solid fa-right-to-bracket"></i>เข้าสู่ระบบ</a><a href="auth/register.php" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 px-6 py-3 font-extrabold text-white hover:bg-white/10">สมัครสมาชิก</a></div></div></div></section>
</main>

<footer class="border-t border-slate-200 bg-white"><div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-center sm:px-6 md:flex-row md:items-center md:justify-between md:text-left lg:px-8"><div class="flex items-center justify-center gap-3 md:justify-start"><img src="assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="h-14 w-12 object-contain"><img src="assets/images/logo-transparent.png" alt="โลโก้คณะ" class="h-8 w-14 object-contain"><div><p class="text-sm font-extrabold text-slate-800">คณะวิศวกรรมศาสตร์และเทคโนโลยี</p><p class="text-xs text-slate-500">มหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน</p></div></div><p class="text-xs text-slate-400">© <?= date('Y') ?> Faculty of Engineering and Technology</p></div></footer>

<script>
const mobileButton=document.getElementById('mobile-menu-button'),mobileMenu=document.getElementById('mobile-menu');
mobileButton?.addEventListener('click',()=>{const open=mobileMenu.classList.toggle('hidden')===false;mobileButton.setAttribute('aria-expanded',String(open));mobileButton.querySelector('i').className=open?'fa-solid fa-xmark':'fa-solid fa-bars'});
mobileMenu?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{mobileMenu.classList.add('hidden');mobileButton?.setAttribute('aria-expanded','false');const icon=mobileButton?.querySelector('i');if(icon)icon.className='fa-solid fa-bars'}));
</script>
</body>
</html>
