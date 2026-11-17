# Official internship letters layout contract

## Design authorities retained unchanged

1. `C:\Users\mewww\Downloads\ขอส่งตัวนักศึกษาฝึกประสบการณ์วิชาชีพ (5).docx`
   - SHA-256: `014FA9CBB1B997ED1C78C6477009BDB7081F5A71366E52E34573540E108477AC`
   - One portrait A4 page; Word rendering stored in `word/` and reviewed at 160 dpi.
   - Page margins: left 1.18 in, right 0.79 in, top 0.10 in, bottom 0.16 in.
   - Fixed elements: Garuda/header at the top; letter number on the left; faculty address at upper-right; QR block at lower-left; contact line at bottom; signature block in lower-right.
   - Variable slots: letter number, date, recipient, reference text, attachment, internship details, student names, and signatory details.

2. `C:\Users\mewww\Downloads\ขอความอนุเคราะห์รับนักศึกษาฝึกประสบการณ์วิชาชีพ (1).docx`
   - SHA-256: `833E025C80B702C9F10616B84596F6BC03017E5D3B98A5F5DC7E728247264359`
   - One portrait A4 page; Word rendering stored in `word/` and reviewed at 160 dpi.
   - Page margins: left 1.18 in, right 0.79 in, top 0.79 in, bottom 0.20 in.
   - Fixed elements: Garuda/header at the top; letter number on the left; faculty address at upper-right; signature block in lower-right; contact block at lower-left.
   - Variable slots: letter number, date, recipient, attachment quantities, internship dates/details, student names, response deadline, and signatory details.

## Fidelity rules

- Existing DOCX templates in `templates/official_internship/` remain untouched.
- The PHP PDF renderer may only adjust placement, line flow, and static source selection so that its request and send PDFs match the existing official Word layout.
- Both output types must remain one A4 page for the ordinary one-student case, preserve TH Sarabun typography, and keep all fixed letterhead, QR, signature, and contact components in their respective Word-template regions.
