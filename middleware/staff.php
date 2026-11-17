<?php

require_once __DIR__ . "/auth.php";

if ($_SESSION['role'] != "staff") {

    header("Location: /Project/index.php");

    exit;

}