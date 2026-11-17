<?php

/**
 * Returns the person whose name must appear on newly created internship
 * official letters. The vice dean is the normal acting signatory; the dean
 * is used as a fallback when no current vice dean has been configured.
 *
 * The returned values are copied into internship_official_document so an
 * existing letter never changes when Admin assigns a new office holder.
 */
function currentInternshipSignatory(PDO $pdo): ?array
{
    $statement = $pdo->query(
        "SELECT oh.office_holder_id, p.position_name,
                oh.signature_title_line1, oh.signature_title_line2,
                oh.holder_name
         FROM office_holders oh
         INNER JOIN positions p ON p.position_id = oh.position_id
         WHERE oh.is_active = 1
           AND oh.effective_from <= CURRENT_DATE
           AND (oh.effective_to IS NULL OR oh.effective_to >= CURRENT_DATE)
           AND p.position_name IN ('รองคณบดีฝ่ายวิชาการและวิจัย', 'คณบดี')
         ORDER BY FIELD(p.position_name, 'รองคณบดีฝ่ายวิชาการและวิจัย', 'คณบดี'),
                  oh.effective_from DESC, oh.office_holder_id DESC
         LIMIT 1"
    );
    $holder = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$holder) {
        return null;
    }

    $name = trim((string) ($holder['holder_name'] ?? ''));

    return [
        'office_holder_id' => (int) $holder['office_holder_id'],
        'position_name' => (string) $holder['position_name'],
        'name' => $name,
        'title_line1' => (string) ($holder['signature_title_line1'] ?? ''),
        'title_line2' => (string) ($holder['signature_title_line2'] ?? ''),
    ];
}
