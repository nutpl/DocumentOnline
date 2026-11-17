<?php

function r01LayoutConfig(): array
{
    return [
        'layer_offset_y' => 3.0,
        // 16 pt in PDF equals 21.333 CSS px at 96 DPI.
        'font_px' => 21.333,
        'font_pt' => 16.0,
        'printed_name_font_px' => 21.333,
        'printed_name_font_pt' => 16.0,
        // TCPDF and CSS use different vertical font origins. These offsets
        // compensate only the PDF renderer so its visible glyphs match the preview.
        'pdf_text_offset_y_px' => -7.0,
        'pdf_date_offset_y_px' => -8.333,
        'pdf_faculty_curriculum_offset_y_px' => -4.0,
        // Shared visual offset for both CSS preview and TCPDF. The PDF code
        // uses the same 3px overlay origin as the preview, so this value is
        // no longer compensated by the database field's 12px font size.
        'pdf_printed_name_offset_y_px' => -8.333,
        'fields' => [
            'subject' => [210, 224, 580, 30],
            'recipient' => [210, 264, 580, 30],
            'student_name' => [340, 312, 195, 30],
            'student_number' => [700, 312, 130, 30],
            'faculty' => [135, 397, 260, 30],
            'curriculum' => [450, 397, 220, 30],
            'telephone' => [745, 399, 85, 30],
            'request_detail' => [227, 456, 599, 27],
            'signature_name' => [515, 614, 200, 30],
        ],
        'date' => [
            'day' => [513, 182],
            'month' => [603, 182],
            'year' => [724, 182],
        ],
        'prefix_strikes' => [
            'นาย' => [188, 324, 23, 2],
            'นาง' => [217, 324, 25, 2],
            'นางสาว' => [247, 324, 41, 2],
        ],
        'education_ticks' => [
            'certificate' => [208, 361],
            'diploma' => [320, 361],
            'undergraduate' => [428, 357],
            'master' => [555, 361],
            'doctor' => [708, 361],
        ],
        'request_lines' => [
            [227, 456, 599, 27, 46],
            [85, 483, 740, 27, 62],
            [85, 510, 740, 27, 62],
            [85, 537, 740, 27, 62],
        ],
        'signature' => [
            'ink' => [515, 614, 200, 30],
            'print' => [495, 650, 240, 30],
        ],
    ];
}

function r01SplitTextByLimits(string $text, array $limits): array
{
    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lines = [];
    $offset = 0;
    foreach ($limits as $limit) {
        $lines[] = implode('', array_slice($characters, $offset, $limit));
        $offset += $limit;
    }
    return $lines;
}
