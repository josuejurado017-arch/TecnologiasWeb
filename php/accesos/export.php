<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
Auth::requireModule('accesos');

$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$isValidDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
};

if (
    !$isValidDate($fechaDesde)
    || !$isValidDate($fechaHasta)
    || ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta)
) {
    http_response_code(400);
    echo 'Rango de fechas no valido.';
    exit;
}

$accesses = (new AccesosController())->index(
    $fechaDesde !== '' ? $fechaDesde : null,
    $fechaHasta !== '' ? $fechaHasta : null
);
$suffix = $fechaDesde !== '' || $fechaHasta !== '' ? '-' . ($fechaDesde ?: 'inicio') . '-a-' . ($fechaHasta ?: 'hoy') : '';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="historial-accesos' . $suffix . '.csv"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Fecha y hora', 'Usuario', 'Nombre', 'IP de origen', 'Resultado'], ';');
$csvCell = static function ($value): string {
    $value = (string) $value;

    return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
};
foreach ($accesses as $access) {
    fputcsv($output, [
        $csvCell($access['fecha_hora']),
        $csvCell($access['usuario']),
        $csvCell($access['nombre'] . ' ' . $access['apellido']),
        $csvCell($access['ip_origen'] ?: 'No disponible'),
        $csvCell($access['resultado']),
    ], ';');
}
fclose($output);
