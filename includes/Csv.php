<?php

declare(strict_types=1);

/**
 * Descarga de listados en CSV abrible por Excel. Extraido de
 * php/accesos/export.php, que fue el primer listado exportable, para que todos
 * los modulos compartan el mismo formato y las mismas protecciones.
 */

/**
 * Abre la descarga y devuelve el manejador donde escribir las filas.
 * El BOM es lo que hace que Excel respete los acentos al abrir el archivo.
 */
function csv_download(string $filename)
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-store');

    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");

    return $output;
}

/**
 * Neutraliza celdas que Excel interpretaria como formula (inyeccion CSV): un
 * valor que empieza por = + - @ se prefija con comilla simple.
 */
function csv_cell($value): string
{
    $value = (string) $value;

    return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
}

/** Escribe una fila usando el separador ';' que espera Excel en configuracion regional ES. */
function csv_row($output, array $cells): void
{
    fputcsv($output, array_map('csv_cell', $cells), ';');
}
