<?php

/**
 * Student PDF access follows the review result, not merely document ownership.
 * The internship application is the only exception because its generated PDF
 * must be printed and signed before the signed copy can be submitted for review.
 */
function studentCanDownloadDocument(
    string $documentCode,
    string $documentStatus,
    ?string $internshipStatus = null
): bool {
    $normalizedCode = str_replace('.', '', strtoupper(trim($documentCode)));

    if ($normalizedCode === 'INTERNSHIP_INFO') {
        return $documentStatus === 'approved' || $internshipStatus === 'waiting_signature';
    }

    return $documentStatus === 'approved';
}

function studentDocumentDownloadUrl(
    int $documentId,
    string $documentCode,
    string $documentStatus,
    ?string $internshipStatus = null
): string {
    $normalizedCode = str_replace('.', '', strtoupper(trim($documentCode)));
    if ($normalizedCode === 'INTERNSHIP_INFO' && $internshipStatus === 'waiting_signature' && $documentStatus !== 'approved') {
        return 'generate_internship_pdf.php';
    }

    return 'download_document.php?document=' . $documentId;
}
