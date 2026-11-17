Option Explicit

Dim wordApp, document, sourcePath, outputPath
sourcePath = WScript.Arguments(0)
outputPath = WScript.Arguments(1)

Set wordApp = CreateObject("Word.Application")
wordApp.Visible = False
wordApp.DisplayAlerts = 0
Set document = wordApp.Documents.Open(sourcePath, False, True, False)
document.ExportAsFixedFormat outputPath, 17
document.Close False
wordApp.Quit
