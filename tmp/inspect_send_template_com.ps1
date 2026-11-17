$ErrorActionPreference = 'Stop'
$source = Join-Path $PSScriptRoot '..\templates\official_internship\send_letter_template.docx'
$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open((Resolve-Path -LiteralPath $source), $false, $true)
    foreach ($shape in $document.Shapes) {
        [pscustomobject]@{
            Name = $shape.Name
            Type = $shape.Type
            Left = [math]::Round($shape.Left, 2)
            Top = [math]::Round($shape.Top, 2)
            Width = [math]::Round($shape.Width, 2)
            Height = [math]::Round($shape.Height, 2)
            Text = $shape.TextFrame.TextRange.Text.Trim()
        }
    } | Format-Table -AutoSize
} finally {
    if ($document) { $document.Close(0) }
    if ($word) { $word.Quit() }
}
