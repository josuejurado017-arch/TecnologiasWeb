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

$rows = [];
foreach ($accesses as $access) {
    $rows[] = [
        $access['fecha_hora'],
        $access['usuario'],
        $access['nombre'] . ' ' . $access['apellido'],
        $access['ip_origen'] ?: 'No disponible',
        ucfirst((string) $access['resultado']),
    ];
}
xlsx_download('historial-accesos' . $suffix, ['Fecha y hora', 'Usuario', 'Nombre', 'IP de origen', 'Resultado'], $rows, 'Accesos');
exit;
