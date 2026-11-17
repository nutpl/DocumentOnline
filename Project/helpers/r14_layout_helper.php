<?php

function r14LayoutConfig(): array
{
    return [
        // Match the 16 pt text standard used by the other R documents.
        'font_pt' => 16.0,
        'font_px' => 21.333,
        'signature_font_pt' => 16.0,
        'signature_font_px' => 21.333,
        'signature_padding_px' => 8.0,
        'signature_padding_mm' => 2.0,
        'pdf_text_offset_y_px' => -4.0,
        'preview_text_offset_y_px' => 0.0,
        'date' => [
            'day' => [542.0, 165.0],
            'month' => [660.0, 165.0],
            'year' => [798.0, 165.0],
        ],
        'prefix_strikes' => [
            // Coordinates follow the printed นาย/นาง/นางสาว words in the
            // official R.14 background at the 1.5x PDF.js preview scale.
            'นาย' => [184.5, 299.0, 25.0, 2.0],
            'นาง' => [215.5, 299.0, 23.5, 2.0],
            'นางสาว' => [244.8, 299.0, 46.5, 2.0],
        ],
        'prefix_ranges_mm' => [
            'นาย' => [43.4, 49.3],
            'นาง' => [50.7, 56.2],
            'นางสาว' => [57.6, 68.5],
        ],
        'prefix_strike_y_mm' => 71.1,
        'education_x' => [
            'certificate' => 200.0,
            'diploma' => 310.0,
            'undergraduate' => 427.0,
            'master' => 573.0,
            'doctor' => 720.0,
        ],
        'education_y' => 323.0,
        'request_type' => [
            'less' => [238.0, 432.0],
            'more' => [238.0, 472.0],
        ],
        // The upper signature line contains the student's name without a
        // prefix; the parenthesized line contains the formal prefixed name.
        // Browser text uses a top edge while TCPDF positions from its text
        // baseline, so each renderer needs a small vertical correction.
        'signature_preview_ink' => [539.0, 544.0, 210.0, 24.0],
        'signature_pdf_ink' => [539.0, 540.0, 210.0, 24.0],
        'signature_preview_print' => [539.0, 573.0, 210.0, 24.0],
        'signature_pdf_print' => [539.0, 574.0, 210.0, 24.0],
    ];
}
