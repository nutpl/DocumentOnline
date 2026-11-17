<?php

require_once __DIR__ . '/csrf_helper.php';

function renderRoleSwitcher(): void
{
    $roles = array_values(array_unique(array_filter($_SESSION['roles'] ?? [], static fn($role): bool => is_string($role))));
    if (count($roles) < 2) return;
    $labels = ['admin' => 'ผู้ดูแลระบบ', 'staff' => 'เจ้าหน้าที่', 'teacher' => 'อาจารย์', 'student' => 'นักศึกษา'];
    $activeRole = (string) ($_SESSION['role'] ?? '');
    ?>
    <details class="role-switcher shrink-0" title="สลับบทบาทการใช้งาน">
        <summary class="role-switcher__summary">
            <span class="role-switcher__icon" aria-hidden="true"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
            <span class="role-switcher__current"><?= htmlspecialchars($labels[$activeRole] ?? $activeRole, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="fa-solid fa-chevron-down role-switcher__chevron" aria-hidden="true"></i>
        </summary>
        <div class="role-switcher__menu" role="menu" aria-label="เลือกบทบาทการใช้งาน">
            <p class="role-switcher__menu-title">สลับบทบาทการใช้งาน</p>
            <?php foreach ($roles as $role): ?>
                <form method="post" action="/Project/auth/switch_role.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="role-switcher__option <?= $role === $activeRole ? 'is-active' : '' ?>" role="menuitem">
                        <span class="role-switcher__option-icon"><i class="fa-solid <?= $role === 'admin' ? 'fa-user-shield' : ($role === 'staff' ? 'fa-user-gear' : ($role === 'teacher' ? 'fa-chalkboard-user' : 'fa-user-graduate')) ?>"></i></span>
                        <span><?= htmlspecialchars($labels[$role] ?? $role, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($role === $activeRole): ?><i class="fa-solid fa-check ml-auto text-xs"></i><?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
}
