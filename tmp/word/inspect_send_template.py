from docx import Document

document = Document(r'templates\official_internship\send_letter_template.docx')
for table_index, table in enumerate(document.tables):
    print('TABLE', table_index, len(table.rows), len(table.columns))
    for row_index, row in enumerate(table.rows):
        print('ROW', row_index, [cell.text for cell in row.cells])
        for cell_index, cell in enumerate(row.cells):
            for paragraph_index, paragraph in enumerate(cell.paragraphs):
                print('CELL', cell_index, 'P', paragraph_index, [(type(item).__name__, getattr(item, 'getText', lambda: '')()) for item in paragraph._p])
for paragraph_index, paragraph in enumerate(document.paragraphs):
    print('PARA', paragraph_index, [(type(item).__name__, getattr(item, 'getText', lambda: '')()) for item in paragraph._p])
