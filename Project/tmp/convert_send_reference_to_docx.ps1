$ErrorActionPreference = 'Stop'
$source = 'C:\DocumentProject1_69\ตัวอย่างหนังสือ_ขอส่งตัวนักศึกษา.doc'
$destination = Join-Path $PSScriptRoot 'send-reference-from-example.docx'
$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($source, $false, $true)
    $document.SaveAs2($destination, 16)
} finally {
    if ($document) { $document.Close(0) }
    if ($word) { $word.Quit() }
}
if (!(Test-Path -LiteralPath $destination)) { throw 'Word did not create the DOCX reference.' }
Write-Output $destination
