<?php

require_once __DIR__ . "/auth.php";

if ($_SESSION['role'] != "teacher") {

    header("Location: /Project/index.php");

    exit;

}