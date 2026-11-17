<?php

require "../db.php";

$result=mysqli_query($conn,"
SELECT
dt.template_id,
dt.template_name,
dt.pdf_template,
dt.docx_template,
d.document_name
FROM document_template dt
LEFT JOIN document_type d
ON d.document_type_id=dt.document_type_id
ORDER BY dt.template_id DESC
");

?>

<!doctype html>

<html>

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260911-2">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>

<title>Templates</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-slate-100">

<div class="max-w-7xl mx-auto px-4 py-6 sm:py-10">

<div class="flex flex-wrap justify-between items-center gap-4 mb-8">

<h1 class="text-3xl font-bold">

Document Templates

</h1>

<a
href="upload_template.php"
class="bg-blue-600 text-white px-5 py-2 rounded-lg">

Upload Template

</a>

</div>

<div class="bg-white rounded-xl shadow overflow-x-auto">

<table class="w-full min-w-[720px]">

<thead class="bg-slate-200">

<tr>

<th class="p-4 text-left">

ID

</th>

<th>

Template

</th>

<th>

Type

</th>

<th>

PDF

</th>

<th>

DOCX

</th>

<th>

Action

</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr class="border-b">

<td class="p-4">

<?= $row['template_id'] ?>

</td>

<td>

<?= $row['template_name'] ?>

</td>

<td>

<?= $row['document_name'] ?>

</td>

<td>

<?= $row['pdf_template'] ?>

</td>

<td>

<?= $row['docx_template'] ?>

</td>

<td>

<div class="flex gap-2">

<a
href="template_designer.php?id=<?= $row['template_id']?>"
class="bg-green-500 text-white px-3 py-1 rounded">

Designer

</a>

<a
href="delete_template.php?id=<?= $row['template_id']?>"
class="bg-red-500 text-white px-3 py-1 rounded">

Delete

</a>

</div>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</body>

</html>
