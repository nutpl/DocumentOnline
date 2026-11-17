<?php

function r16LayoutConfig(): array
{
    return [
        'font_pt' => 16.0,
        'font_px' => 21.333,
        'table_font_pt' => 16.0,
        'table_font_px' => 21.333,
        'font_weight' => 700,
        // TCPDF and CSS use different glyph origins at 16 pt.
        'pdf_text_offset_y_px' => -7.0,
        'preview_text_offset_y_px' => 0.0,
        'preview_table_offset_y_px' => -7.0,
        'preview_reason_offset_y_px' => -3.0,
        'date' => [
            'day' => [560.0, 148.0],
            'month' => [645.0, 148.0],
            'year' => [754.0, 148.0],
        ],
        // Printable area inside the template's lower parentheses.
        'signature_print' => [596.0, 772.0, 160.0, 24.0],
        'signature_min_font_pt' => 10.0,
        'signature_min_font_px' => 13.333,
    ];
}
