from pathlib import Path

import pdfplumber
from PIL import Image
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "uploads" / "pdf" / "RMUTI_Pro-Ex_02.pdf"
PNG = ROOT / "tmp" / "pdfs" / "rmuti-pro-ex-02-clean.png"
OUTPUT = ROOT / "uploads" / "pdf" / "RMUTI_Pro-Ex_02-clean.pdf"

PNG.parent.mkdir(parents=True, exist_ok=True)
with pdfplumber.open(SOURCE) as pdf:
    image = pdf.pages[0].to_image(resolution=300, antialias=True).original.convert("RGB")

pixels = image.load()
start_y = int(image.height * 0.12)
for y in range(start_y, image.height):
    for x in range(image.width):
        red, green, blue = pixels[x, y]
        # Remove blue handwriting while retaining black print, rules and the colored crest.
        if blue > red + 8 and blue > green + 4 and blue > 90:
            pixels[x, y] = (255, 255, 255)

image.save(PNG, "PNG", optimize=True)
page_width, page_height = A4
pdf = canvas.Canvas(str(OUTPUT), pagesize=A4, pageCompression=1)
pdf.drawImage(str(PNG), 0, 0, width=page_width, height=page_height, preserveAspectRatio=False, mask="auto")
pdf.showPage()
pdf.save()
