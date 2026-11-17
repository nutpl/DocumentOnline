<?php
require "../db.php";

$template_id = intval($_GET['id']);

$sql = mysqli_query($conn,"
SELECT *
FROM document_template
WHERE template_id=$template_id
");

$template=mysqli_fetch_assoc($sql);

if(!$template){
    die("Template not found");
}

$pdf="../uploads/pdf/".$template['pdf_template'];

?>
<!DOCTYPE html>
<html>

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260911-2">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>

<title>Template Designer</title>

<style>

body{

margin:0;
background:#eee;
font-family:"TH Sarabun PSK",Tahoma,sans-serif;

}

.toolbar{

background:#333;
color:white;
padding:15px;
display:flex;
flex-wrap:wrap;
gap:10px;

}

#viewer{

position:relative;
display:inline-block;
margin:20px;

}

.viewer-scroll{overflow:auto;max-width:100vw;}

canvas{

border:1px solid #999;

}

.field{

position:absolute;

border:2px dashed red;

padding:4px;

background:white;

cursor:move;

font-size:12px;

}

</style>

</head>

<body>

<div class="toolbar">

<h2>

Template Designer

</h2>

</div>

<div class="viewer-scroll"><div id="viewer">

<canvas id="pdf-canvas"></canvas>

</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>

pdfjsLib.GlobalWorkerOptions.workerSrc =
'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const url="<?php echo $pdf;?>";

const loadingTask=pdfjsLib.getDocument(url);

loadingTask.promise.then(function(pdf){

pdf.getPage(1).then(function(page){

const scale=1.5;

const viewport=page.getViewport({scale});

const canvas=document.getElementById("pdf-canvas");

const ctx=canvas.getContext("2d");

canvas.width=viewport.width;

canvas.height=viewport.height;

page.render({

canvasContext:ctx,

viewport:viewport

});

});

});

</script>

</body>

</html>
