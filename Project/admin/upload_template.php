<?php

require "../db.php";
require "../config.php";

$message="";

if(isset($_POST['upload'])){

    $template_name=mysqli_real_escape_string(
        $conn,
        $_POST['template_name']
    );

    $template_type=$_POST['template_type'];

    if(!empty($_FILES['template_file']['name'])){

        $extension=strtolower(
            pathinfo(
                $_FILES['template_file']['name'],
                PATHINFO_EXTENSION
            )
        );

        if(
            $template_type=="pdf"
            &&
            $extension!="pdf"
        ){

            $message="กรุณาอัปโหลด PDF";

        }else{

            if(
                $template_type=="docx"
                &&
                $extension!="docx"
            ){

                $message="กรุณาอัปโหลด DOCX";

            }else{

                $newName=time()."_".$_FILES['template_file']['name'];

                if($template_type=="pdf"){

                    $uploadPath="../uploads/pdf/".$newName;

                }else{

                    $uploadPath="../uploads/docx/".$newName;

                }

                if(move_uploaded_file(
                    $_FILES['template_file']['tmp_name'],
                    $uploadPath
                )){

                    $sql="INSERT INTO document_template
                    (
                        template_name,
                        template_type,
                        file_name,
                        file_path
                    )
                    VALUES
                    (
                        '$template_name',
                        '$template_type',
                        '$newName',
                        '$uploadPath'
                    )";

                    mysqli_query($conn,$sql);

                    $message="อัปโหลดสำเร็จ";

                }else{

                    $message="Upload Error";

                }

            }

        }

    }

}

?>
<!DOCTYPE html>

<html>

<head>
    <link rel="icon" type="image/png" href="/Project/assets/images/fet-logo-transparent.png?v=20260815-3">


<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/Project/assets/css/responsive.css?v=20260911-2">
<script src="/Project/assets/js/responsive-tables.js?v=20260901-1" defer></script>

<style>
body{margin:0;padding:clamp(1rem,4vw,2.5rem);background:#f1f5f9;font-family:"TH Sarabun PSK",Tahoma,sans-serif;color:#0f172a}
form{width:min(100%,36rem);box-sizing:border-box;padding:clamp(1rem,4vw,2rem);border-radius:1rem;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.08)}
input,select{width:100%;box-sizing:border-box;margin-top:.4rem;padding:.75rem;border:1px solid #cbd5e1;border-radius:.6rem;background:#fff}
button,input[type=submit]{width:auto;background:#dc2626;color:#fff;border:0;font-weight:700;cursor:pointer}
</style>

<title>Upload Template</title>

</head>

<body>

<h2>Upload Document Template</h2>

<form
method="post"
enctype="multipart/form-data">

ชื่อ Template

<br>

<input
type="text"
name="template_name"
required>

<br><br>

ประเภท

<br>

<select
name="template_type">

<option value="pdf">

PDF

</option>

<option value="docx">

DOCX

</option>

</select>

<br><br>

เลือกไฟล์

<br>

<input
type="file"
name="template_file"
required>

<br><br>

<button
name="upload">

Upload

</button>

</form>

<br>

<?php

echo $message;

?>

</body>

</html>
