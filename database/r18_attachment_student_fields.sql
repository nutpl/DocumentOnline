SET @r18_template_id := (
    SELECT t.template_id
    FROM document_template t
    JOIN document_type d ON d.document_type_id=t.document_type_id
    WHERE REPLACE(d.document_code,'.','')='R18'
    ORDER BY t.template_id DESC LIMIT 1
);

DELIMITER //
DROP PROCEDURE IF EXISTS seed_r18_attachment_students//
CREATE PROCEDURE seed_r18_attachment_students()
BEGIN
    DECLARE row_no INT DEFAULT 1;
    WHILE row_no <= 25 DO
        DELETE FROM template_fields
        WHERE template_id=@r18_template_id AND field_name IN (
            CONCAT('attachment_student_number_',row_no), CONCAT('attachment_student_name_',row_no),
            CONCAT('attachment_group_',row_no), CONCAT('attachment_gpa_',row_no),
            CONCAT('attachment_expected_semester_',row_no)
        );
        INSERT INTO template_fields
            (template_id,field_name,field_label,field_type,page_no,pos_x,pos_y,width,height,font_size,required_field,default_value)
        VALUES
            (@r18_template_id,CONCAT('attachment_student_number_',row_no),CONCAT('รหัสนักศึกษา คนที่ ',row_no),'text',3,90,151+(row_no-1)*32,158,26,10,0,NULL),
            (@r18_template_id,CONCAT('attachment_student_name_',row_no),CONCAT('ชื่อ - สกุล คนที่ ',row_no),'text',3,263,151+(row_no-1)*32,260,26,10,0,NULL),
            (@r18_template_id,CONCAT('attachment_group_',row_no),CONCAT('กลุ่มเรียน คนที่ ',row_no),'text',3,540,151+(row_no-1)*32,100,26,10,0,NULL),
            (@r18_template_id,CONCAT('attachment_gpa_',row_no),CONCAT('เกรดเฉลี่ย คนที่ ',row_no),'text',3,657,151+(row_no-1)*32,58,26,10,0,NULL),
            (@r18_template_id,CONCAT('attachment_expected_semester_',row_no),CONCAT('ภาคการศึกษาที่คาดว่าจะสำเร็จ คนที่ ',row_no),'text',3,732,151+(row_no-1)*32,120,26,10,0,NULL);
        SET row_no = row_no + 1;
    END WHILE;
END//
CALL seed_r18_attachment_students()//
DROP PROCEDURE seed_r18_attachment_students//
DELIMITER ;
