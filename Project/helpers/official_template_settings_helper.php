<?php
declare(strict_types=1);

/** Values editable by staff; document geometry and merge fields remain fixed. */
function officialTemplateSettingDefaults(): array
{
    return [
        'footer_contact' => 'แผนกงานวิชาการและวิจัย โทรศัพท์ ๐ ๔๔๒๓ ๓๐๐๐ ต่อ ๓๑๙๔ อีเมล FET@rmuti.ac.th',
        'assessment_qr_caption' => "QR-Code\nสำหรับเข้าระบบประเมินผล\nการปฏิบัติงานนักศึกษา\nhttps://cwie.rmuti.ac.th/login",
        'steps_qr_caption' => "QR-Code\nขั้นตอนการประเมินผล\nการปฏิบัติงานนักศึกษา\nhttps://shorturl.asia/JYz5h",
        'assessment_qr_image' => '',
        'steps_qr_image' => '',
    ];
}

function officialTemplateSettings(PDO $pdo, int $departmentId): array
{
    $settings = officialTemplateSettingDefaults();
    if ($departmentId < 1) return $settings;
    try {
        $statement = $pdo->prepare('SELECT setting_key, setting_value FROM internship_official_template_setting WHERE department_id=?');
        $statement->execute([$departmentId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)$row['setting_key'];
            if (array_key_exists($key, $settings) && trim((string)$row['setting_value']) !== '') {
                $settings[$key] = trim((string)$row['setting_value']);
            }
        }
    } catch (PDOException) {
        // The migration may not yet be installed. Keep the approved defaults.
    }
    return $settings;
}

function officialTemplateApplySettings(PDO $pdo, int $departmentId, array $document): array
{
    $settings = officialTemplateSettings($pdo, $departmentId);
    $document['_template_footer_contact'] = $settings['footer_contact'];
    $document['_template_assessment_qr_caption'] = $settings['assessment_qr_caption'];
    $document['_template_steps_qr_caption'] = $settings['steps_qr_caption'];
    $document['_template_assessment_qr_image'] = $settings['assessment_qr_image'];
    $document['_template_steps_qr_image'] = $settings['steps_qr_image'];
    return $document;
}
