<?php

// Parametros configurables de Modalidades de Grado (HU-020).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.parametros');
$title = 'Parámetros de Modalidades de Grado';
$activePage = 'mg-parametros';

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $controller = new MgCatalogoController();
    foreach ((array) ($_POST['valor'] ?? []) as $clave => $valor) {
        if (!is_string($clave) || !is_string($valor)) {
            continue;
        }
        if (($error = $controller->actualizarParametro($clave, $valor, (int) Auth::user()['id_usuario'])) !== null) {
            $errores[] = $error;
        }
    }
    if (!$errores) {
        mg_redirect('parametros.php', ['message' => 'guardado']);
    }
}

[$message, $error] = mg_flash(['guardado' => 'Parámetros guardados. Los cambios quedan en la bitácora.']);
$parametros = (new MgParametro())->all();
$plantillas = (new MgDocumento())->plantillas();

require dirname(__DIR__, 2) . '/views/mg/parametros.php';
