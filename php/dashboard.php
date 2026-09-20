<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
Auth::requireModule('dashboard');

$user = Auth::user();
$activePage = 'dashboard';
$dashboardController = new DashboardController();
$stats = $dashboardController->summary((string) $user['nombre_rol'], (int) $user['id_usuario']);
$upcoming = $dashboardController->upcoming((string) $user['nombre_rol'], (int) $user['id_usuario']);
$notifications = (new Notificacion())->unreadForUser((int) $user['id_usuario']);

require dirname(__DIR__) . '/views/dashboard.php';
