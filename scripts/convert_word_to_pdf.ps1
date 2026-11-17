param(
    [Parameter(Mandatory = $true)][string]$SourceDocx,
    [Parameter(Mandatory = $true)][string]$DestinationPdf
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $SourceDocx)) {
    throw 'ไม่พบไฟล์ Word ต้นทาง'
}

$outputDirectory = Split-Path -Path $DestinationPdf -Parent
if (-not (Test-Path -LiteralPath $outputDirectory)) {
    New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
}

$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($SourceDocx, $false, $true)
    $document.ExportAsFixedFormat($DestinationPdf, 17)
} finally {
    if ($null -ne $document) { $document.Close(0) }
    if ($null -ne $word) { $word.Quit() }
}

if (-not (Test-Path -LiteralPath $DestinationPdf) -or (Get-Item -LiteralPath $DestinationPdf).Length -lt 1) {
    throw 'Word ไม่สร้างไฟล์ PDF'
}
