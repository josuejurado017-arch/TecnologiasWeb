<?php

// Cambia el tipo de tutoria con el que trabaja el portal (selector de la barra superior).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id_tipo_tutoria'] ?? null, FILTER_VALIDATE_INT);
if ($id !== false && $id !== null) {
    TipoTutoria::seleccionar($id);
}

// Vuelve a la misma pagina. Solo rutas locales: se descartan esquema y host, y las
// barras iniciales (incluida "\", que algunos navegadores leen como "//host").
$destino = is_string($_POST['redirect'] ?? null) ? $_POST['redirect'] : '';
$ruta = ltrim((string) (parse_url($destino, PHP_URL_PATH) ?: ''), '/\\');
$query = parse_url($destino, PHP_URL_QUERY);
header('Location: ' . app_url(($ruta !== '' ? $ruta : 'dashboard.php') . (is_string($query) && $query !== '' ? '?' . $query : '')));
exit;
