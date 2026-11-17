<?php
declare(strict_types=1);

function responseNoDocumentReviewAvailable(PDO $pdo): bool
{
    static $available = null;
    if ($available === null) {
        $statement = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='internship_response_no_document_review'");
        $available = (bool)$statement->fetchColumn();
    }
    return $available;
}

function responseNoDocumentApprovedSql(PDO $pdo, string $profileId = 'ip.internship_profile_id'): string
{
    if (!responseNoDocumentReviewAvailable($pdo)) return '0=1';
    return 'EXISTS (SELECT 1 FROM internship_response_declaration rdecl
        JOIN internship_response_no_document_review ndr ON ndr.internship_profile_id=rdecl.internship_profile_id
        WHERE rdecl.internship_profile_id='.$profileId.'
          AND rdecl.evidence_state="no_document" AND rdecl.reported_result="accepted"
          AND ndr.review_status="approved" AND ndr.declaration_updated_at=rdecl.updated_at)';
}

function responseVerifiedForSendSql(PDO $pdo, string $profileId = 'ip.internship_profile_id'): string
{
    return '(EXISTS (SELECT 1 FROM internship_response_document rd WHERE rd.internship_profile_id='.$profileId.' AND rd.review_status="approved") OR '.responseNoDocumentApprovedSql($pdo, $profileId).')';
}
