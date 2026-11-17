from pathlib import Path

import pdfplumber

files = {
    "word": Path(r"C:\Users\mewww\OneDrive\เดสก์ท็อป\เอกสารProject\ส่งตัว.pdf"),
    "printed": Path("output/pdf/official-send-word-position-test.pdf"),
}
needles = ("เรื่อง", "ตามหนังสือ", "จึงเรียน", "ขอแสดง", "รองศาสตราจารย์", "QR-Code")

for label, path in files.items():
    print(label)
    with pdfplumber.open(path) as pdf:
        for word in pdf.pages[0].extract_words():
            if any(needle in word["text"] for needle in needles):
                print(
                    f'{word["text"]}: '
                    f'x0={word["x0"]:.2f}, x1={word["x1"]:.2f}, '
                    f'top={word["top"]:.2f}, bottom={word["bottom"]:.2f}'
                )
