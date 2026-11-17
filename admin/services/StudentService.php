<?php

class StudentService{

    public static function getStudent($pdo,$user_id){

        $sql="

        SELECT

        s.*,

        g.group_name,

        d.department_name

        FROM student s

        LEFT JOIN group_class g

        ON s.group_id=g.group_id

        LEFT JOIN departments d

        ON s.department_id=d.department_id

        WHERE s.user_id=?

        ";

        $stmt=$pdo->prepare($sql);

        $stmt->execute([$user_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);

    }

}