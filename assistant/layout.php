<?php
require_once __DIR__ . '/../helpers/assistant_workspace_helper.php';
require_once __DIR__ . '/../helpers/role_switcher_helper.php';
require_once __DIR__ . '/../helpers/theme_helper.php';

function assistantWorkspaceHeader(string $title, string $subtitle, string $activeRole): void
{
    $roleLabel = assistantWorkspaceRoleLabel($activeRole);
    ?>
    <!doctype html>
    <html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
        <?php renderThemeAssets(); ?>
        <style>
            @font-face{font-family:'TH Sarabun PSK';src:url('/Project/assets/fonts/thsarabunpsk/THSarabun.ttf') format('truetype');font-style:normal;font-weight:400;font-display:swap}
            @font-face{font-family:'TH Sarabun PSK';src:url('/Project/assets/fonts/thsarabunpsk/THSarabun-Bold.ttf') format('truetype');font-style:normal;font-weight:700;font-display:swap}
            html{font-size:17px}
            body{font-family:'TH Sarabun PSK',Tahoma,'Noto Sans Thai',sans-serif;font-size:22px;line-height:1.35}
            .text-xs{font-size:18px!important}.text-sm{font-size:20px!important}.text-base{font-size:22px!important}
            .text-lg{font-size:24px!important}.text-xl{font-size:27px!important}.text-2xl{font-size:31px!important}
            .text-3xl{font-size:36px!important}
            @media(max-width:639px){body{font-size:21px}.text-xs{font-size:17px!important}.text-sm{font-size:19px!important}.text-base{font-size:21px!important}.text-lg{font-size:23px!important}.text-xl{font-size:25px!important}.text-2xl{font-size:29px!important}}
        </style>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900">
        <?php renderQueuedSweetAlert(); ?>
        <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur">
            <div class="mx-auto flex min-h-[4.75rem] max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-700 text-base text-white shadow-sm"><i class="fa-solid fa-shield-halved"></i></span>
                    <div class="min-w-0">
                        <h1 class="truncate text-base font-black text-slate-800 sm:text-lg"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></h1>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <?php renderRoleSwitcher(); ?>
                    <?php renderThemeToggle(); ?>
                    <div class="relative" data-assistant-history-menu>
                        <button type="button" data-assistant-history-toggle aria-expanded="false" aria-controls="assistant-history-popover" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 transition hover:border-red-100 hover:bg-red-50 hover:text-red-700" title="ประวัติการดำเนินการ"><i class="fa-solid fa-clock-rotate-left"></i><span class="hidden sm:inline">ประวัติ</span></button>
                        <section id="assistant-history-popover" data-assistant-history-popover hidden class="absolute right-0 top-full z-50 mt-3 w-[min(28rem,calc(100vw-2rem))] overflow-visible rounded-2xl border border-slate-200 bg-white shadow-2xl" aria-label="ประวัติการดำเนินการ">
                            <span class="absolute -top-2 right-3 h-4 w-4 rotate-45 border-l border-t border-slate-200 bg-white"></span>
                            <header class="relative flex items-center gap-3 border-b border-slate-100 px-4 py-3"><span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid fa-clock-rotate-left"></i></span><div><h2 class="text-sm font-bold text-slate-900">ประวัติการดำเนินการ</h2><p class="text-xs text-slate-500">รายการล่าสุดในระบบ</p></div></header>
                            <div data-assistant-history-list class="max-h-[32rem] overflow-y-auto"><div class="px-5 py-10 text-center text-sm text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>กำลังโหลดประวัติ</div></div>
                        </section>
                    </div>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-7xl p-4 sm:p-6">
    <?php
}

function assistantWorkspaceFooter(): void
{
    ?>
        <script>
        (()=>{
            const menu=document.querySelector('[data-assistant-history-menu]');
            const button=document.querySelector('[data-assistant-history-toggle]');
            const popover=document.querySelector('[data-assistant-history-popover]');
            const list=document.querySelector('[data-assistant-history-list]');
            if(!menu||!button||!popover||!list)return;
            const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const load=async()=>{list.innerHTML='<div class="px-5 py-10 text-center text-sm text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>กำลังโหลดประวัติ</div>';try{const response=await fetch('/Project/assistant/history_feed.php',{headers:{Accept:'application/json'}});const data=await response.json();const items=Array.isArray(data.items)?data.items:[];if(!items.length){list.innerHTML='<div class="px-5 py-10 text-center text-sm text-slate-500"><i class="fa-regular fa-clock mb-2 block text-2xl text-slate-300"></i>ยังไม่มีประวัติการดำเนินการ</div>';return;}list.innerHTML=items.map(item=>'<article class="border-b border-slate-100 px-4 py-3 last:border-0"><div class="flex items-start gap-3"><span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700"><i class="fa-solid fa-pen-to-square text-xs"></i></span><div class="min-w-0"><p class="text-sm font-bold text-slate-800">'+escapeHtml(item.actor_name)+'</p><p class="mt-0.5 text-xs font-semibold text-red-700">'+escapeHtml(item.activity)+'</p><p class="mt-0.5 line-clamp-2 text-xs text-slate-600">'+escapeHtml(item.detail||'-')+'</p><p class="mt-1 text-[11px] text-slate-400">'+escapeHtml(item.activity_time)+'</p></div></div></article>').join('');}catch(_){list.innerHTML='<div class="px-5 py-10 text-center text-sm text-slate-500">ไม่สามารถโหลดประวัติได้</div>';}};
            const close=()=>{popover.hidden=true;button.setAttribute('aria-expanded','false');};
            button.addEventListener('click',()=>{const open=popover.hidden;popover.hidden=!open;button.setAttribute('aria-expanded',String(open));if(open)load();});
            document.addEventListener('click',event=>{if(!menu.contains(event.target))close();});
            document.addEventListener('keydown',event=>{if(event.key==='Escape')close();});
        })();
        </script>
        </main>
    </body>
    </html>
    <?php
}

function assistantModuleCard(string $title, string $description, string $module, string $icon): void
{
    ?>
    <a href="/Project/assistant/work.php?module=<?= urlencode($module) ?>" class="group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-700"><i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i></span>
        <h3 class="mt-4 font-black text-slate-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="mt-1 text-sm leading-6 text-slate-500"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
    </a>
    <?php
}
