<!-- ADMIN SIDEBAR -->

<aside
    id="admin-sidebar"
    class="fixed
           top-0
           left-0
           z-40
           flex
           w-72
           h-screen
           flex-col
           border-r
           border-gray-200
           bg-white
           transition-transform
           -translate-x-full
           lg:translate-x-0"
>

    <!-- LOGO -->

    <div
        class="admin-sidebar-brand h-20
               flex
               items-center
               border-b
               border-gray-200"
    >

        <img src="/Project/assets/images/rmuti-university-seal.png" alt="ตรามหาวิทยาลัยเทคโนโลยีราชมงคลอีสาน" class="admin-university-seal h-16 w-12 shrink-0 object-contain">
        <img src="/Project/assets/images/logo-transparent.png" alt="คณะวิศวกรรมศาสตร์และเทคโนโลยี" class="admin-sidebar-logo h-10 w-14 shrink-0 object-contain">
        <div class="admin-sidebar-title min-w-0"><h2 class="whitespace-nowrap text-base font-bold text-gray-900">ผู้ดูแลระบบ</h2></div>

        <button id="admin-sidebar-collapse" type="button" class="admin-sidebar-collapse hidden lg:inline-flex" title="ย่อแถบเมนู" aria-label="ย่อแถบเมนู" aria-expanded="true"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="admin-sidebar-close" type="button" class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-700 lg:hidden" title="ปิดเมนู" aria-label="ปิดเมนู"><i class="fa-solid fa-xmark text-xl"></i></button>

    </div>


    <!-- MENU -->

    <nav class="flex-1 overflow-y-auto p-4 space-y-2">


        <!-- DASHBOARD -->

        <a
            href="/Project/admin/dashboard.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-gauge w-5"></i>

            <span>

                Dashboard

            </span>

        </a>


        <!-- PERMISSIONS -->

        <a
            href="/Project/admin/permissions/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >
            <i class="fa-solid fa-user-shield w-5"></i>
            <span>สิทธิ์ใช้งาน</span>
        </a>


        <!-- STUDENTS -->

        <a
            href="/Project/admin/students/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-user-graduate w-5"></i>

            <span>

                จัดการนักศึกษา

            </span>

        </a>


        <!-- STAFF -->

        <a
            href="/Project/admin/staffs/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-user-tie w-5"></i>

            <span>

                จัดการเจ้าหน้าที่

            </span>

        </a>


        <!-- TEACHERS -->

        <a
            href="/Project/admin/teachers/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-chalkboard-user w-5"></i>

            <span>

                จัดการอาจารย์

            </span>

        </a>


        <!-- เส้นแบ่ง -->

        <div class="border-t border-gray-700 my-4"></div>


        <!-- FACULTIES -->

        <a
            href="/Project/admin/faculties/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-building-columns w-5"></i>

            <span>

                จัดการคณะ

            </span>

        </a>


        <!-- DEPARTMENTS -->

        <a
            href="/Project/admin/departments/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-sitemap w-5"></i>

            <span>

                จัดการสาขา

            </span>

        </a>


        <!-- GROUP CLASS -->

        <a
            href="/Project/admin/group_class/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-people-group w-5"></i>

            <span>

                จัดการกลุ่มเรียน

            </span>

        </a>


        <!-- เส้นแบ่ง -->

        <div class="border-t border-gray-700 my-4"></div>


        <!-- DOCUMENT TYPES -->

        <a
            href="/Project/admin/document_types/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-file-lines w-5"></i>

            <span>

                ประเภทเอกสาร

            </span>

        </a>


        <!-- WORKFLOW -->

        <a
            href="/Project/admin/workflow/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-diagram-project w-5"></i>

            <span>

                ลำดับผู้พิจารณา

            </span>

        </a>

        <a
            href="/Project/admin/workflow/status.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-route w-5"></i>

            <span>

                ประวัติการดำเนินงาน

            </span>

        </a>

        <a
            href="/Project/admin/academic_calendar/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-calendar-days w-5"></i>

            <span>

                ปฏิทินการศึกษา

            </span>

        </a>

        <a
            href="/Project/admin/positions/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-briefcase w-5"></i>

            <span>

                จัดการตำแหน่ง

            </span>

        </a>


        <!-- OFFICE HOLDERS -->

        <a
            href="/Project/admin/office_holders/index.php"
            class="flex items-center gap-3
                   px-4 py-3 rounded-lg
                   text-gray-500
                   hover:bg-red-600
                   hover:text-white
                   transition"
        >

            <i class="fa-solid fa-user-tie w-5"></i>

            <span>

                ผู้ลงนามเอกสารสหกิจ

            </span>

        </a>

    </nav>

    <!-- LOGOUT: kept outside the scrollable navigation so it remains visible. -->
    <div class="admin-sidebar-footer border-t border-gray-200 p-4">
        <a
            href="/Project/auth/logout.php"
            class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 py-3 font-semibold text-gray-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>

