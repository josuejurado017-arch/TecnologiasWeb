<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor']);
Auth::requireModule('tutorias');

$user = Auth::user();
$role = (string) $user['nombre_rol'];
$title = 'Solicitudes especiales';
$activePage = 'tutorias';
$requests = (new SolicitudesEspecialesController())->index($role, (int) $user['id_usuario']);

require dirname(__DIR__, 2) . '/views/tutorias/special-list.php';
