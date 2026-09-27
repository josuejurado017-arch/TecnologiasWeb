<?php

// Acciones POST de la ficha: datos, estado y etapa (HU-024).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.expediente');
mg_require_post();

$id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$userId = (int) Auth::user()['id_usuario'];
$controller = new MgExpedientesController();
$ficha = 'expedientes/ver.php?id=' . $id;

switch ((string) ($_POST['accion'] ?? '')) {
    case 'datos':
        $error = $controller->actualizarDatos($id, $_POST);
        mg_redirect($ficha . '#datos', $error ? ['error' => $error] : ['message' => 'datos']);
        // no break: mg_redirect termina la peticion
    case 'estado':
        $error = $controller->cambiarEstado($id, (string) ($_POST['estado'] ?? ''), (string) ($_POST['motivo'] ?? ''), $userId);
        mg_redirect($ficha, $error ? ['error' => $error] : ['message' => 'estado']);
        // no break
    case 'etapa':
        $error = $controller->avanzarEtapa($id, (string) ($_POST['resultado'] ?? ''), $userId);
        mg_redirect($ficha, $error ? ['error' => $error] : ['message' => 'etapa']);
        // no break
    default:
        http_response_code(400);
        exit('Acción no válida.');
}
