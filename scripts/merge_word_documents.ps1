param(
    [Parameter(Mandatory = $true)][string]$SourceList,
    [Parameter(Mandatory = $true)][string]$DestinationDocx
)

$ErrorActionPreference = 'Stop'
$sources = @(Get-Content -LiteralPath $SourceList | Where-Object { $_.Trim() -ne '' })
if ($sources.Count -eq 0) {
    throw 'No Word documents were provided for merging.'
}

$word = $null
$document = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0

    # Let Word insert each complete DOCX. This preserves the template's
    # floating footer objects and anchors, unlike copying document.xml nodes.
    $document = $word.Documents.Open($sources[0], $false, $false)
    foreach ($source in ($sources | Select-Object -Skip 1)) {
        $insertAt = $document.Content.End - 1
        $document.Range($insertAt, $insertAt).InsertFile($source)

        # Do not add a page-break paragraph after the preceding letter. Its
        # footer already occupies the bottom of the page, so Word can place
        # that empty paragraph alone on a blank page. Instead, make the first
        # paragraph of the inserted letter start on its own page.
        $insertedRange = $document.Range($insertAt, $document.Content.End - 1)
        if ($insertedRange.Paragraphs.Count -gt 0) {
            $insertedRange.Paragraphs.Item(1).Range.ParagraphFormat.PageBreakBefore = $true
        }
    }

    $document.SaveAs2($DestinationDocx, 16) # wdFormatDocumentDefault (.docx)
}
finally {
    if ($null -ne $document) { $document.Close(0) }
    if ($null -ne $word) { $word.Quit() }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
