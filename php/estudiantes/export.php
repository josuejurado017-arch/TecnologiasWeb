<?php

// Exporta a Excel (.xlsx) el padron de estudiantes con los MISMOS filtros del
// listado: texto, carrera, semestre, estado y tutoria del periodo activo.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

$filtros = EstudiantesController::filtros($_GET);
$students = (new EstudiantesController())->index($filtros);

$rows = [];
foreach ($students as $student) {
    $rows[] = [
        $student['nombre'],
        $student['apellido'],
        $student['carnet_identidad'] ?? '',
        $student['registro_universitario'] ?: 'Sin registro',
        $student['usuario'],
        $student['correo'],
        $student['telefono'] ?? '',
        $student['nombre_carrera'],
        (int) $student['semestre'],
        ucfirst((string) $student['estado']),
        $student['tutoria_grupo'] ?? ($student['tutoria_espera'] !== null ? $student['tutoria_espera'] . ' (en espera)' : 'Sin solicitud'),
    ];
}

xlsx_download(
    'estudiantes-' . date('Y-m-d') . (EstudiantesController::queryFiltros($filtros) !== '' ? '-filtrado' : ''),
    ['Nombres', 'Apellidos', 'Carnet', 'Registro universitario', 'Usuario', 'Correo', 'Teléfono', 'Carrera', 'Semestre', 'Estado', 'Tutoría (período activo)'],
    $rows,
    'Estudiantes'
);
exit;
