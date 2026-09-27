<?php

// Cohortes de Modalidades de Grado (HU-022). Una cohorte con expedientes no se elimina: se desactiva.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$title = 'Cohortes de grado';
$activePage = 'mg-cohortes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    Auth::requireAction('mg.catalogo');
    $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $error = (new MgCatalogoController())->cambiarEstadoCohorte($id, ($_POST['accion'] ?? '') === 'activar');
    mg_redirect('cohortes/', $error ? ['error' => $error] : ['message' => 'estado']);
}

[$message, $error] = mg_flash([
    'creada' => 'Cohorte creada. Carga ahora su calendario de hitos.',
    'actualizada' => 'Cohorte actualizada.',
    'estado' => 'Cohorte actualizada.',
]);
$cohortes = (new MgCatalogo())->cohortes();

require dirname(__DIR__, 3) . '/views/mg/cohortes.php';
