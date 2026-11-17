"""Build Linux-only copies of the approved official-letter templates.

The Windows originals intentionally remain untouched.  LibreOffice on the
Linux server does not include TH SarabunIT๙, so it substitutes a different
font and changes the page flow.  The server copies use the project-bundled
TH SarabunPSK font instead.
"""

from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


ROOT = Path(__file__).resolve().parents[1]
TEMPLATE_ROOT = ROOT / "templates" / "official_internship"
OUTPUT_ROOT = TEMPLATE_ROOT / "libreoffice"
FILENAMES = ("send_letter_template.docx", "request_letter_template.docx")
FONT_NAMES = ("TH SarabunIT๙", "TH SarabunIT9")
# TH SarabunPSK has a larger visual footprint than the font in the approved
# Windows template.  LibreOffice uses the Normal style for most letter text,
# so reduce only its 16 pt style definition to 14 pt in Linux-only copies.
# The Windows originals remain untouched.
LIBREOFFICE_STYLE_REPLACEMENTS = (
    ('<w:sz w:val="32"/>', '<w:sz w:val="28"/>'),
    ('<w:szCs w:val="32"/>', '<w:szCs w:val="28"/>'),
)


def patch_xml(xml: bytes) -> bytes:
    text = xml.decode("utf-8")
    for font in FONT_NAMES:
        text = text.replace(font, "TH SarabunPSK")
    for original, replacement in LIBREOFFICE_STYLE_REPLACEMENTS:
        text = text.replace(original, replacement)
    return text.encode("utf-8")


def build_template(filename: str) -> None:
    source = TEMPLATE_ROOT / filename
    destination = OUTPUT_ROOT / filename
    if not source.is_file():
        raise FileNotFoundError(source)

    OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)
    with ZipFile(source, "r") as input_docx, ZipFile(destination, "w", ZIP_DEFLATED) as output_docx:
        for entry in input_docx.infolist():
            content = input_docx.read(entry.filename)
            if entry.filename in {"word/document.xml", "word/styles.xml", "word/fontTable.xml"}:
                content = patch_xml(content)
            output_docx.writestr(entry, content)


if __name__ == "__main__":
    for filename in FILENAMES:
        build_template(filename)
        print(f"Built LibreOffice template: {filename}")
