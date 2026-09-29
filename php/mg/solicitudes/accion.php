<?php

// Decision de la Coordinacion sobre una solicitud: aprobar, observar o rechazar (db/049).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.solicitudes');
mg_require_post();

$id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$accion = (string) ($_POST['accion'] ?? '');
[$error, $idExpediente] = (new MgSolicitudesController())->decidir($id, $accion, $_POST, (int) Auth::user()['id_usuario']);
if ($error !== null) {
    mg_redirect('solicitudes/ver.php?id=' . $id, ['error' => $error]);
}
$resultado = ['aprobar' => 'aprobada', 'observar' => 'observada', 'rechazar' => 'rechazada'][$accion];
mg_redirect('solicitudes/', ['message' => $resultado]);
