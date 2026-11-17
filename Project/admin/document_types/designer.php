<?php
session_start();
require_once '../../config/database.php';
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../../auth/login.php'); exit; }
$_SESSION['admin_error'] = 'ระบบปิดการแก้ไขตำแหน่งช่องกรอกของแม่แบบแล้ว';
header('Location: index.php');
exit;

$templateId=(int)($_GET['id'] ?? 0);
$stmt=$pdo->prepare('SELECT t.template_id,t.template_name,t.pdf_template,dt.document_code,dt.document_name
    FROM document_template t JOIN document_type dt ON dt.document_type_id=t.document_type_id WHERE t.template_id=?');
$stmt->execute([$templateId]);
$template=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$template) { http_response_code(404); exit('ไม่พบแม่แบบเอกสาร'); }
$referenceOnly=in_array($template['document_code'],['INTERNSHIP_REQUEST','INTERNSHIP_SEND','INTERNSHIP_INFO'],true);
?>
<!doctype html>
<html lang="th">
<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">

    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>กำหนดช่องกรอก <?= htmlspecialchars($template['document_code']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .designer-field{position:absolute;border:2px dashed #dc2626;background:rgba(254,226,226,.8);cursor:move;overflow:hidden;color:#991b1b;font-size:12px;padding:3px;user-select:none}
        .designer-field.active{border-style:solid;box-shadow:0 0 0 3px rgba(220,38,38,.18)}
        .designer-field.reference-only{cursor:default;border-color:#2563eb;background:rgba(219,234,254,.78);color:#1e3a8a}
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<header class="sticky top-0 z-30 flex items-center justify-between border-b bg-white px-5 py-4 shadow-sm">
    <div class="flex items-center gap-3"><a href="index.php" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border"><i class="fa-solid fa-arrow-left"></i></a><div><p class="text-xs font-bold text-red-600"><?= htmlspecialchars($template['document_code']) ?></p><h1 class="font-bold"><?= htmlspecialchars($template['template_name']) ?></h1></div></div>
    <div class="flex items-center gap-3"><div id="page-tabs" class="flex rounded-lg bg-slate-100 p-1"></div><span id="save-status" class="text-sm text-slate-500">ลากกล่องเพื่อปรับตำแหน่ง</span></div>
</header>

<div class="grid items-start gap-5 p-4 lg:grid-cols-[330px_1fr] lg:p-6">
    <aside class="rounded-xl border bg-white p-5 shadow-sm lg:sticky lg:top-24">
        <?php if($referenceOnly): ?>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><p class="font-bold"><i class="fa-solid fa-circle-info mr-2"></i>ตำแหน่งอ้างอิงจากระบบ</p><p class="mt-1 leading-6 text-blue-700">แสดงตำแหน่งที่ใช้อยู่ในเอกสารจริงแบบดูอย่างเดียว การดาวน์โหลด PDF ยังคงใช้ตำแหน่งเดิมและไม่ถูกเปลี่ยนแปลงจากหน้านี้</p></div>
        <?php else: ?>
        <h2 class="text-lg font-bold">เพิ่มช่องกรอก</h2><p class="mt-1 text-sm text-slate-500">คลิกบนกระดาษเพื่อเลือกตำแหน่ง แล้วกรอกข้อมูลช่อง</p>
        <?php endif; ?>
        <form id="field-form" class="mt-5 space-y-4 <?= $referenceOnly?'hidden':'' ?>">
            <label class="block"><span class="mb-1 block text-sm font-semibold">ชื่อฟิลด์ภาษาอังกฤษ</span><input id="field-name" required pattern="[A-Za-z0-9_]+" placeholder="course_code" class="w-full rounded-lg border px-3 py-2.5"></label>
            <label class="block"><span class="mb-1 block text-sm font-semibold">ชื่อที่แสดง</span><input id="field-label" required placeholder="รหัสวิชา" class="w-full rounded-lg border px-3 py-2.5"></label>
            <label class="block"><span class="mb-1 block text-sm font-semibold">ประเภทข้อมูล</span><select id="field-type" class="w-full rounded-lg border px-3 py-2.5"><option value="text">ข้อความ</option><option value="textarea">ข้อความหลายบรรทัด</option><option value="date">วันที่</option><option value="number">ตัวเลข</option><option value="checkbox">ตัวเลือก</option></select></label>
            <div class="grid grid-cols-2 gap-3"><label><span class="mb-1 block text-sm">กว้าง</span><input id="field-width" type="number" value="180" min="40" class="w-full rounded-lg border px-3 py-2"></label><label><span class="mb-1 block text-sm">สูง</span><input id="field-height" type="number" value="35" min="20" class="w-full rounded-lg border px-3 py-2"></label></div>
            <div class="grid grid-cols-2 gap-3"><label><span class="mb-1 block text-sm">ขนาดตัวอักษร</span><input id="font-size" type="number" value="10" min="6" max="30" class="w-full rounded-lg border px-3 py-2"></label><label class="flex items-end gap-2 pb-2"><input id="required-field" type="checkbox" checked><span class="text-sm">จำเป็นต้องกรอก</span></label></div>
            <input id="pos-x" type="hidden" value="100"><input id="pos-y" type="hidden" value="100"><input id="page-no" type="hidden" value="1">
            <button class="w-full rounded-lg bg-red-600 py-3 font-bold text-white hover:bg-red-700"><i class="fa-solid fa-plus mr-2"></i>เพิ่มช่องกรอก</button>
        </form>
        <div class="mt-6 border-t pt-5"><div class="flex items-center justify-between"><h3 class="font-bold"><?= $referenceOnly?'รายการตำแหน่งอ้างอิง':'ช่องที่สร้างแล้ว' ?></h3><span id="field-count" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">0</span></div><div id="field-list" class="mt-3 max-h-72 space-y-2 overflow-y-auto"></div></div>
    </aside>

    <main class="min-w-0 overflow-auto rounded-xl border bg-slate-300 p-4 shadow-inner">
        <div id="pdf-stage" class="relative mx-auto w-max bg-white shadow-xl"><canvas id="pdf-canvas"></canvas><div id="field-layer" class="absolute inset-0"></div></div>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const templateId=<?= $templateId ?>;
const referenceOnly=<?= $referenceOnly?'true':'false' ?>;
const pdfUrl='../../uploads/pdf/<?= rawurlencode($template['pdf_template']) ?>?v=<?= is_file(__DIR__.'/../../uploads/pdf/'.$template['pdf_template']) ? filemtime(__DIR__.'/../../uploads/pdf/'.$template['pdf_template']) : time() ?>';
const layer=document.getElementById('field-layer'), stage=document.getElementById('pdf-stage');
let fields=[], selectedId=null, currentPage=1, pdfDocument=null, pageWidth=0, pageHeight=0;
pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

async function renderPdf(){if(!pdfDocument)pdfDocument=await pdfjsLib.getDocument(pdfUrl).promise;const page=await pdfDocument.getPage(currentPage),viewport=page.getViewport({scale:1.5}),canvas=document.getElementById('pdf-canvas');pageWidth=viewport.width;pageHeight=viewport.height;canvas.width=pageWidth;canvas.height=pageHeight;stage.style.width=pageWidth+'px';stage.style.height=pageHeight+'px';await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;renderPageTabs();drawFields();}
function renderPageTabs(){const tabs=document.getElementById('page-tabs');tabs.innerHTML='';if(!pdfDocument||pdfDocument.numPages<2)return;for(let page=1;page<=pdfDocument.numPages;page++){const button=document.createElement('button');button.type='button';button.textContent='หน้า '+page;button.className='rounded-md px-3 py-1.5 text-xs font-bold '+(page===currentPage?'bg-white text-red-600 shadow-sm':'text-slate-500');button.onclick=async()=>{currentPage=page;selectedId=null;document.getElementById('page-no').value=page;await renderPdf();};tabs.appendChild(button);}}
async function loadFields(){const response=await fetch('../ajax/load_fields.php?template_id='+templateId);fields=await response.json();drawFields();}
function drawFields(){layer.innerHTML='';const list=document.getElementById('field-list');list.innerHTML='';const pageFields=fields.filter(field=>Number(field.page_no||1)===currentPage&&Number(field.width)>1&&Number(field.height)>1);document.getElementById('field-count').textContent=pageFields.length;pageFields.forEach(field=>{const box=document.createElement('div');box.className='designer-field'+(referenceOnly?' reference-only':'')+(selectedId==field.field_id?' active':'');box.dataset.id=field.field_id;box.textContent=field.field_label;Object.assign(box.style,{left:field.pos_x+'px',top:field.pos_y+'px',width:field.width+'px',height:field.height+'px',fontSize:field.font_size+'px'});if(!referenceOnly)makeDraggable(box,field);box.onclick=e=>{e.stopPropagation();selectedId=field.field_id;drawFields();};layer.appendChild(box);const item=document.createElement('div');item.className='flex items-center gap-2 rounded-lg border p-2 text-sm';item.innerHTML=`<button type="button" class="min-w-0 flex-1 text-left"><strong class="block truncate">${escapeHtml(field.field_label)}</strong><span class="text-xs text-slate-500">${escapeHtml(field.field_name)}</span></button>${referenceOnly?'':'<button type="button" class="delete-field h-8 w-8 text-red-600" title="ลบ"><i class="fa-solid fa-trash"></i></button>'}`;item.querySelector('button').onclick=()=>{selectedId=field.field_id;drawFields();box.scrollIntoView({behavior:'smooth',block:'center'});};const deleteButton=item.querySelector('.delete-field');if(deleteButton)deleteButton.onclick=()=>deleteField(field.field_id);list.appendChild(item);});}
function makeDraggable(box,field){let startX,startY,left,top;box.onpointerdown=e=>{e.preventDefault();box.setPointerCapture(e.pointerId);startX=e.clientX;startY=e.clientY;left=parseFloat(box.style.left);top=parseFloat(box.style.top);};box.onpointermove=e=>{if(!box.hasPointerCapture(e.pointerId))return;const nextLeft=Math.max(0,Math.min(pageWidth-box.offsetWidth,left+e.clientX-startX));const nextTop=Math.max(0,Math.min(pageHeight-box.offsetHeight,top+e.clientY-startY));box.style.left=nextLeft+'px';box.style.top=nextTop+'px';};box.onpointerup=async e=>{if(!box.hasPointerCapture(e.pointerId))return;box.releasePointerCapture(e.pointerId);field.pos_x=parseFloat(box.style.left);field.pos_y=parseFloat(box.style.top);await fetch('../ajax/update_field_position.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({field_id:field.field_id,pos_x:field.pos_x,pos_y:field.pos_y,width:field.width,height:field.height})});showSaved();};}
layer.addEventListener('click',e=>{if(referenceOnly)return;const rect=layer.getBoundingClientRect();document.getElementById('pos-x').value=Math.round(e.clientX-rect.left);document.getElementById('pos-y').value=Math.round(e.clientY-rect.top);});
document.getElementById('field-form').onsubmit=async e=>{e.preventDefault();const payload={template_id:templateId,page_no:currentPage,field_name:document.getElementById('field-name').value,field_label:document.getElementById('field-label').value,field_type:document.getElementById('field-type').value,pos_x:+document.getElementById('pos-x').value,pos_y:+document.getElementById('pos-y').value,width:+document.getElementById('field-width').value,height:+document.getElementById('field-height').value,font_size:+document.getElementById('font-size').value,required:document.getElementById('required-field').checked};const response=await fetch('../ajax/save_field.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}),result=await response.json();if(!response.ok){alert(result.message||'เพิ่มช่องไม่สำเร็จ');return;}e.target.reset();document.getElementById('field-width').value=180;document.getElementById('field-height').value=35;document.getElementById('font-size').value=10;document.getElementById('page-no').value=currentPage;await loadFields();showSaved();};
async function deleteField(id){if(!confirm('ลบช่องกรอกนี้หรือไม่?'))return;await fetch('../ajax/delete_field.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({field_id:id})});await loadFields();showSaved();}
function showSaved(){const el=document.getElementById('save-status');el.textContent='บันทึกแล้ว';el.className='text-sm font-semibold text-green-600';setTimeout(()=>{el.textContent='ลากกล่องเพื่อปรับตำแหน่ง';el.className='text-sm text-slate-500';},1500);}
function escapeHtml(value){const div=document.createElement('div');div.textContent=value;return div.innerHTML;}
(async()=>{await renderPdf();await loadFields();})();
</script>
</body></html>
