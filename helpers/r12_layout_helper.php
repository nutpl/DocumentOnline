<?php

function r12LayoutConfig(): array
{
    return [
        'layer_offset_y' => 3.0,
        // Keep the entered text at the same physical 16 pt size in both
        // TCPDF and the browser preview (96 DPI = 21.333 CSS px).
        'font_pt' => 16.0,
        'font_px' => 21.333,
        'table_font_pt' => 16.0,
        'table_font_px' => 21.333,
        'font_weight' => 700,
        // TCPDF and CSS use different glyph origins at 16 pt. Keep separate
        // renderer corrections so both outputs align visually with the form.
        'pdf_text_offset_y_px' => -7.0,
        'preview_text_offset_y_px' => 0.0,
        'preview_table_offset_y_px' => -7.0,
        'date' => [
            'day' => [520, 180],
            'month' => [611, 180],
            'year' => [716, 180],
        ],
        'main_fields' => [
            'recipient' => [205, 207, 430, 30],
            'student_name' => [290, 277, 310, 30],
            'student_number' => [690, 277, 140, 30],
            'faculty' => [120, 337, 265, 30],
            'curriculum' => [405, 337, 250, 30],
            'telephone' => [700, 337, 130, 30],
        ],
        'prefix_strikes' => [
            'นาย' => [174, 291, 25, 2],
            'นาง' => [205, 291, 24, 2],
            'นางสาว' => [235, 291, 46, 2],
        ],
        'prefix_ranges_mm' => [
            'นาย' => [40.9, 46.9],
            'นาง' => [48.3, 53.8],
            'นางสาว' => [55.2, 66.1],
        ],
        'prefix_strike_y_mm' => 69.0,
        'education_x' => [
            'certificate' => 200,
            'diploma' => 312,
            'undergraduate' => 420,
            'master' => 567,
            'doctor' => 715,
        ],
        'education_y' => 310,
        'table_left_shift' => [
            'semester' => 10,
            'course_code' => 10,
            'course_name' => 10,
            'section' => 30,
            'old_group' => 40,
            'new_group' => 50,
        ],
        'table_y_shift' => 13,
        'signature' => [
            'ink' => [490, 710, 250, 28],
            'print' => [490, 735, 250, 25],
        ],
    ];
}
