Option Explicit

Const wdExportFormatPDF = 17

Dim word, doc, inputPath, outputPath

If WScript.Arguments.Count < 2 Then
  WScript.Echo "Usage: cscript //nologo convert_word_to_pdf.vbs <input.docx> <output.pdf>"
  WScript.Quit 1
End If

inputPath = WScript.Arguments(0)
outputPath = WScript.Arguments(1)

On Error Resume Next
Set word = CreateObject("Word.Application")
If Err.Number <> 0 Then
  WScript.Echo "Failed to start Word: " & Err.Description
  WScript.Quit 2
End If
On Error GoTo 0

word.Visible = False
word.DisplayAlerts = 0

On Error Resume Next
Set doc = word.Documents.Open(inputPath, False, True)
If Err.Number <> 0 Then
  WScript.Echo "Failed to open document: " & Err.Description
  word.Quit
  WScript.Quit 3
End If
On Error GoTo 0

On Error Resume Next
doc.ExportAsFixedFormat outputPath, wdExportFormatPDF
If Err.Number <> 0 Then
  WScript.Echo "Failed to export PDF: " & Err.Description
  doc.Close False
  word.Quit
  WScript.Quit 4
End If
On Error GoTo 0

doc.Close False
word.Quit

WScript.Echo "CREATED: " & outputPath
