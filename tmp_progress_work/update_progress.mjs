import fs from 'node:fs/promises';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';

const inputPath = 'C:/DocumentProject1_69/อัพเดตความคืบหน้าproject.xlsx';
const outputDir = 'C:/xampp/htdocs/Project/outputs/progress_update';
const outputPath = `${outputDir}/อัพเดตความคืบหน้าproject_ประเมินแล้ว.xlsx`;
await fs.mkdir(outputDir, { recursive: true });

const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(inputPath));
const sheet = workbook.worksheets.getItem('Sheet1');

const progress = new Map([
  [4, 1], [5, 1], [6, 0.9], [7, 1], [8, 1], [9, 1], [10, 1],
  [12, 1], [13, 1], [14, 1], [15, 1], [16, 1], [17, 1], [18, 0.9], [19, 1], [20, 1], [21, 1], [22, 1], [23, 1], [24, 1],
  [26, 1], [27, 1], [28, 1], [29, 1], [30, 1], [31, 1], [32, 1], [33, 1], [34, 1], [35, 1],
  [37, 1], [38, 1], [39, 1], [40, 1], [41, 1], [42, 1], [43, 1], [44, 1], [45, 1],
  [47, 1], [48, 1], [49, 1], [50, 1], [51, 1], [52, 1], [53, 1], [54, 1], [55, 1],
  [57, 1], [58, 1], [59, 1], [60, 1], [61, 1], [62, 1], [63, 1], [64, 1], [65, 1],
  [67, 1], [68, 1], [69, 1], [70, 1], [71, 1], [72, 1], [73, 1], [74, 1], [75, 1],
  [77, 0.9], [78, 0.9], [79, 0.9], [80, 0.9], [81, 0.9], [82, 0.9], [83, 0.9], [84, 0.9],
  [86, 1], [87, 1], [88, 1], [89, 1], [90, 1], [91, 1], [92, 1], [93, 1],
]);

for (const [row, value] of progress) {
  sheet.getRange(`B${row}`).values = [[value]];
}

sheet.getRange('B2').formulas = [['=AVERAGE(B3,B11,B25,B36)']];
sheet.getRange('B3').formulas = [['=AVERAGE(B4:B10)']];
sheet.getRange('B11').formulas = [['=AVERAGE(B12:B24)']];
sheet.getRange('B25').formulas = [['=AVERAGE(B26:B35)']];
sheet.getRange('B36').formulas = [['=AVERAGE(B37:B45)']];
sheet.getRange('B46').formulas = [['=AVERAGE(B47:B55)']];
sheet.getRange('B56').formulas = [['=AVERAGE(B57:B65,B66)']];
sheet.getRange('B66').formulas = [['=AVERAGE(B67:B75)']];
sheet.getRange('B76').formulas = [['=AVERAGE(B77:B84)']];
sheet.getRange('B85').formulas = [['=AVERAGE(B86:B93)']];

const notes = {
  2: 'สรุปจากสิทธิ์ผู้ใช้งาน 4 กลุ่ม',
  3: 'เหลือปรับหน้าจอจัดการสิทธิ์ให้กำหนดหลายบทบาทต่อบัญชีได้โดยตรง',
  6: 'เหลือปรับหน้าจอจัดการสิทธิ์ให้รองรับหลายบทบาทต่อบัญชีโดยตรง',
  11: 'การแจ้งเตือนผ่านอีเมลยังอยู่ระหว่างเปิดใช้งานและทดสอบ',
  18: 'แจ้งเตือนภายในเว็บทำงานแล้ว; อีเมลยังปิดไว้ระหว่างทดสอบ',
  46: 'เอกสารและแบบฟอร์มตามขอบเขตจัดทำครบแล้ว',
  56: 'ขั้นตอนในระบบทำงานครบตามเงื่อนไขที่กำหนด',
  66: 'การบันทึกสถานะ CWIE เป็นการบันทึกข้อมูลในระบบ ไม่เชื่อมต่อระบบส่วนกลาง',
  76: 'แจ้งเตือนภายในเว็บทำงานแล้ว; เหลือเปิดใช้งานและทดสอบการส่งอีเมลก่อนใช้งานจริง',
  85: 'เป็นข้อจำกัดที่กำหนดไว้ตามขอบเขตของโครงการ ไม่ใช่งานค้าง',
};
for (const [row, note] of Object.entries(notes)) {
  sheet.getRange(`C${row}`).values = [[note]];
}

sheet.getRange('B2:B93').format.numberFormat = '0%';
sheet.getRange('B2:B93').format.horizontalAlignment = 'center';
sheet.getRange('B2:B93').format.verticalAlignment = 'center';
sheet.getRange('C2:C93').format.wrapText = true;
sheet.getRange('B1:C93').format.verticalAlignment = 'center';
sheet.getRange('B:B').format.columnWidth = 12;
sheet.getRange('C:C').format.columnWidth = 54;
sheet.getRange('C2:C93').format.autofitRows();

workbook.recalculate();

const check = await workbook.inspect({
  kind: 'table',
  range: 'Sheet1!A1:C93',
  include: 'values,formulas',
  maxChars: 22000,
  tableMaxRows: 100,
  tableMaxCols: 3,
  tableMaxCellChars: 180,
});
console.log(check.ndjson);

const errors = await workbook.inspect({
  kind: 'match',
  searchTerm: '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!',
  options: { useRegex: true, maxResults: 100 },
  summary: 'formula error scan',
});
console.log(errors.ndjson);

const preview = await workbook.render({ sheetName: 'Sheet1', range: 'A1:C93', scale: 1.5, format: 'png' });
await fs.writeFile(`${outputDir}/progress_preview.png`, new Uint8Array(await preview.arrayBuffer()));

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);

const exportedWorkbook = await SpreadsheetFile.importXlsx(await FileBlob.load(outputPath));
const exportedCheck = await exportedWorkbook.inspect({
  kind: 'table',
  range: 'Sheet1!A1:C10',
  include: 'values,formulas',
  maxChars: 5000,
  tableMaxRows: 10,
  tableMaxCols: 3,
  tableMaxCellChars: 180,
});
console.log(exportedCheck.ndjson);
console.log(`OUTPUT:${outputPath}`);
