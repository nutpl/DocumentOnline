<?php
require_once "../../middleware/auth.php";
include "../../layouts/header.php";
include "../../layouts/navbar.php";
?>

<link rel="stylesheet" href="style.css">

<div class="max-w-7xl mx-auto py-6">

    <div class="flex justify-between items-center mb-6">

        <h1 class="text-3xl font-bold">

            Template Editor

        </h1>

        <button
            id="saveBtn"
            class="bg-blue-600 text-white px-5 py-2 rounded-lg">

            บันทึกตำแหน่ง

        </button>

    </div>

    <div id="canvas">

        <img
            id="paper"
            src="../../templates/r01/preview.png">

    </div>

</div>

<script src="script.js"></script>

<?php
include "../../layouts/footer.php";
?>