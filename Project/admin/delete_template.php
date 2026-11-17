<?php

require "../db.php";

$id=(int)$_GET['id'];

mysqli_query($conn,"
DELETE FROM document_template
WHERE template_id=$id
");

header("Location: templates.php");