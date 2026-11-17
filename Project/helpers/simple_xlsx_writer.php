<?php

final class SimpleXlsxWriter
{
    public static function save(array $sheets, string $path): void
    {
        $directory=dirname($path);if(!is_dir($directory)){throw new RuntimeException('ไม่พบโฟลเดอร์ไฟล์ Excel กลาง: '.$directory);}
        $tmp=tempnam(sys_get_temp_dir(),'xlsx_');$zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::OVERWRITE)!==true){throw new RuntimeException('ไม่สามารถสร้างไฟล์ Excel');}
        $zip->addFromString('[Content_Types].xml',self::contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/package/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอพพลิเคชั่น</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอพพลิเคชั่น</Application></Properties>');$zip->addFromString('xl/styles.xml',self::styles());
        $sheetXml=[];$rels=[];foreach(array_values($sheets) as $i=>$sheet){$n=$i+1;$sheetXml[]='<sheet name="'.self::xml($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';$rels[]='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';$zip->addFromString('xl/worksheets/sheet'.$n.'.xml',self::worksheet($sheet));}
        $styleRel=count($sheets)+1;$rels[]='<Relationship Id="rId'.$styleRel.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.implode('',$sheetXml).'</sheets></workbook>');$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('',$rels).'</Relationships>');$zip->close();
        $staging=$path.'.tmp';if(!copy($tmp,$staging)){@unlink($tmp);throw new RuntimeException('ไม่สามารถบันทึกไฟล์ Excel กลาง');}@unlink($tmp);if(file_exists($path)&&!@unlink($path)){@unlink($staging);throw new RuntimeException('ไฟล์ Excel กลางกำลังถูกใช้งาน กรุณาปิดไฟล์แล้วลองใหม่');}if(!@rename($staging,$path)){@unlink($staging);throw new RuntimeException('ไม่สามารถแทนที่ไฟล์ Excel กลาง');}
    }
    public static function output(array $sheets, string $filename): never
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('ไม่สามารถสร้างไฟล์ Excel'); }
        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอพพลิเคชั่น</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ระบบจัดการเอกสารคำร้องของคณะผ่านเว็บแอพพลิเคชั่น</Application></Properties>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $sheetXml = []; $rels = [];
        foreach (array_values($sheets) as $i => $sheet) {
            $n=$i+1; $name=self::xml($sheet['name']); $sheetXml[]='<sheet name="'.$name.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels[]='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', self::worksheet($sheet));
        }
        $styleRel=count($sheets)+1; $rels[]='<Relationship Id="rId'.$styleRel.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.implode('',$sheetXml).'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('',$rels).'</Relationships>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Content-Length: '.filesize($tmp));
        readfile($tmp); unlink($tmp); exit;
    }

    private static function worksheet(array $sheet): string
    {
        $rows=$sheet['rows'];$widths=$sheet['widths']??[];
        $metadataRows=array_map('intval',$sheet['metadata_rows']??[]);
        $headerRows=array_map('intval',$sheet['header_rows']??[1]);
        $plainRows=array_map('intval',$sheet['plain_rows']??[]);
        $dataStyle=(int)($sheet['data_style']??2);
        $freezeRows=(int)($sheet['freeze_rows']??1);
        $cols=''; foreach($widths as $i=>$width){$n=$i+1;$cols.='<col min="'.$n.'" max="'.$n.'" width="'.(float)$width.'" customWidth="1"/>';}
        $xml=[]; foreach($rows as $r=>$row){$rowNo=$r+1;$cells=[];$style=in_array($rowNo,$plainRows,true)?0:(in_array($rowNo,$metadataRows,true)?3:(in_array($rowNo,$headerRows,true)?4:$dataStyle));foreach(array_values($row) as $c=>$value){$ref=self::column($c+1).$rowNo;$cells[]='<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.self::xml((string)($value??'')).'</t></is></c>';}$height=in_array($rowNo,$headerRows,true)?' ht="26" customHeight="1"':(in_array($rowNo,$metadataRows,true)?' ht="22" customHeight="1"':'');$xml[]='<row r="'.$rowNo.'"'.$height.'>'.implode('',$cells).'</row>';}
        $mergeXml='';$merges=$sheet['merges']??[];if($merges){$mergeXml='<mergeCells count="'.count($merges).'">';foreach($merges as $merge){$mergeXml.='<mergeCell ref="'.self::xml($merge).'"/>';}$mergeXml.='</mergeCells>';}
        $pane=$freezeRows>0?'<pane ySplit="'.$freezeRows.'" topLeftCell="A'.($freezeRows+1).'" activePane="bottomLeft" state="frozen"/>':'';
        $filter='';if(!empty($sheet['auto_filter'])){$filter='<autoFilter ref="'.self::xml($sheet['auto_filter']).'"/>';}
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0">'.$pane.'</sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.implode('',$xml).'</sheetData>'.$mergeXml.$filter.'</worksheet>';
    }
    private static function styles(): string { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="TH SarabunPSK"/></font><font><b/><sz val="10"/><name val="TH SarabunPSK"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>'; }
    private static function contentTypes(int $count): string { $parts=[];for($i=1;$i<=$count;$i++)$parts[]='<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.implode('',$parts).'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'; }
    private static function column(int $n): string { $s='';while($n>0){$n--; $s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s; }
    private static function xml(string $value): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$value),ENT_XML1|ENT_QUOTES,'UTF-8'); }
}
