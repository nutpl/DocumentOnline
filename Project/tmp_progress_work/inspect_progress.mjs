import fs from 'node:fs/promises';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';

const inputPath = 'C:/DocumentProject1_69/อัพเดตความคืบหน้าproject.xlsx';
const outputDir = 'C:/xampp/htdocs/Project/tmp_progress_work/inspection';
await fs.mkdir(outputDir, { recursive: true });

const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(inputPath));
const summary = await workbook.inspect({
  kind: 'workbook,sheet,table',
  maxChars: 12000,
  tableMaxRows: 30,
  tableMaxCols: 20,
  tableMaxCellChars: 180,
});
console.log(summary.ndjson);

for (const sheet of workbook.worksheets.items) {
  const used = sheet.getUsedRange();
  const preview = await workbook.render({ sheetName: sheet.name, autoCrop: 'all', scale: 1.5, format: 'png' });
  await fs.writeFile(`${outputDir}/${sheet.name.replace(/[\\/:*?"<>|]/g, '_')}.png`, new Uint8Array(await preview.arrayBuffer()));
  console.log(`SHEET:${sheet.name} USED:${used.address}`);
  const table = await workbook.inspect({ kind: 'table', sheetId: sheet.sheetId, range: used.address, include: 'values,formulas', maxChars: 16000, tableMaxRows: 80, tableMaxCols: 20, tableMaxCellChars: 180 });
  console.log(table.ndjson);
}
