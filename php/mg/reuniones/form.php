<?php

// Registrar (?expediente=) o editar/corregir (?id=) una reunion de MG (HU-034/035).
// Registra solo el tutor vigente; corrige la Coordinacion con motivo.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireLogin();

$modelo = new MgReunion();
$reunionId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$reunion = $reunionId ? $modelo->find($reunionId) : null;
$expedienteId = $reunion ? (int) $reunion['id_expediente'] : (int) filter_input(INPUT_GET, 'expediente', FILTER_VALIDATE_INT);
$expediente = $expedienteId ? (new MgExpediente())->find($expedienteId) : null;
if ($expediente === null || ($reunionId && $reunion === null)) {
    http_response_code(404);
    exit('Expediente o reunión no encontrados.');
}
$acceso = MgSeguimientoController::acceso($expediente);
$coordinacion = Auth::canDo('mg.validar');
$userId = (int) Auth::user()['id_usuario'];
$esTutorDeLaReunion = $reunion !== null && $acceso === 'tutor' && (int) $reunion['id_usuario_tutor'] === $userId;
$permitido = $reunion === null ? $acceso === 'tutor' : ($coordinacion || ($esTutorDeLaReunion && $reunion['estado_validacion'] !== 'validada'));
if (!$permitido) {
    http_response_code(403);
    exit($reunion === null ? 'Solo el tutor vigente registra reuniones de este expediente.' : 'No puedes editar esta reunión.');
}
$activePage = $acceso === 'tutor' ? 'mg-mis-tesistas' : 'mg-reuniones';

$data = ['fecha' => date('Y-m-d'), 'hora_inicio' => '', 'hora_fin' => '', 'modalidad' => 'presencial', 'lugar_o_enlace' => '', 'temas' => '',
    'avance_sesion' => '', 'observaciones' => '', 'asistio_estudiante' => 'si', 'asistio_tutor' => 'si', 'motivo' => ''];
if ($reunion !== null) {
    $data = array_merge($data, array_intersect_key($reunion, $data));
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $data = array_merge($data, array_intersect_key(array_map(static fn ($v) => is_string($v) ? $v : '', $_POST), $data));
    $controller = new MgSeguimientoController();
    if ($reunion === null) {
        [$errors] = $controller->registrarReunion($expedienteId, $_POST, $userId);
    } else {
        [$errors] = $controller->editarReunion($reunionId, $_POST, $userId);
    }
    if (!$errors) {
        mg_redirect('seguimiento.php?expediente=' . $expedienteId . '#reuniones', ['message' => $reunion === null ? 'reunion' : 'reunion_editada']);
    }
}

$plazo = (int) MgParametro::entero('plazo_registro_reunion_dias', 7);
$minima = $coordinacion ? '' : date('Y-m-d', strtotime('-' . $plazo . ' days'));
$title = ($reunion ? 'Editar reunión · ' : 'Registrar reunión · ') . $expediente['estudiante'];
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1><?= $reunion ? ($coordinacion ? 'Corregir reunión' : 'Editar reunión') : 'Registrar reunión' ?></h1>
        <p><strong><?= e($expediente['estudiante']) ?></strong> · <?= e($expediente['modalidad']) ?> · <?= mg_badge_etapa((string) $expediente['etapa_actual']) ?> · Tutor: <?= e((string) ($reunion['tutor'] ?? $expediente['tutor'] ?? '—')) ?></p>
        <?php if ($reunion && $reunion['estado_validacion'] === 'observada'): ?><p class="notice-warning mg-aviso">Observación de la Coordinación: <?= e((string) $reunion['motivo_observacion']) ?><?= $esTutorDeLaReunion ? '<br>Al guardar, la reunión vuelve a quedar por validar.' : '' ?></p><?php endif; ?>
        <?php if ($reunion && $reunion['estado_validacion'] === 'validada'): ?><p class="notice-warning mg-aviso">La reunión está validada. La corrección queda en la bitácora con los datos anteriores.</p><?php endif; ?>
        <p class="panel-note">Registra la reunión cuando termine. No se registran reuniones futuras<?= $coordinacion ? '' : ' ni de hace más de ' . $plazo . ' días' ?>, ni reuniones que se crucen con otra del tutor o del estudiante.</p>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <div><label for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" max="<?= e(date('Y-m-d')) ?>" <?= $minima !== '' ? 'min="' . e($minima) . '"' : '' ?> required value="<?= e($data['fecha']) ?>"></div>
                <div><label for="modalidad">Modalidad</label><select id="modalidad" name="modalidad"><?php foreach (MgReunion::MODALIDADES as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $data['modalidad'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div><label for="hora_inicio">Hora de inicio</label><input id="hora_inicio" name="hora_inicio" type="time" required value="<?= e(substr((string) $data['hora_inicio'], 0, 5)) ?>"></div>
                <div><label for="hora_fin">Hora de fin</label><input id="hora_fin" name="hora_fin" type="time" required value="<?= e(substr((string) $data['hora_fin'], 0, 5)) ?>"></div>
            </div>
            <label for="lugar_o_enlace">Lugar, o enlace / ID de Teams si fue virtual</label>
            <input id="lugar_o_enlace" name="lugar_o_enlace" type="text" maxlength="255" required value="<?= e((string) $data['lugar_o_enlace']) ?>" placeholder="Ej. Sala de docentes · https://teams.microsoft.com/...">
            <label for="temas">Temas tratados</label>
            <textarea id="temas" name="temas" rows="3" maxlength="1000" required><?= e((string) $data['temas']) ?></textarea>
            <label for="avance_sesion">Avance de la sesión · opcional</label>
            <textarea id="avance_sesion" name="avance_sesion" rows="2" maxlength="1000"><?= e((string) $data['avance_sesion']) ?></textarea>
            <label for="observaciones">Observaciones · opcional</label>
            <textarea id="observaciones" name="observaciones" rows="2" maxlength="1000" placeholder="Tardanzas, compromisos, próximos pasos"><?= e((string) $data['observaciones']) ?></textarea>
            <div class="form-grid">
                <div><label for="asistio_estudiante">¿Asistió el estudiante?</label><select id="asistio_estudiante" name="asistio_estudiante"><option value="si" <?= $data['asistio_estudiante'] === 'si' ? 'selected' : '' ?>>Sí</option><option value="no" <?= $data['asistio_estudiante'] === 'no' ? 'selected' : '' ?>>No</option></select></div>
                <div><label for="asistio_tutor">¿Asistió el tutor?</label><select id="asistio_tutor" name="asistio_tutor"><option value="si" <?= $data['asistio_tutor'] === 'si' ? 'selected' : '' ?>>Sí</option><option value="no" <?= $data['asistio_tutor'] === 'no' ? 'selected' : '' ?>>No</option></select></div>
            </div>
            <?php if ($reunion && $coordinacion): ?>
                <label for="motivo">Motivo de la corrección</label>
                <textarea id="motivo" name="motivo" rows="2" maxlength="500" required><?= e((string) $data['motivo']) ?></textarea>
            <?php endif; ?>
            <button type="submit"><?= $reunion ? 'Guardar cambios' : 'Registrar reunión' ?></button>
            <a class="button secondary" href="<?= e(app_url('mg/seguimiento.php?expediente=' . $expedienteId . '#reuniones')) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