</aside>
<div id="admin-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-950/40 backdrop-blur-[1px] lg:hidden" aria-hidden="true"></div>
<script>
(() => {
    const sidebar=document.getElementById('admin-sidebar');
    const button=document.getElementById('admin-sidebar-collapse');
    const setCollapsed=(collapsed)=>{
        if(!sidebar||!button)return;
        sidebar.classList.toggle('admin-sidebar-collapsed',collapsed);
        button.setAttribute('aria-expanded',String(!collapsed));
        button.setAttribute('aria-label',collapsed?'ขยายแถบเมนู':'ย่อแถบเมนู');
        button.title=collapsed?'ขยายแถบเมนู':'ย่อแถบเมนู';
        button.querySelector('i')?.classList.toggle('fa-chevron-right',collapsed);
        button.querySelector('i')?.classList.toggle('fa-chevron-left',!collapsed);
    };
    const menuLinks=[...(sidebar?.querySelectorAll('nav a')||[])];
    const groupDefinitions=[
        {label:'ผู้ใช้งานและสิทธิ์',icon:'fa-users-gear',paths:['/admin/permissions/','/admin/students/','/admin/staffs/','/admin/teachers/','/admin/positions/']},
        {label:'โครงสร้างการศึกษา',icon:'fa-building-columns',paths:['/admin/faculties/','/admin/departments/','/admin/group_class/','/admin/academic_calendar/']},
        {label:'เอกสารและขั้นตอน',icon:'fa-file-signature',paths:['/admin/document_types/','/admin/workflow/','/admin/office_holders/']},
    ];
    const nav=sidebar?.querySelector('nav');
    const pathOf=link=>new URL(link.href,window.location.origin).pathname;
    if(nav){
        nav.querySelectorAll(':scope > div').forEach(divider=>divider.remove());
        const dashboard=menuLinks.find(link=>pathOf(link).endsWith('/admin/dashboard.php'));
        groupDefinitions.forEach(group=>{
            const links=menuLinks.filter(link=>group.paths.some(path=>pathOf(link).includes(path)));
            if(!links.length)return;
            const details=document.createElement('details');details.className='admin-menu-group';
            const summary=document.createElement('summary');summary.innerHTML='<i class="fa-solid '+group.icon+' w-5"></i><span>'+group.label+'</span><i class="fa-solid fa-chevron-down admin-menu-group-chevron ml-auto text-xs"></i>';
            const linksBox=document.createElement('div');linksBox.className='admin-menu-group__links';
            links.forEach(link=>linksBox.appendChild(link));
            details.append(summary,linksBox);nav.appendChild(details);
        });
        dashboard&&nav.insertBefore(dashboard,nav.firstChild);
    }
    menuLinks.forEach(link=>{if(!link.title)link.title=link.querySelector('span')?.textContent.trim()||'';});
    const currentPath=window.location.pathname.replace(/\/index\.php$/,'/');
    let bestLink=null,bestScore=-1;
    menuLinks.filter(link=>!link.href.includes('/auth/logout.php')).forEach(link=>{
        const linkPath=new URL(link.href,window.location.origin).pathname;
        const normalized=linkPath.replace(/\/index\.php$/,'/');
        const directory=normalized.endsWith('/')?normalized:normalized.slice(0,normalized.lastIndexOf('/')+1);
        const exact=currentPath===normalized;
        const matches=exact||currentPath.startsWith(directory);
        const score=matches?(exact?10000:directory.length):-1;
        if(score>bestScore){bestScore=score;bestLink=link;}
    });
    /* Import is a temporary subpage, so it must not highlight a management menu. */
    const isStandaloneImportPage=/\/admin\/teachers\/import\.php$/.test(window.location.pathname);
    if(!isStandaloneImportPage)bestLink?.classList.add('admin-menu-active');
    sidebar?.querySelectorAll('.admin-menu-group').forEach(group=>{
        if(group.querySelector('.admin-menu-active'))group.open=true;
    });
    const compactNavigation=()=>window.innerWidth<=1100;
    if(!compactNavigation())setCollapsed(localStorage.getItem('adminSidebarCollapsed')==='1');
    button?.addEventListener('click',()=>{
        const collapsed=!sidebar.classList.contains('admin-sidebar-collapsed');
        setCollapsed(collapsed);
        localStorage.setItem('adminSidebarCollapsed',collapsed?'1':'0');
    });

    /* Mobile and tablet navigation: the sidebar is an off-canvas menu below lg. */
    document.addEventListener('DOMContentLoaded',()=>{
        const mobileButton=document.getElementById('sidebar-toggle');
        const closeButton=document.getElementById('admin-sidebar-close');
        const overlay=document.getElementById('admin-sidebar-overlay');
        if(!sidebar||!mobileButton||!overlay)return;
        const isMobile=()=>compactNavigation();
        const setMobileMenu=(open)=>{
            if(!isMobile()) return;
            sidebar.classList.toggle('-translate-x-full',!open);
            overlay.classList.toggle('hidden',!open);
            document.body.classList.toggle('overflow-hidden',open);
            mobileButton.setAttribute('aria-expanded',String(open));
            mobileButton.setAttribute('aria-label',open?'ปิดเมนู':'เปิดเมนู');
        };
        mobileButton.addEventListener('click',()=>setMobileMenu(sidebar.classList.contains('-translate-x-full')));
        closeButton?.addEventListener('click',()=>setMobileMenu(false));
        overlay.addEventListener('click',()=>setMobileMenu(false));
        menuLinks.forEach(link=>link.addEventListener('click',()=>setMobileMenu(false)));
        window.addEventListener('resize',()=>{
            if(!isMobile()){
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                mobileButton.setAttribute('aria-expanded','false');
                mobileButton.setAttribute('aria-label','เปิดเมนู');
            }else{
                setMobileMenu(false);
            }
        });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/form-confirm.js?v=<?= filemtime(__DIR__ . '/../../assets/js/form-confirm.js') ?>" defer></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/Project/assets/js/success-alert.js"></script>
<script src="/Project/assets/js/logout-confirm.js?v=20260911-2"></script>
