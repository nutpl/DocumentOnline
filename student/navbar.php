<?php
require_once __DIR__.'/../helpers/alert_response_helper.php';
renderQueuedSweetAlert();
$navStudent=$student ?? [];
if ((!array_key_exists('student_prefix', $navStudent) || empty($navStudent['student_id'])) && isset($_SESSION['user_id'])) {
    if (!isset($pdo)) require_once __DIR__.'/../config/database.php';
    $navStmt=$pdo->prepare('SELECT s.student_id,s.student_number,s.firstname,s.lastname,
        COALESCE(p.prefix_short,p.prefix_name,"") AS student_prefix
        FROM student s LEFT JOIN prefixes p ON p.prefix_id=s.prefix_id
        WHERE s.user_id=? LIMIT 1');
    $navStmt->execute([$_SESSION['user_id']]);
    $navStudent=$navStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

if (!isset($student_document_count) && !empty($navStudent['student_id'])) {
    $navCountStmt=$pdo->prepare('SELECT COUNT(*) AS total,SUM(document_status IN ("submitted","in_review","approved")) AS submitted FROM documents WHERE student_id=?');
    $navCountStmt->execute([$navStudent['student_id']]);
    $navCounts=$navCountStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $student_document_count=(int)($navCounts['total'] ?? 0);
    $student_submitted_count=(int)($navCounts['submitted'] ?? 0);
}
require_once __DIR__.'/../helpers/notification_helper.php';
$unread_notification_count=unreadNotificationCount($pdo,(int)($_SESSION['user_id']??0));
$navNotifications=[];
try {
    $navNotificationStmt=$pdo->prepare('SELECT notification_id,document_id,title,message,target_url,is_read,created_at FROM notifications WHERE recipient_user_id=? ORDER BY created_at DESC');
    $navNotificationStmt->execute([(int)($_SESSION['user_id']??0)]);
    $navNotifications=$navNotificationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $navNotifications=[];
}
function studentNotificationTarget(array $notification): string {
    $target=trim((string)($notification['target_url'] ?? '')) ?: 'history.php';
    $fragment='';
    $hashPosition=strpos($target,'#');
    if ($hashPosition !== false) { $fragment=substr($target,$hashPosition); $target=substr($target,0,$hashPosition); }
    return $target.(str_contains($target,'?') ? '&' : '?').'notification_id='.(int)$notification['notification_id'].$fragment;
}
?>
<header class="student-navbar sticky top-0 z-20 flex h-20 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3">
        <button id="student-sidebar-open" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-600 lg:hidden" title="เปิดเมนู"><i class="fa-solid fa-bars text-lg"></i></button>
    </div>
    <div class="flex items-center gap-3 sm:gap-5">
        <?php require_once __DIR__.'/../helpers/theme_helper.php'; renderThemeToggle(); ?>
        <div class="relative" data-notification-menu>
            <button type="button" data-notification-toggle aria-expanded="false" aria-controls="student-notification-popover" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 transition hover:bg-red-50 hover:text-red-700" title="การแจ้งเตือน"><i class="fa-regular fa-bell text-xl"></i><?php if ($unread_notification_count > 0): ?><span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-bold leading-5 text-white"><?= (int)$unread_notification_count ?></span><?php endif; ?></button>
            <section id="student-notification-popover" data-notification-popover hidden class="absolute right-0 top-full z-50 mt-3 w-[min(24rem,calc(100vw-2rem))] overflow-visible rounded-2xl border border-slate-200 bg-white shadow-2xl" aria-label="รายการแจ้งเตือนทั้งหมด">
                <span class="absolute -top-2 right-3 h-4 w-4 rotate-45 border-l border-t border-slate-200 bg-white"></span>
                <header class="relative flex items-center gap-2 border-b border-slate-100 px-4 py-3"><span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-700"><i class="fa-regular fa-bell"></i></span><div><h2 class="text-sm font-bold text-slate-900">การแจ้งเตือนทั้งหมด</h2><p class="text-xs text-slate-500"><?= $unread_notification_count > 0 ? 'ยังไม่ได้อ่าน '.(int)$unread_notification_count.' รายการ' : 'ไม่มีรายการที่ยังไม่ได้อ่าน' ?></p></div></header>
                <div class="relative max-h-[32rem] overflow-y-auto divide-y divide-slate-100"><?php foreach($navNotifications as $item): ?><a href="<?= htmlspecialchars(studentNotificationTarget($item),ENT_QUOTES,'UTF-8') ?>" class="block px-4 py-3 transition hover:bg-slate-50 <?= !(int)$item['is_read']?'border-l-4 border-red-600 bg-red-50/40 pl-3':'' ?>"><div class="flex gap-3"><span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full <?= !(int)$item['is_read']?'bg-red-100 text-red-700':'bg-slate-100 text-slate-500' ?>"><i class="fa-regular fa-bell text-xs"></i></span><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900"><?= htmlspecialchars($item['title'],ENT_QUOTES,'UTF-8') ?></p><p class="mt-0.5 line-clamp-2 text-xs leading-5 text-slate-600"><?= htmlspecialchars($item['message'],ENT_QUOTES,'UTF-8') ?></p><p class="mt-1 text-[11px] text-slate-400"><?= htmlspecialchars($item['created_at'],ENT_QUOTES,'UTF-8') ?></p></div></div></a><?php endforeach; ?><?php if(!$navNotifications): ?><div class="px-5 py-9 text-center text-sm text-slate-500"><i class="fa-regular fa-bell-slash mb-2 block text-2xl text-slate-300"></i>ยังไม่มีการแจ้งเตือน</div><?php endif; ?></div>
            </section>
        </div>
        <div class="hidden text-right sm:block"><p class="text-sm font-bold text-gray-900"><?= htmlspecialchars(trim(($navStudent['student_prefix'] ?? '').($navStudent['firstname'] ?? '').' '.($navStudent['lastname'] ?? ''))) ?: '-' ?></p><p class="text-xs text-gray-500"><?= htmlspecialchars($navStudent['student_number'] ?? '-') ?></p></div>
        <a href="profile.php"><img src="../assets/images/welcome.png" alt="โปรไฟล์" class="student-profile-image h-11 w-11 rounded-full border-2 border-red-500 object-cover"></a>
    </div>
</header>
<script>
(() => {
    const sidebar=document.getElementById('student-sidebar'), overlay=document.getElementById('student-sidebar-overlay');
    const collapseButton=document.getElementById('student-sidebar-collapse');
    const open=()=>{sidebar?.classList.remove('-translate-x-full');overlay?.classList.remove('hidden');};
    const close=()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden');};
    const setCollapsed=(collapsed)=>{
        if(!sidebar||!collapseButton)return;
        sidebar.classList.toggle('student-sidebar-collapsed',collapsed);
        collapseButton.setAttribute('aria-expanded',String(!collapsed));
        collapseButton.setAttribute('aria-label',collapsed?'ขยายแถบเมนู':'ย่อแถบเมนู');
        collapseButton.title=collapsed?'ขยายแถบเมนู':'ย่อแถบเมนู';
        collapseButton.querySelector('i')?.classList.toggle('fa-chevron-right',collapsed);
        collapseButton.querySelector('i')?.classList.toggle('fa-chevron-left',!collapsed);
    };
    document.getElementById('student-sidebar-open')?.addEventListener('click',open);
    document.getElementById('student-sidebar-close')?.addEventListener('click',close);
    overlay?.addEventListener('click',close);
    const compactNavigation=()=>window.innerWidth<=1100;
    if(!compactNavigation())setCollapsed(localStorage.getItem('studentSidebarCollapsed')==='1');
    collapseButton?.addEventListener('click',()=>{
        const collapsed=!sidebar.classList.contains('student-sidebar-collapsed');
        setCollapsed(collapsed);
        localStorage.setItem('studentSidebarCollapsed',collapsed?'1':'0');
    });
    window.addEventListener('resize',()=>{
        if(compactNavigation()){
            close();
            sidebar?.classList.remove('student-sidebar-collapsed');
        }else{
            setCollapsed(localStorage.getItem('studentSidebarCollapsed')==='1');
        }
    });
    const notificationMenu=document.querySelector('[data-notification-menu]');
    const notificationToggle=document.querySelector('[data-notification-toggle]');
    const notificationPopover=document.querySelector('[data-notification-popover]');
    const closeNotifications=()=>{if(notificationPopover){notificationPopover.hidden=true;notificationToggle?.setAttribute('aria-expanded','false');}};
    notificationToggle?.addEventListener('click',()=>{
        if(!notificationPopover)return;
        const open=notificationPopover.hidden;
        notificationPopover.hidden=!open;
        notificationToggle.setAttribute('aria-expanded',String(open));
    });
    document.addEventListener('click',(event)=>{if(notificationMenu&&!notificationMenu.contains(event.target))closeNotifications();});
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape')closeNotifications();});
})();
</script>
<link rel="stylesheet" href="/Project/assets/css/status-badges.css?v=20260815-2">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/Project/assets/js/jquery-live-search.js"></script>
