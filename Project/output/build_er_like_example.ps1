$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$schemaPath = Join-Path $root 'ER_SCHEMA_CURRENT.sql'
$outPath = Join-Path $PSScriptRoot 'ER_7_groups_table_style.drawio'
$sql = Get-Content -LiteralPath $schemaPath -Raw

function Escape-Xml([string]$value) {
    return [Security.SecurityElement]::Escape($value)
}

$groups = [ordered]@{
    '1 บัญชีผู้ใช้ สิทธิ์ และการแจ้งเตือน' = @('users','user_roles','notifications','activity_log','password_reset_otp')
    '2 โครงสร้างมหาวิทยาลัยและบุคลากร' = @('prefixes','student','employee','faculty','department','group_class','positions','employee_position','office_holders')
    '3 ประเภทเอกสาร แม่แบบ และลำดับพิจารณา' = @('document_type','document_template','template_fields','review_workflows','review_workflow_steps')
    '4 เอกสารที่ยื่นและประวัติการพิจารณา' = @('documents','document_field_values','document_attachments','document_review_processes','document_review_step_instances','document_events')
    '5 รายละเอียดคำร้องแต่ละประเภท' = @('r01_request','r12_request','r12_request_courses','r14_request','r18_request','r24_request')
    '6 วงจรข้อมูลฝึกประสบการณ์วิชาชีพ' = @('internship_calendar','internship_profile','internship_application','internship_tracking','internship_supervision','internship_excel_sync')
    '7 หนังสือตอบรับ หนังสือราชการ และการนำเข้าข้อมูล' = @('internship_response_document','internship_response_declaration','internship_response_no_document_review','internship_official_document','internship_official_student','internship_direct_send_import')
}

# ตำแหน่งจัดตามความสัมพันธ์หลัก: ตารางแม่อยู่กลาง ตารางลูกอยู่รอบ ๆ
# ตารางที่มีในหลายกลุ่มจะถูกวางซ้ำใกล้ตารางที่ใช้งาน จึงไม่ต้องมีเส้นยาวตัดกัน
$layout = @{
    '1|users'=@(480,230); '1|user_roles'=@(480,70); '1|activity_log'=@(1090,250); '1|password_reset_otp'=@(80,570); '1|notifications'=@(530,620); '1|documents'=@(1090,620)
    '2|users'=@(70,70); '2|prefixes'=@(70,380); '2|faculty'=@(480,70); '2|department'=@(480,360); '2|employee'=@(900,70); '2|group_class'=@(900,390); '2|student'=@(70,590); '2|positions'=@(480,620); '2|employee_position'=@(900,630); '2|office_holders'=@(1280,630)
    '3|document_type'=@(70,330); '3|document_template'=@(550,70); '3|template_fields'=@(1030,70); '3|review_workflows'=@(550,720); '3|review_workflow_steps'=@(1030,720); '3|users'=@(70,720); '3|employee'=@(70,980); '3|positions'=@(1030,1130)
    '4|documents'=@(560,70); '4|document_field_values'=@(1040,160); '4|document_attachments'=@(1040,460); '4|document_review_processes'=@(560,500); '4|document_review_step_instances'=@(70,730); '4|document_events'=@(560,930); '4|student'=@(70,70); '4|document_template'=@(70,350); '4|template_fields'=@(1040,740); '4|review_workflows'=@(1040,1020); '4|review_workflow_steps'=@(70,1080); '4|users'=@(560,1240); '4|employee'=@(1040,1300)
    '5|documents'=@(680,70); '5|r01_request'=@(70,410); '5|r12_request'=@(490,410); '5|r14_request'=@(910,410); '5|r18_request'=@(70,800); '5|r24_request'=@(490,800); '5|r12_request_courses'=@(910,800); '5|employee'=@(1330,620)
    '6|internship_profile'=@(600,90); '6|internship_calendar'=@(70,90); '6|student'=@(70,430); '6|internship_application'=@(1100,90); '6|internship_tracking'=@(1100,420); '6|internship_supervision'=@(1100,700); '6|internship_excel_sync'=@(70,760); '6|users'=@(70,1070); '6|employee'=@(1100,1040); '6|documents'=@(600,1420)
    '7|internship_profile'=@(650,70); '7|internship_response_document'=@(70,430); '7|internship_response_declaration'=@(500,430); '7|internship_response_no_document_review'=@(930,430); '7|internship_official_document'=@(70,820); '7|internship_official_student'=@(500,820); '7|internship_direct_send_import'=@(930,820); '7|users'=@(1360,430); '7|employee'=@(1360,720)
}

