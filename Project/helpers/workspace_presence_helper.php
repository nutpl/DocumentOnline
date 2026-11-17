<?php

require_once __DIR__ . '/csrf_helper.php';

function ensureWorkspacePresenceTable(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec('CREATE TABLE IF NOT EXISTS workspace_presence (
        page_key VARCHAR(120) NOT NULL,
        user_id INT NOT NULL,
        active_role VARCHAR(30) NOT NULL,
        last_seen_at DATETIME NOT NULL,
        PRIMARY KEY (page_key, user_id),
        KEY idx_workspace_presence_seen (last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $ready = true;
}

function touchWorkspacePresence(PDO $pdo, string $pageKey): array
{
    ensureWorkspacePresenceTable($pdo);
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId < 1) return [];
    $pageKey = mb_substr($pageKey, 0, 120);
    $role = mb_substr((string) ($_SESSION['role'] ?? ''), 0, 30);
    $touch = $pdo->prepare('INSERT INTO workspace_presence (page_key,user_id,active_role,last_seen_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE active_role=VALUES(active_role),last_seen_at=NOW()');
    $touch->execute([$pageKey, $userId, $role]);
    $others = $pdo->prepare('SELECT COALESCE(NULLIF(CONCAT(COALESCE(p.prefix_short,p.prefix_name,""),e.firstname," ",e.lastname),""),u.username) AS display_name,wp.active_role
        FROM workspace_presence wp
        JOIN users u ON u.user_id=wp.user_id
        LEFT JOIN employee e ON e.user_id=u.user_id
        LEFT JOIN prefixes p ON p.prefix_id=e.prefix_id
        WHERE wp.page_key=? AND wp.user_id<>? AND wp.last_seen_at>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)
        ORDER BY wp.last_seen_at DESC');
    $others->execute([$pageKey, $userId]);
    return $others->fetchAll(PDO::FETCH_ASSOC);
}

function renderWorkspacePresenceNotice(PDO $pdo, string $pageKey): void
{
    try {
        $others = touchWorkspacePresence($pdo, $pageKey);
    } catch (Throwable $e) {
        error_log('Workspace presence failed: ' . $e->getMessage());
        return;
    }
    $names = array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string) ($row['display_name'] ?? '')), $others))));
    $encodedPage = json_encode($pageKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encodedNames = json_encode($names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $token = json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (()=>{const page=<?= $encodedPage ?>,names=<?= $encodedNames ?>,token=<?= $token ?>;
      const key='workspace-presence-alert:'+page+':'+names.join('|');
      if(names.length&&window.Swal&&!sessionStorage.getItem(key)){sessionStorage.setItem(key,'1');Swal.fire({icon:'info',title:'มีผู้ใช้งานร่วมในหน้านี้',html:'ขณะนี้ <b>'+names.map(n=>String(n).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))).join(', ')+'</b> กำลังเปิดหน้านี้อยู่<br><span style="font-size:.9em;color:#64748b">โปรดตรวจสอบข้อมูลล่าสุดก่อนบันทึก</span>',confirmButtonText:'รับทราบ',confirmButtonColor:'#dc2626'});}
      setInterval(()=>fetch('/Project/assistant/workspace_presence.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({page_key:page,csrf_token:token})}).catch(()=>{}),60000);
    })();
    </script>
    <?php
}
