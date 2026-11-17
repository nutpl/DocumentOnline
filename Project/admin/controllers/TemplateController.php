<?php

require_once "../../config/database.php";

class TemplateController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // แสดงรายการ Template
    public function getTemplates($keyword = "")
    {

        $sql = "
        SELECT
            dt.template_id,
            dt.template_name,
            dt.pdf_template,
            dt.docx_template,
            dt.status,
            dt.version,
            dt.imported_at,

            d.document_code,
            d.document_name

        FROM document_template dt

        LEFT JOIN document_type d
            ON dt.document_type_id=d.document_type_id
        ";

        if ($keyword != "") {

            $sql .= "
            WHERE
                d.document_code LIKE :keyword
                OR d.document_name LIKE :keyword
                OR dt.template_name LIKE :keyword
            ";
        }

        $sql .= "
        ORDER BY d.document_code ASC
        ";

        $stmt = $this->pdo->prepare($sql);

        if ($keyword != "") {

            $stmt->bindValue(
                ":keyword",
                "%".$keyword."%"
            );

        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

}