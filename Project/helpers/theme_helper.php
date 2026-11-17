<?php

function renderThemeAssets(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    ?>
    <link rel="stylesheet" href="/Project/assets/css/dark-mode.css?v=20261108-7">
    <script src="/Project/assets/js/theme-toggle.js?v=20261107-1" defer></script>
    <?php
}

function renderThemeToggle(): void
{
    ?>
    <button type="button" data-theme-toggle class="theme-toggle inline-flex shrink-0 items-center justify-center" title="เปลี่ยนเป็นโหมดมืด" aria-label="เปลี่ยนเป็นโหมดมืด" aria-pressed="false">
        <i class="fa-solid fa-moon" data-theme-icon></i>
        <span class="sr-only" data-theme-label>เปลี่ยนเป็นโหมดมืด</span>
    </button>
    <?php
}
