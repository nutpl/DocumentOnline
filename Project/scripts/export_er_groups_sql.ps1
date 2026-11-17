param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$schemaPath = Join-Path $ProjectRoot 'ER_SCHEMA_CURRENT.sql'
$dbmlDir = Join-Path $ProjectRoot 'ER_DBML_GROUPS'
$outputDir = Join-Path $ProjectRoot 'output\er_sql_groups'

if (-not (Test-Path -LiteralPath $schemaPath)) { throw "Schema not found: $schemaPath" }
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

# Extract each complete CREATE TABLE statement from the current MySQL schema.
$schema = Get-Content -LiteralPath $schemaPath -Raw
$createStatements = @{}
foreach ($match in [regex]::Matches($schema, '(?is)CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*\(.*?\)\s*ENGINE\s*=.*?;')) {
    $createStatements[$match.Groups[1].Value] = $match.Value
}

foreach ($dbmlFile in Get-ChildItem -LiteralPath $dbmlDir -Filter '*.dbml' | Sort-Object Name) {
    $dbml = Get-Content -LiteralPath $dbmlFile.FullName -Raw
    $tableNames = [regex]::Matches($dbml, '(?m)^Table\s+([A-Za-z0-9_]+)\s*\{') |
        ForEach-Object { $_.Groups[1].Value } |
        Select-Object -Unique

    $sql = @(
        '-- Generated from ER_DBML_GROUPS and ER_SCHEMA_CURRENT.sql'
        '-- MySQL / MariaDB'
        'SET FOREIGN_KEY_CHECKS = 0;'
        ''
    )
    foreach ($tableName in $tableNames) {
        if ($createStatements.ContainsKey($tableName)) {
            $sql += "DROP TABLE IF EXISTS ``$tableName``;"
            $sql += $createStatements[$tableName]
            $sql += ''
        }
        else {
            $sql += "-- Table ``$tableName`` is shown in the ER diagram but has no CREATE TABLE definition in ER_SCHEMA_CURRENT.sql."
            $sql += ''
        }
    }
    $sql += 'SET FOREIGN_KEY_CHECKS = 1;'

    $outputName = [IO.Path]::ChangeExtension($dbmlFile.Name, '.sql')
    Set-Content -LiteralPath (Join-Path $outputDir $outputName) -Value $sql -Encoding utf8
}
