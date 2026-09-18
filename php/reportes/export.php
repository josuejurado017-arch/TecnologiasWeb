<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

$controller = new ReportesController();
$filters = $controller->filters($_GET);
[$rows] = $controller->report($filters);
$suffix = date('Ymd-His');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte-tutorias-' . $suffix . '.csv"');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');
fputcsv($output, ['Fecha', 'Hora inicio', 'Hora fin', 'Materia', 'Estudiante', 'Tutor', 'Modalidad', 'Estado', 'Asistencia', 'Minutos retraso', 'Motivo cancelacion']);
$csvCell = static function ($value): string {
    $value = (string) $value;

    return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
};
foreach ($rows as $row) {
    fputcsv($output, [
        $csvCell($row['fecha']),
        $csvCell(substr((string) $row['hora_inicio'], 0, 5)),
        $csvCell(substr((string) $row['hora_fin'], 0, 5)),
        $csvCell($row['nombre_materia']),
        $csvCell($row['estudiante']),
        $csvCell($row['tutor']),
        $csvCell($row['modalidad']),
        $csvCell($row['estado']),
        $csvCell($row['asistencia']),
        $csvCell($row['minutos_retraso']),
        $csvCell($row['motivo_cancelacion']),
    ]);
}
fclose($output);
exit;