$flowNotes = @{
    1='ผู้ใช้ → สิทธิ์ผู้ใช้ / บันทึกกิจกรรม / การแจ้งเตือน / รีเซ็ตรหัสผ่าน'
    2='คณะ → สาขา → กลุ่มเรียน → นักศึกษา และ บุคลากร → ตำแหน่ง → ผู้ดำรงตำแหน่ง'
    3='ประเภทเอกสาร → แม่แบบ → ฟิลด์ในแม่แบบ และ ประเภทเอกสาร → ลำดับการพิจารณา'
    4='เอกสารที่ยื่น → ค่าในฟอร์ม/ไฟล์แนบ → กระบวนการพิจารณา → ขั้นตอน → เหตุการณ์'
    5='เอกสารหลัก → แบบคำร้องแต่ละประเภท → รายการรายวิชา (เฉพาะ ร12)'
    6='ปฏิทินและนักศึกษา → โปรไฟล์ฝึกงาน → ใบคำร้อง/การติดตาม/นิเทศ'
    7='โปรไฟล์ฝึกงาน → ใบตอบรับหรือคำชี้แจง → หนังสือราชการ → รายชื่อนักศึกษา'
}

$tables = @{}
$blocks = [regex]::Matches($sql, '(?s)CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\((.*?)\)\s*ENGINE')
foreach ($match in $blocks) {
    $name = $match.Groups[1].Value
    $body = $match.Groups[2].Value
    $fields = @()
    foreach ($line in ($body -split "`n")) {
        # ดึงเฉพาะคอลัมน์จริง ไม่ดึง PRIMARY/UNIQUE/KEY/CONSTRAINT ซึ่งเป็นดัชนีและข้อกำหนดของตาราง
        if ($line -notmatch '^\s*(PRIMARY|UNIQUE|KEY|CONSTRAINT|FOREIGN|INDEX)\b' -and $line -match '^\s*`?(\w+)`?\s+([a-zA-Z]+(?:\(\d+(?:,\d+)?\))?)') {
            $fields += [pscustomobject]@{ Name = $matches[1]; Type = $matches[2].ToLower() }
        }
    }
    $pk = @([regex]::Matches($body, 'PRIMARY KEY\s*\(\s*`?(\w+)`?') | ForEach-Object { $_.Groups[1].Value })
    $foreign = @{}
    foreach ($fk in [regex]::Matches($body, 'FOREIGN KEY\s*\(\s*`?(\w+)`?\s*\)\s*REFERENCES\s+`?(\w+)`?', 'IgnoreCase')) {
        $foreign[$fk.Groups[1].Value] = $fk.Groups[2].Value
    }
    $tables[$name] = [pscustomobject]@{ Fields=$fields; PK=$pk; FK=$foreign; Body=$body }
}

function Is-OneToOne($table, $field) {
    $body = $tables[$table].Body
    return $body -match ('UNIQUE KEY\s+`?\w+`?\s*\(\s*`?' + [regex]::Escape($field) + '`?\s*\)')
}

