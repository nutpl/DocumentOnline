param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$schemaPath = Join-Path $ProjectRoot 'ER_SCHEMA_CURRENT.sql'
$outputDir = Join-Path $ProjectRoot 'output\er_sql_groups_v2'
if (-not (Test-Path -LiteralPath $schemaPath)) { throw "Schema not found: $schemaPath" }
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

# These groups are for drawing/explaining the ER model.  A few supporting
# tables deliberately repeat so every important foreign-key line can be seen
# in the same diagram.  Do not import all seven files into one database.
$groups = [ordered]@{
    '01_organization.sql' = @('faculty','department','prefixes','users','employee','group_class','student')
    '02_accounts_personnel.sql' = @('faculty','department','group_class','prefixes','positions','users','user_roles','password_reset_otp','activity_log','employee','employee_position','student','office_holders')
    '03_document_templates_and_workflow.sql' = @('users','employee','positions','document_type','document_template','template_fields','review_workflows','review_workflow_steps')
    '04_document_execution.sql' = @('users','employee','student','positions','document_type','document_template','template_fields','review_workflows','review_workflow_steps','documents','document_field_values','document_attachments','document_review_processes','document_review_step_instances','document_events','notifications')
    '05_request_details.sql' = @('users','employee','student','document_template','documents','r01_request','r12_request','r12_request_courses','r14_request','r18_request','r24_request')
    '06_internship_application_response.sql' = @('users','student','document_template','documents','internship_calendar','internship_profile','internship_application','internship_response_document','internship_response_declaration','internship_response_no_document_review')
    '07_internship_operations.sql' = @('users','employee','department','student','internship_calendar','internship_profile','internship_tracking','internship_supervision','internship_direct_send_import','internship_official_document','internship_official_student','internship_excel_sync')
}

$schema = Get-Content -LiteralPath $schemaPath -Raw
$createStatements = @{}
foreach ($match in [regex]::Matches($schema, '(?is)CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*\(.*?\)\s*ENGINE\s*=.*?;')) {
    $createStatements[$match.Groups[1].Value] = $match.Value
}

foreach ($entry in $groups.GetEnumerator()) {
    $lines = @(
        '-- ER diagram group only. Tables may repeat in other groups to show relationships.',
        '-- Do not run this file together with the other group files as a database installation script.',
        'SET FOREIGN_KEY_CHECKS = 0;',
        ''
    )
    foreach ($tableName in $entry.Value) {
        if (-not $createStatements.ContainsKey($tableName)) { throw "Missing table definition: $tableName" }
        $lines += $createStatements[$tableName]
        $lines += ''
    }
    $lines += 'SET FOREIGN_KEY_CHECKS = 1;'
    Set-Content -LiteralPath (Join-Path $outputDir $entry.Key) -Value $lines -Encoding utf8
}

$readme = @'
# ER Diagram SQL Groups v2

These seven files are for importing one group at a time into an ER-diagram tool.
Tables are intentionally repeated as supporting tables where they make a foreign-key relationship visible.
They are not seven sequential database migration files and must not be executed together.

1. Organization: faculty, department, class group, advisor and student links.
2. Accounts and personnel: user accounts, roles, credentials, staff, students and office holders.
3. Document templates and workflow definition: document types, templates, fields, workflow versions and steps.
4. Document execution: submitted documents, values, attachments, review instances, events and notifications.
5. Request details: data specific to each request form.
6. Internship application and response: profile, calendar, application and response/exception records.
7. Internship operations: tracking, supervision, official letters, direct import and Excel synchronization.
'@
Set-Content -LiteralPath (Join-Path $outputDir 'README.md') -Value $readme -Encoding utf8
