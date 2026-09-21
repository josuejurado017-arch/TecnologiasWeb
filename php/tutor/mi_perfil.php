<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('tutores');
$user = Auth::user();
$title = 'Mi perfil de tutor';
$activePage = 'mi-perfil-tutor';
$controller = new TutorPortalController();
$profile = $controller->profile((int) $user['id_usuario']);
if (!$profile) {
    http_response_code(404);
    exit('Perfil de tutor no encontrado.');
}
$data = ['especialidad' => $profile['especialidad'] ?? '', 'biografia' => $profile['biografia'] ?? ''];
$errors = [];
$message = ($_GET['message'] ?? '') === 'updated' ? 'Perfil actualizado correctamente.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update((int) $user['id_usuario'], $_POST);
        if (!$errors) {
            header('Location: ' . app_url('mi-perfil-tutor/?message=updated'));
            exit;
        }
    }
}

require dirname(__DIR__, 2) . '/views/tutor/mi-perfil.php';
