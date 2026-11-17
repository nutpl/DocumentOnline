<?php

require_once "../../middleware/auth.php";

$fields = require "../../templates/r01/fields.php";

include "../../layouts/header.php";
include "../../layouts/navbar.php";
?>

<link rel="stylesheet" href="style.css">

<div class="max-w-7xl mx-auto py-6">

    <h1 class="text-3xl font-bold mb-6">
        R.01 คำร้องทั่วไป
    </h1>

    <div class="bg-white rounded-xl shadow-lg p-8 overflow-x-auto">

        <div id="document">

            <img src="../../templates/r01/preview.png" id="paper">

            <?php foreach ($fields as $field): ?>

                <?php if ($field['type'] === 'textarea'): ?>

                    <textarea
                        class="input-field"
                        name="<?= htmlspecialchars($field['name']) ?>"
                        style="
                            left:<?= $field['left'] ?>px;
                            top:<?= $field['top'] ?>px;
                            width:<?= $field['width'] ?>px;
                            height:<?= $field['height'] ?>px;
                        "
                    ></textarea>

                <?php else: ?>

                    <input
                        type="text"
                        class="input-field"
                        name="<?= htmlspecialchars($field['name']) ?>"
                        style="
                            left:<?= $field['left'] ?>px;
                            top:<?= $field['top'] ?>px;
                            width:<?= $field['width'] ?>px;
                        "
                    >

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<?php include "../../layouts/footer.php"; ?>