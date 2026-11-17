# Official send letter templates

Reference sources:

- `C:\DocumentProject1_69\ขอส่งตัว 1 คน การไฟฟ้า.docx`
  SHA-256 `EC07C4D1B43A433BF0C8179F8CE7AE86B41ADE4F4F53B6CD666F701C181DBA7E`
- `C:\DocumentProject1_69\บ.ไตรภัทร 3คน.docx`
  SHA-256 `4A707D6FD8AF1458611FD1254EC5C330B871DCD08314CAE9F3D42EA1AC47A00B`

Both references are single-page, portrait A4 letters. Section audit records
left/right/top/bottom margins of 1.18/0.79/0.10/0.00 inches (3/2/0.25/0 cm).
The retained sources are authoritative for their respective one-student and
multi-student layouts, including header table, direct paragraph formatting,
signature block, paired QR VML group, and contact line.

Slot map:

- Main table: outgoing number, faculty address, and Garuda are preserved.
- Body: date, subject, recipient, reference, first narrative, numbered student
  slots, second narrative, and signature text are replaced in place.
- QR/footer: preserve existing two-QR VML group and source contact placement
  for Word downloads.

Fidelity gates:

- One student selects `send_letter_template.docx`.
- Two or more students select `send_letter_multi_template.docx`.
- Two/three student list slots retain source paragraph geometry; four/five
  students clone only the documented name-slot pattern and compact list
  leading to retain a one-page document.
- The local DOCX renderer could not run because its bundled LibreOffice binary
  is unavailable; visual reference was checked against each supplied Word PDF.
