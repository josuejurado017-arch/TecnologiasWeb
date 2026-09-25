<?php

declare(strict_types=1);

/**
 * Descarga de listados como libro de Excel (.xlsx) real, sin librerias externas:
 * un .xlsx es un ZIP con XML. A diferencia del CSV, Excel lo abre siempre en
 * columnas, sin depender del separador de lista de la configuracion regional, y
 * respeta acentos y ceros a la izquierda (carnet, registro universitario).
 *
 * Una hoja, encabezado en negrita fijo al hacer scroll, autofiltro y ancho de
 * columna segun el contenido. Los textos van como cadenas en linea, que Excel
 * nunca evalua como formula, asi que no hay inyeccion de formulas.
 * Requiere la extension zip; si falta, cae a CSV (includes/Csv.php).
 */
function xlsx_download(string $filename, array $headers, iterable $rows, string $sheetName = 'Datos'): void
{
    if (!class_exists('ZipArchive')) {
        $output = csv_download($filename);
        csv_row($output, $headers);
        foreach ($rows as $row) {
            csv_row($output, $row);
        }
        fclose($output);
        return;
    }

    $xml = static fn ($value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    // Quita caracteres de control que invalidan el XML (salvo tab y salto de linea).
    $limpio = static fn ($value): string => (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);

    $anchos = array_map(static fn ($h): int => mb_strlen((string) $h), array_values($headers));
    $filasXml = [xlsx_row(1, array_values($headers), 1, $xml, $limpio)];
    $n = 1;
    foreach ($rows as $row) {
        $row = array_values($row);
        $n++;
        foreach ($row as $i => $value) {
            $anchos[$i] = max($anchos[$i] ?? 0, mb_strlen((string) $value));
        }
        $filasXml[] = xlsx_row($n, $row, 0, $xml, $limpio);
    }

    $ultimaColumna = xlsx_column(max(count($headers), 1));
    $cols = '';
    foreach ($anchos as $i => $ancho) {
        $cols .= sprintf('<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $i + 1, min(max($ancho + 2, 8), 60));
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . $cols . '</cols>'
        . '<sheetData>' . implode('', $filasXml) . '</sheetData>'
        . '<autoFilter ref="A1:' . $ultimaColumna . $n . '"/>'
        . '</worksheet>';

    $parts = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $xml(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $xml(mb_substr($sheetName, 0, 31)) . '\'!$A$1:$' . $ultimaColumna . '$' . $n . '</definedName></definedNames>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        // Estilo 0: normal. Estilo 1: encabezado en negrita con fondo gris.
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE7E6E6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    ];

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach ($parts as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    unlink($tmp);
}

/** Letra de columna de Excel: 1 -> A, 27 -> AA. */
function xlsx_column(int $index): string
{
    $letters = '';
    while ($index > 0) {
        $index--;
        $letters = chr(65 + $index % 26) . $letters;
        $index = intdiv($index, 26);
    }

    return $letters;
}

/** Fila de la hoja: enteros como numero, todo lo demas como texto en linea. */
function xlsx_row(int $number, array $values, int $style, callable $xml, callable $limpio): string
{
    $cells = '';
    foreach ($values as $i => $value) {
        $ref = xlsx_column($i + 1) . $number;
        $s = $style > 0 ? ' s="' . $style . '"' : '';
        if (is_int($value)) {
            $cells .= '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
        } elseif ($value !== null && $value !== '') {
            $cells .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . $xml($limpio($value)) . '</t></is></c>';
        }
    }

    return '<row r="' . $number . '">' . $cells . '</row>';
}
