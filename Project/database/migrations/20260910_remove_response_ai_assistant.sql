-- Removes stored OCR text and all AI preliminary-review records.
-- Run once only on databases that previously installed the AI assistant.

DROP TABLE IF EXISTS internship_response_ai_check;
