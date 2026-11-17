# PDF บน server ด้วย LibreOffice

เอกสาร Word และ PDF ใช้แม่แบบ Word ชุดเดียวกันใน `templates/official_internship/` เสมอ
LibreOffice มีหน้าที่แปลง DOCX เป็น PDF บน Docker เท่านั้น และไม่แก้ไขแม่แบบ Word

ไม่ต้องกำหนดค่า Word worker หรือ token ใน `docker-compose.yml` อีกต่อไป
Docker จะใช้ LibreOffice โดยอัตโนมัติ ส่วน XAMPP บน Windows ยังคงใช้ Microsoft Word
บนเครื่องนั้นโดยตรง

Docker image ต้องมี LibreOffice Writer และฟอนต์ `TH Sarabun PSK` แล้วจึงสร้างใหม่:

```bash
docker compose up -d --build --force-recreate web
docker compose exec web libreoffice --version
docker compose exec web fc-match "TH Sarabun PSK"
```

หลัง deploy ให้ทดสอบดาวน์โหลด Word และ PDF ของหนังสือขอความอนุเคราะห์และหนังสือส่งตัว
โดยเลือกกรณีมีรายชื่อ 5 คนด้วย หาก PDF ต่างจากแบบ Word ให้เก็บไฟล์ PDF ที่ได้จาก server
เพื่อปรับการตั้งค่า LibreOffice โดยไม่แก้แม่แบบ Word หลัก
