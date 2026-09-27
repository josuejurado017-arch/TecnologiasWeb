<?php

// Catalogo de modalidades de grado (HU-022). Solo se activan o desactivan.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$title = 'Modalidades de grado';
$activePage = 'mg-cohortes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    Auth::requireAction('mg.catalogo');
    $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $error = (new MgCatalogoController())->cambiarEstadoModalidad($id, ($_POST['accion'] ?? '') === 'activar');
    mg_redirect('modalidades.php', $error ? ['error' => $error] : ['message' => 'estado']);
}

[$message, $error] = mg_flash(['estado' => 'Modalidad actualizada.']);
$modalidades = (new MgCatalogo())->modalidades();

require dirname(__DIR__, 2) . '/views/mg/modalidades.php';
