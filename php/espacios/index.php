<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Espacios de tutoría';
$activePage = 'espacios';

$messages = [
    'created' => 'Espacio creado correctamente.',
    'updated' => 'Espacio actualizado correctamente.',
    'estado' => 'Espacio actualizado.',
    'desde_aulas' => 'El módulo de aulas se reemplazó por Espacios de tutoría: el sistema ya no reserva aulas. El aula o el enlace de cada grupo se registra en Grupos → Ubicación.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$espacios = (new EspaciosController())->index();

require dirname(__DIR__, 2) . '/views/espacios/index.php';
