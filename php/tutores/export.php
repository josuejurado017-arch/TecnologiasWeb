<?php

// Exporta el listado de tutores a Excel (.xlsx).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

$rows = [];
foreach ((new TutoresController())->index() as $tutor) {
    $rows[] = [
        $tutor['nombre'],
        $tutor['apellido'],
        $tutor['carnet_identidad'] ?? '',
        $tutor['usuario'],
        $tutor['correo'],
        $tutor['telefono'] ?? '',
        $tutor['especialidad'] ?: 'Sin especialidad',
        ucfirst((string) $tutor['estado']),
    ];
}

xlsx_download('tutores-' . date('Y-m-d'), ['Nombres', 'Apellidos', 'Carnet', 'Usuario', 'Correo', 'Teléfono', 'Especialidad', 'Estado'], $rows, 'Tutores');
exit;
