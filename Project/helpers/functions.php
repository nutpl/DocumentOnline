<?php

function redirect($url)
{
    header("Location: $url");
    exit;
}

function e($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function old($key)
{
    return $_POST[$key] ?? "";
}