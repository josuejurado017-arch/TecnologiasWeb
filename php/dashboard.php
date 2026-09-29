<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
// El equipo de Modalidades de Grado no opera tutorias: su inicio es el panel MG.
if (Auth::isMgRole()) {
    header('Location: ' . app_url('mg/'));
    exit;
}
// El estudiante con expediente de grado activo trabaja solo en Modalidades de Grado.
if (Auth::enModoGrado()) {
    header('Location: ' . app_url('mg/mi-modalidad.php'));
    exit;
}
Auth::requireModule('dashboard');

$user = Auth::user();
$activePage = 'dashboard';
$dashboardController = new DashboardController();
$stats = $dashboardController->summary((string) $user['nombre_rol'], (int) $user['id_usuario']);
$upcoming = $dashboardController->upcoming((string) $user['nombre_rol'], (int) $user['id_usuario']);
$notifications = (new Notificacion())->unreadForUser((int) $user['id_usuario']);

require dirname(__DIR__) . '/views/dashboard.php';
