<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
Auth::requireModule('dashboard');

$user = Auth::user();
$activePage = 'dashboard';
$dashboardController = new DashboardController();
$stats = $dashboardController->summary((string) $user['nombre_rol'], (int) $user['id_usuario']);
$upcoming = $dashboardController->upcoming((string) $user['nombre_rol'], (int) $user['id_usuario']);
$notificationModel = new Notificacion();
$notificationModel->createUpcomingForUser((int) $user['id_usuario']);
$notificationModel->createEvaluationPendingForUser((int) $user['id_usuario']);
$notifications = $notificationModel->unreadForUser((int) $user['id_usuario']);
$recentActivity = (new HistorialTutoria())->recentForUser((int) $user['id_usuario'], (string) $user['nombre_rol']);
require dirname(__DIR__) . '/views/dashboard.php';
