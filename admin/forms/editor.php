<!DOCTYPE html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>กรอกคำร้อง R.01 คำร้องทั่วไป | คณะวิศวกรรมศาสตร์และเทคโนโลยี มทร.อีสาน</title>

  <!-- Google Fonts: Prompt & Sarabun -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Tailwind CSS v3 Play CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['TH Sarabun PSK', 'Tahoma', 'sans-serif'],
            print: ['TH Sarabun PSK', 'Tahoma', 'sans-serif'],
          },
          colors: {
            brand: {
              red: '#b91c1c',
              redHover: '#991b1b',
              dark: '#09090b',
              sidebar: '#18181b',
            }
          }
        }
      }
    }
  </script>

  <style>
    /* Screen styling for A4 sheet */
    .a4-sheet {
      width: 210mm;
      height: 297mm;
      background-color: white;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
      padding: 15mm 20mm;
      box-sizing: border-box;
      position: relative;
    }

    /* Dotted line input values style */
    .val-line {
      font-family: 'TH Sarabun PSK', Tahoma, sans-serif;
      font-weight: 700;
      color: #1e3a8a; /* Deep blue color for filled text to resemble ink */
      white-space: normal; /* allow wrapping to next line */
      word-break: break-word; /* break long words if needed */
      overflow-wrap: anywhere; /* aggressive wrap for long continuous text */
      display: inline-block; /* keep inline behavior but allow size control */
      max-width: 100%;
    }

    /* Print styles */
    @media print {
      @page {
        size: A4;
        margin: 0;
      }
      body {
        background-color: white !important;
        color: black !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .no-print {
        display: none !important;
      }
      .print-container {
        padding: 0 !important;
        margin: 0 !important;
        background-color: white !important;
        height: auto !important;
        overflow: visible !important;
      }
      .a4-sheet {
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        padding: 15mm 20mm !important;
        width: 210mm !important;
        height: 297mm !important;
        page-break-after: avoid;
        page-break-inside: avoid;
      }
      .val-line {
        color: black !important; /* Printed copy uses standard black ink */
      }
    }
  </style>
</head>
<body class="bg-zinc-800 font-sans min-h-screen flex flex-col lg:flex-row-reverse text-zinc-300">

  <!-- RIGHT PANEL: FORM INPUTS (no-print) -->
  <aside class="w-full lg:w-[450px] bg-brand-sidebar flex-shrink-0 border-l border-zinc-800 flex flex-col max-h-screen overflow-y-auto no-print">
    <!-- Header Controls -->
    <div class="p-6 border-b border-zinc-800 flex items-center justify-between sticky top-0 bg-brand-sidebar z-10">
      <a href="create_document.php" class="flex items-center gap-2 text-zinc-400 hover:text-white transition-colors text-sm font-semibold">
        <i class="fa-solid fa-arrow-left"></i>
        <span>กลับหน้าเลือกแบบฟอร์ม</span>
      </a>
      <button type="button" onclick="window.print()" title="พิมพ์เอกสาร" aria-label="พิมพ์เอกสาร" class="bg-brand-red hover:bg-brand-redHover text-white px-4 py-2 rounded-xl text-xs font-bold shadow-lg hover:shadow-red-900/30 transition-all duration-200 flex items-center gap-2">
        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/><path d="M18 12h.01"/></svg>
        <span>พิมพ์ / บันทึก PDF</span>
      </button>
    </div>

    <!-- Inputs Content -->
    <div class="p-6 space-y-6 flex-grow">
      <div>
        <h2 class="text-xl font-bold text-white mb-1">กรอกคำร้อง R.01</h2>
        <p class="text-zinc-500 text-xs">ข้อมูลที่ท่านกรอกจะแสดงบนฟอร์มเอกสารทางด้านซ้ายแบบเรียลไทม์</p>
      </div>

      <div class="space-y-4">
        <!-- Date Group -->
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1">วันที่ (1-31)</label>
            <input type="text" id="in-date" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="24">
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1">เดือน</label>
            <input type="text" id="in-month" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="มิถุนายน">
          </div>
          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1">พ.ศ.</label>
            <input type="text" id="in-year" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="2568">
          </div>
        </div>

        <!-- Subject & To -->
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">เรื่อง (Title)</label>
          <input type="text" id="in-subject" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="ขอฝึกงานวิชาชีพในสถานประกอบการ">
        </div>
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">เรียน (To)</label>
          <input type="text" id="in-to" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี">
        </div>

        <!-- Name & Student ID -->
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">ชื่อ-นามสกุล (ข้าพเจ้า)</label>
          <input type="text" id="in-name" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="นาย สมศักดิ์ รักเรียน">
        </div>
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">รหัสประจำตัวนักศึกษา</label>
          <input type="text" id="in-student-id" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="368105123456-7">
        </div>

        <!-- Study Level dropdown -->
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">ระดับการศึกษา (Level of study)</label>
          <select id="in-level" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm">
            <option value="undergraduate" selected>ปริญญาตรี (Undergraduate)</option>
            <option value="certificate">ปวช. (Certificate)</option>
            <option value="diploma">ปวส. (Diploma)</option>
            <option value="master">ปริญญาโท (Master's Degree)</option>
            <option value="doctor">ปริญญาเอก (Doctoral Degree)</option>
          </select>
        </div>

        <!-- Faculty, Curriculum, Telephone -->
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">คณะ (Faculty)</label>
          <input type="text" id="in-faculty" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="วิศวกรรมศาสตร์และเทคโนโลยี">
        </div>
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">หลักสูตร/สาขาวิชา (Curriculum)</label>
          <input type="text" id="in-curriculum" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="วศ.บ. วิศวกรรมคอมพิวเตอร์">
        </div>
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">เบอร์โทรศัพท์ (Telephone)</label>
          <input type="text" id="in-phone" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="089-123-4567">
        </div>

        <!-- Request detail -->
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">มีความประสงค์ (I would like to)</label>
          <textarea id="in-request" rows="5" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="เนื่องด้วยข้าพเจ้ามีความต้องการยื่นเรื่องขอออกหนังสือส่งตัวฝึกประสบการณ์วิชาชีพ ณ บริษัท ก้าวหน้าเทคโนโลยี จำกัด ตั้งแต่วันที่ 1 พฤศจิกายน 2568 ถึงวันที่ 28 กุมภาพันธ์ 2569..."></textarea>
        </div>

        <!-- Signature Name -->
        <div>
          <label class="block text-xs font-semibold text-zinc-400 mb-1">ชื่อสำหรับการลงชื่อ (นักศึกษา)</label>
          <input type="text" id="in-sig-name" class="w-full bg-zinc-900 border border-zinc-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-brand-red text-sm" placeholder="สมศักดิ์ รักเรียน">
        </div>
      </div>
    </div>
  </aside>

  <!-- LEFT PANEL: REAL FORM PREVIEW -->
  <main class="flex-grow flex items-start justify-center p-4 lg:p-8 overflow-y-auto print-container">
    <div class="w-full max-w-[980px] rounded-2xl border border-zinc-700 bg-zinc-900/90 p-3 shadow-2xl">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-lg font-bold text-white">แบบฟอร์มจริงจากไฟล์ PDF</h2>
          <p class="text-xs text-zinc-400">แสดงเอกสารตามไฟล์ในโฟลเดอร์ File เพื่อให้ตรงกับแบบฟอร์มต้นฉบับ</p>
        </div>
        <a href="./File/R.01.pdf" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-zinc-600 bg-zinc-800 px-3 py-2 text-sm font-semibold text-zinc-200 hover:bg-zinc-700">
          <i class="fa-solid fa-up-right-from-square"></i>
          <span>เปิดไฟล์จริง</span>
        </a>
      </div>

      <div class="overflow-hidden rounded-xl border border-zinc-700 bg-white">
        <iframe src="./File/R.01.pdf#toolbar=0&navpanes=0&scrollbar=1" class="min-h-[900px] w-full"></iframe>
      </div>
    </div>
  </main>

        <!-- Please Consider & Sign -->
        <div class="flex justify-between items-end pt-4">
          <!-- Left: Please consider -->
          <div class="flex flex-col items-start">
            <span class="font-bold text-[13px]">จึงเรียนมาเพื่อโปรดพิจารณา</span>
            <span class="text-[9px] text-zinc-500 font-sans -mt-1">Please consider my request.</span>
          </div>

          <!-- Right: Signature box -->
          <div class="text-center w-72 space-y-1">
            <div class="flex items-end justify-center">
              <span>(ลงชื่อ)</span>
              <span id="p-sig-name-ink" class="val-line font-serif italic px-2 border-b border-dotted border-black flex-grow text-center pb-0.5">&nbsp;</span>
              <span>นักศึกษา</span>
            </div>
            <div class="flex justify-between w-full text-[9px] text-zinc-500 font-sans pl-10 pr-12">
              <span>Signature</span>
              <span>Student</span>
            </div>
            <div class="pt-1">
              ( <span id="p-sig-name-print" class="val-line px-2 border-b border-dotted border-black min-w-[200px] inline-block text-center pb-0.5">&nbsp;</span> )
            </div>
          </div>
        </div>

      </div>

      <!-- Administrative comment sections (Follow Steps 1-5) -->
      <div class="mt-auto pt-4 border-t border-zinc-300">
        <p class="text-[9px] font-bold text-zinc-600 mb-2">นักศึกษาดำเนินการตามขั้นตอนที่ ❶ - ❺ : Follow steps 1 - 5</p>
        
        <div class="grid grid-cols-2 gap-3 text-[9px] leading-tight">
          <!-- Column 1 (Steps 1, 2, 3) -->
          <div class="space-y-2">
            <!-- Box 1 -->
            <div class="admin-box p-2">
              <span class="font-bold">❶ ความเห็นของอาจารย์ที่ปรึกษา : Advisor's Comments</span>
              <div class="border-b border-dotted h-4 mt-2"></div>
              <div class="border-b border-dotted h-4 mt-1"></div>
              <div class="flex justify-between items-end mt-3">
                <div class="flex flex-col items-center">
                  <div>ลงชื่อ......................................................</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Signature</span>
                </div>
                <div class="flex flex-col items-center">
                  <div>วันที่............/............/............</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Date</span>
                </div>
              </div>
            </div>
            <!-- Box 2 -->
            <div class="admin-box p-2">
              <span class="font-bold">❷ ความเห็นของประธานหลักสูตร : Chairperson's Comments Program</span>
              <div class="border-b border-dotted h-4 mt-2"></div>
              <div class="border-b border-dotted h-4 mt-1"></div>
              <div class="flex justify-between items-end mt-3">
                <div class="flex flex-col items-center">
                  <div>ลงชื่อ......................................................</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Signature</span>
                </div>
                <div class="flex flex-col items-center">
                  <div>วันที่............/............/............</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Date</span>
                </div>
              </div>
            </div>
            <!-- Box 3 -->
            <div class="admin-box p-2">
              <span class="font-bold">❸ ความเห็นของหัวหน้าสาขา : Head of Department's Comments</span>
              <div class="border-b border-dotted h-4 mt-2"></div>
              <div class="border-b border-dotted h-4 mt-1"></div>
              <div class="flex justify-between items-end mt-3">
                <div class="flex flex-col items-center">
                  <div>ลงชื่อ......................................................</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Signature</span>
                </div>
                <div class="flex flex-col items-center">
                  <div>วันที่............/............/............</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Date</span>
                </div>
              </div>
            </div>
          </div>
          <!-- Column 2 (Steps 4, 5) -->
          <div class="space-y-2 flex flex-col">
            <!-- Box 4 -->
            <div class="admin-box p-2 flex flex-col flex-grow">
              <span class="font-bold mb-1">❹ ผลการพิจารณาของคณบดี : Dean's Consideration</span>
              <div class="flex gap-4 my-1">
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1 font-bold">
                    <span class="w-2.5 h-2.5 border border-black inline-block"></span>
                    <span>อนุมัติ</span>
                  </div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Approved</span>
                </div>
                <div class="flex flex-col items-center">
                  <div class="flex items-center gap-1 font-bold">
                    <span class="w-2.5 h-2.5 border border-black inline-block"></span>
                    <span>ไม่อนุมัติ เนื่องจาก.................................</span>
                  </div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Disapproved because</span>
                </div>
              </div>
              <div class="border-b border-dotted border-zinc-400 h-4 mt-1"></div>
              <div class="border-b border-dotted border-zinc-400 h-4 mt-1"></div>
              <div class="flex justify-between items-end mt-auto pt-2">
                <div class="flex flex-col items-center">
                  <div>ลงชื่อ......................................................</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Signature</span>
                </div>
                <div class="flex flex-col items-center">
                  <div>วันที่............/............/............</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Date</span>
                </div>
              </div>
            </div>
            <!-- Box 5 -->
            <div class="admin-box p-2 flex flex-col h-[105px]">
              <span class="font-bold mb-1">❺ บันทึกเจ้าหน้าที่งานทะเบียนและประมวลผล : Registrar Officer</span>
              <div class="border-b border-dotted border-zinc-400 h-4 mt-1"></div>
              <div class="border-b border-dotted border-zinc-400 h-4 mt-1"></div>
              <div class="flex justify-between items-end mt-auto pt-2">
                <div class="flex flex-col items-center">
                  <div>ลงชื่อ......................................................</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Signature</span>
                </div>
                <div class="flex flex-col items-center">
                  <div>วันที่............/............/............</div>
                  <span class="text-[7px] text-zinc-500 font-sans -mt-0.5">Date</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- A4 Footer -->
      <div class="mt-4 pt-2 border-t border-brand-red flex justify-between items-center text-[8px] font-sans text-zinc-500">
        <div>งานทะเบียนและประมวลผล : Registration and Processing | https://regis.rmuti.ac.th | Tel : 0 4423 3000 (2751–2758)</div>
        <div class="font-semibold text-zinc-700">ฉบับแก้ไขเมื่อวันที่ 16 มิถุนายน 2568</div>
      </div>

    </div>
  </main>

  <!-- JAVASCRIPT REAL-TIME BINDING -->
  <script>
    // Input Fields
    const inDate = document.getElementById('in-date');
    const inMonth = document.getElementById('in-month');
    const inYear = document.getElementById('in-year');
    const inSubject = document.getElementById('in-subject');
    const inTo = document.getElementById('in-to');
    const inName = document.getElementById('in-name');
    const inStudentId = document.getElementById('in-student-id');
    const inLevel = document.getElementById('in-level');
    const inFaculty = document.getElementById('in-faculty');
    const inCurriculum = document.getElementById('in-curriculum');
    const inPhone = document.getElementById('in-phone');
    const inRequest = document.getElementById('in-request');
    const inSigName = document.getElementById('in-sig-name');

    // Preview Fields
    const pDate = document.getElementById('p-date');
    const pMonth = document.getElementById('p-month');
    const pYear = document.getElementById('p-year');
    const pSubject = document.getElementById('p-subject');
    const pTo = document.getElementById('p-to');
    const pName = document.getElementById('p-name');
    const pStudentId = document.getElementById('p-student-id');
    const pFaculty = document.getElementById('p-faculty');
    const pCurriculum = document.getElementById('p-curriculum');
    const pPhone = document.getElementById('p-phone');
    const pSigNameInk = document.getElementById('p-sig-name-ink');
    const pSigNamePrint = document.getElementById('p-sig-name-print');

    // Textarea multi-line wrappers
    const pRequest1 = document.getElementById('p-request-1');
    const pRequest2 = document.getElementById('p-request-2');
    const pRequest3 = document.getElementById('p-request-3');
    const pRequest4 = document.getElementById('p-request-4');

    // Helper to update text bind
    function bind(inputEl, previewEl, placeholder = '&nbsp;') {
      if (!inputEl || !previewEl) return;
      inputEl.addEventListener('input', () => {
        previewEl.innerHTML = inputEl.value.trim() ? inputEl.value : placeholder;
      });
      // Initial trigger
      previewEl.innerHTML = inputEl.value && inputEl.value.trim() ? inputEl.value : (inputEl.placeholder || placeholder);
    }

    bind(inDate, pDate, '&nbsp;&nbsp;&nbsp;&nbsp;');
    bind(inMonth, pMonth, '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
    bind(inYear, pYear, '&nbsp;&nbsp;&nbsp;&nbsp;');
    bind(inSubject, pSubject, '&nbsp;');
    bind(inTo, pTo, '&nbsp;');
    bind(inName, pName, '&nbsp;');
    bind(inStudentId, pStudentId, '&nbsp;');
    bind(inFaculty, pFaculty, '&nbsp;');
    bind(inCurriculum, pCurriculum, '&nbsp;');
    bind(inPhone, pPhone, '&nbsp;');

    // Signature link binding
    if (inSigName) {
      inSigName.addEventListener('input', () => {
        pSigNameInk.innerHTML = inSigName.value.trim() ? inSigName.value : '&nbsp;';
        pSigNamePrint.innerHTML = inSigName.value.trim() ? inSigName.value : '&nbsp;';
      });
      pSigNameInk.innerHTML = inSigName.placeholder || '&nbsp;';
      pSigNamePrint.innerHTML = inSigName.placeholder || '&nbsp;';
    }

    // Study level checkbox management
    const checkIds = ['chk-certificate', 'chk-diploma', 'chk-undergraduate', 'chk-master', 'chk-doctor'];
    function updateCheckboxes() {
      if (!inLevel) return;
      const selected = inLevel.value;
      checkIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '';
      });
      let targetId = 'chk-undergraduate';
      if (selected === 'certificate') targetId = 'chk-certificate';
      else if (selected === 'diploma') targetId = 'chk-diploma';
      else if (selected === 'master') targetId = 'chk-master';
      else if (selected === 'doctor') targetId = 'chk-doctor';
      const targetEl = document.getElementById(targetId);
      if (targetEl) targetEl.innerHTML = '✓';
    }
    if (inLevel) {
      inLevel.addEventListener('change', updateCheckboxes);
      updateCheckboxes(); // Initial check
    }

    // Request text splitting across dotted lines
    if (inRequest) {
      inRequest.addEventListener('input', () => {
        const text = inRequest.value || '';
        const lineLen1 = 70;
        const lineLenRest = 95;

        let r1 = '';
        let r2 = '';
        let r3 = '';
        let r4 = '';

        if (text.length <= lineLen1) {
          r1 = text;
        } else {
          r1 = text.substring(0, lineLen1);
          let rem1 = text.substring(lineLen1);
          if (rem1.length <= lineLenRest) {
            r2 = rem1;
          } else {
            r2 = rem1.substring(0, lineLenRest);
            let rem2 = rem1.substring(lineLenRest);
            if (rem2.length <= lineLenRest) {
              r3 = rem2;
            } else {
              r3 = rem2.substring(0, lineLenRest);
              r4 = rem2.substring(lineLenRest, lineLenRest * 2); // Cap at 4 lines
            }
          }
        }

        if (pRequest1) pRequest1.innerHTML = r1 ? r1 : '&nbsp;';
        if (pRequest2) pRequest2.innerHTML = r2 ? r2 : '&nbsp;';
        if (pRequest3) pRequest3.innerHTML = r3 ? r3 : '&nbsp;';
        if (pRequest4) pRequest4.innerHTML = r4 ? r4 : '&nbsp;';
      });
      // Initialize request lines
      if (pRequest1) pRequest1.innerHTML = inRequest.placeholder || '&nbsp;';
    }
  </script>
</body>
</html>
