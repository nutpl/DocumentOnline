<?php
declare(strict_types=1);
require_once __DIR__.'/response_no_document_review_helper.php';
function officialDirectSendAvailable(PDO $pdo): bool
{
    static $available;
    if ($available===null) {
        $query=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='internship_direct_send_import'");
        $available=(bool)$query->fetchColumn();
    }
    return $available;
}
function officialDirectSendSql(PDO $pdo): string
{
    return officialDirectSendAvailable($pdo)
        ? 'EXISTS (SELECT 1 FROM internship_direct_send_import imp WHERE imp.internship_profile_id=ip.internship_profile_id)'
        : '0=1';
}
function officialSendReadySql(PDO $pdo): string
{
    return '('.officialDirectSendSql($pdo).' OR (
        EXISTS (SELECT 1 FROM internship_application a WHERE a.internship_profile_id=ip.internship_profile_id AND a.status="submitted")
        AND EXISTS (SELECT 1 FROM internship_tracking t WHERE t.internship_profile_id=ip.internship_profile_id AND t.application_status="applied")
        AND EXISTS (SELECT 1 FROM internship_official_student os JOIN internship_official_document od ON od.official_document_id=os.official_document_id WHERE os.internship_profile_id=ip.internship_profile_id AND od.document_type="request")
        AND '.responseVerifiedForSendSql($pdo).'
    ))';
}