function Add-Table([System.Collections.Generic.List[string]]$lines, [string]$name, [int]$x, [int]$y, [string]$index) {
    $table = $tables[$name]
    $rowHeight = 24
    $width = 330
    $height = 30 + ($table.Fields.Count * $rowHeight)
    $header = "t$index"
    $fieldIds = @{}
    $lines.Add("      <mxCell id=`"$header`" value=`"&lt;b&gt;$(Escape-Xml $name)&lt;/b&gt;`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#e9e4ff;strokeColor=#a78bfa;fontSize=13;fontStyle=1;align=center;verticalAlign=middle;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$x`" y=`"$y`" width=`"$width`" height=`"30`" as=`"geometry`"/></mxCell>")
    $row = 0
    foreach ($field in $table.Fields) {
        $top = $y + 30 + ($row * $rowHeight)
        $key = if ($table.PK -contains $field.Name) { 'PK' } elseif ($table.FK.ContainsKey($field.Name)) { 'FK' } else { '' }
        $fieldName = Escape-Xml $field.Name
        $fieldType = Escape-Xml $field.Type
        $fieldIds[$field.Name] = "$header-name-$row"
        $lines.Add("      <mxCell id=`"$header-type-$row`" value=`"$fieldType`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#c4b5fd;align=left;verticalAlign=middle;spacingLeft=7;fontSize=11;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$x`" y=`"$top`" width=`"80`" height=`"$rowHeight`" as=`"geometry`"/></mxCell>")
        $lines.Add("      <mxCell id=`"$header-name-$row`" value=`"$fieldName`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#c4b5fd;align=left;verticalAlign=middle;spacingLeft=7;fontSize=11;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$($x+80)`" y=`"$top`" width=`"195`" height=`"$rowHeight`" as=`"geometry`"/></mxCell>")
        $lines.Add("      <mxCell id=`"$header-key-$row`" value=`"$key`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#c4b5fd;align=center;verticalAlign=middle;fontSize=11;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$($x+275)`" y=`"$top`" width=`"55`" height=`"$rowHeight`" as=`"geometry`"/></mxCell>")
        $row++
    }
    return @{ Id=$header; X=$x; Y=$y; Height=$height; FieldIds=$fieldIds }
}

