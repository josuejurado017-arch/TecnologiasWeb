<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
Auth::requireModule('accesos');
$title = 'Registro de accesos';
$activePage = 'accesos';
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$isValidDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
};
$filterError = '';
if (!$isValidDate($fechaDesde) || !$isValidDate($fechaHasta)) {
    $filterError = 'Ingrese fechas validas.';
} elseif ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
    $filterError = 'La fecha inicial no puede ser posterior a la fecha final.';
}
$accesses = (new AccesosController())->index(
    $filterError === '' && $fechaDesde !== '' ? $fechaDesde : null,
    $filterError === '' && $fechaHasta !== '' ? $fechaHasta : null
);
$exportQuery = http_build_query(array_filter([
    'fecha_desde' => $fechaDesde,
    'fecha_hasta' => $fechaHasta,
], static fn (string $value): bool => $value !== ''));
$exportUrl = app_url('accesos/export.php') . ($exportQuery !== '' ? '?' . $exportQuery : '');

require dirname(__DIR__, 2) . '/views/accesos/index.php';
