-- Rename historic notification text shown to users. Table and file names stay
-- unchanged so existing uploads and workflow references remain valid.
UPDATE notifications
SET title = REPLACE(title, 'หนังสือตอบกลับ', 'หนังสือตอบรับ'),
    message = REPLACE(message, 'หนังสือตอบกลับ', 'หนังสือตอบรับ')
WHERE title LIKE '%หนังสือตอบกลับ%'
   OR message LIKE '%หนังสือตอบกลับ%';