function Add-Reference([System.Collections.Generic.List[string]]$lines, [string]$name, [int]$x, [int]$y, [int]$index) {
    $table = $tables[$name]
    $pkField = $table.Fields | Where-Object { $table.PK -contains $_.Name } | Select-Object -First 1
    $id = "ref-$index"
    $fieldIds = @{}
    $fieldIds[$pkField.Name] = "$id-name"
    $lines.Add("      <mxCell id=`"$id`" value=`"&lt;b&gt;$(Escape-Xml $name)&lt;/b&gt;`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#f5f5f5;strokeColor=#777777;dashed=1;fontSize=12;fontStyle=1;align=center;verticalAlign=middle;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$x`" y=`"$y`" width=`"300`" height=`"30`" as=`"geometry`"/></mxCell>")
    $lines.Add("      <mxCell id=`"$id-type`" value=`"$(Escape-Xml $pkField.Type)`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#999999;dashed=1;align=left;verticalAlign=middle;spacingLeft=7;fontSize=11;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$x`" y=`"$($y+30)`" width=`"80`" height=`"24`" as=`"geometry`"/></mxCell>")
    $lines.Add("      <mxCell id=`"$id-name`" value=`"$(Escape-Xml $pkField.Name)`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#999999;dashed=1;align=left;verticalAlign=middle;spacingLeft=7;fontSize=11;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$($x+80)`" y=`"$($y+30)`" width=`"165`" height=`"24`" as=`"geometry`"/></mxCell>")
    $lines.Add("      <mxCell id=`"$id-key`" value=`"PK`" style=`"rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#999999;dashed=1;align=center;verticalAlign=middle;fontSize=11;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"$($x+245)`" y=`"$($y+30)`" width=`"55`" height=`"24`" as=`"geometry`"/></mxCell>")
    return @{ Id=$id; X=$x; Y=$y; Height=54; FieldIds=$fieldIds }
}

$xml = [System.Collections.Generic.List[string]]::new()
$xml.Add('<mxfile host="app.diagrams.net" modified="2026-09-17T00:00:00.000Z" agent="Codex" version="26.2.3">')
$pageNo = 0
foreach ($entry in $groups.GetEnumerator()) {
    $pageNo++
    $groupName = $entry.Key
    $members = $entry.Value
    $xml.Add("  <diagram id=`"group-$pageNo`" name=`"$groupName`">")
    $xml.Add('    <mxGraphModel dx="1800" dy="1500" grid="1" gridSize="10" page="1" pageWidth="2000" pageHeight="2800" math="0" shadow="0"><root>')
    $xml.Add('      <mxCell id="0"/><mxCell id="1" parent="0"/>')
    $xml.Add("      <mxCell id=`"title`" value=`"&lt;b&gt;ER กลุ่ม $groupName&lt;/b&gt;`" style=`"text;html=1;fontSize=19;fontStyle=1;align=center;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"420`" y=`"20`" width=`"760`" height=`"35`" as=`"geometry`"/></mxCell>")
    $xml.Add("      <mxCell id=`"flow-note`" value=`"$(Escape-Xml $flowNotes[$pageNo])`" style=`"text;html=1;fontSize=12;fontColor=#666666;align=center;`" vertex=`"1`" parent=`"1`"><mxGeometry x=`"260`" y=`"55`" width=`"1080`" height=`"25`" as=`"geometry`"/></mxCell>")
    $nodes = @{}
    $columnHeights = @(90,90,90)
    $i = 0
    foreach ($member in $members) {
        $layoutKey = "$pageNo|$member"
        if ($layout.ContainsKey($layoutKey)) {
            $x = $layout[$layoutKey][0]; $y = $layout[$layoutKey][1]
        } else {
            $col = 0
            if ($columnHeights[1] -lt $columnHeights[$col]) { $col = 1 }
            if ($columnHeights[2] -lt $columnHeights[$col]) { $col = 2 }
            $x = 70 + ($col * 510); $y = $columnHeights[$col]
        }
        $nodes[$member] = Add-Table $xml $member $x $y $i
        if (-not $layout.ContainsKey($layoutKey)) { $columnHeights[$col] += $nodes[$member].Height + 85 }
        $i++
    }
    # ตารางที่อยู่คนละกลุ่มจะแสดงเป็นกล่องเส้นประด้านล่าง เพื่อให้มีเส้นสัมพันธ์ครบโดยไม่ลากข้ามหน้า
    $externalTargets = [System.Collections.Generic.List[string]]::new()
    foreach ($member in $members) {
        foreach ($target in $tables[$member].FK.Values) {
            if (-not $nodes.ContainsKey($target) -and -not $externalTargets.Contains($target)) { $externalTargets.Add($target) }
        }
    }
    $refY = 1700
    $refIndex = 0
    $refColumnHeights = @($refY, $refY, $refY)
    foreach ($target in $externalTargets) {
        $layoutKey = "$pageNo|$target"
        if ($layout.ContainsKey($layoutKey)) {
            $refX = $layout[$layoutKey][0]; $refRowY = $layout[$layoutKey][1]
        } else {
            $refCol = 0
            if ($refColumnHeights[1] -lt $refColumnHeights[$refCol]) { $refCol = 1 }
            if ($refColumnHeights[2] -lt $refColumnHeights[$refCol]) { $refCol = 2 }
            $refX = 70 + ($refCol * 510); $refRowY = $refColumnHeights[$refCol]
        }
        # ตารางที่อยู่ข้ามกลุ่มแสดงซ้ำเฉพาะคีย์ จึงเห็นเส้นสัมพันธ์ครบโดยไม่ทำให้หน้ารก
        $nodes[$target] = Add-Reference $xml $target $refX $refRowY $refIndex
        if (-not $layout.ContainsKey($layoutKey)) { $refColumnHeights[$refCol] += $nodes[$target].Height + 85 }
        $refIndex++
    }

    $edge = 0
    foreach ($member in $members) {
        foreach ($field in $tables[$member].FK.Keys) {
            $target = $tables[$member].FK[$field]
            if ($nodes.ContainsKey($target)) {
                $edge++
                $start = if (Is-OneToOne $member $field) { 'ERone' } else { 'ERmany' }
                $sourceCell = $nodes[$member].FieldIds[$field]
                $targetPk = $tables[$target].PK | Select-Object -First 1
                $targetCell = $nodes[$target].FieldIds[$targetPk]
                $xml.Add("      <mxCell id=`"edge-$edge`" style=`"edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;startArrow=$start;startFill=0;endArrow=ERone;endFill=0;`" edge=`"1`" parent=`"1`" source=`"$sourceCell`" target=`"$targetCell`"><mxGeometry relative=`"1`" as=`"geometry`"/></mxCell>")
            }
        }
    }
    $xml.Add('    </root></mxGraphModel>')
    $xml.Add('  </diagram>')
}
$xml.Add('</mxfile>')
[IO.File]::WriteAllLines($outPath, $xml, [Text.UTF8Encoding]::new($false))
[xml](Get-Content -LiteralPath $outPath -Raw) | Out-Null
Write-Output "Created $outPath"
