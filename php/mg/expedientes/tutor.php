<?php

// Asignar o cambiar el tutor de un expediente (HU-025/026). Genera la carta (HU-027).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.tutor');
$activePage = 'mg-expedientes';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$expediente = $id ? (new MgExpediente())->find($id) : null;
if ($expediente === null) {
    http_response_code(404);
    exit('Expediente no encontrado.');
}
$asignaciones = new MgAsignacion();
$vigente = $asignaciones->vigente($id);
$data = ['id_tutor' => 0, 'fecha_asignacion' => date('Y-m-d'), 'referencia_decanatura' => '', 'disponibilidad_consultada' => false,
    'observaciones' => '', 'motivo_fin' => '', 'fecha_nota_renuncia' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $data = array_merge($data, array_intersect_key($_POST, $data));
    [$errors, $avisos, $documentoId] = (new MgExpedientesController())->asignarTutor($id, $_POST, (int) Auth::user()['id_usuario']);
    if (!$errors) {
        mg_redirect('expedientes/ver.php?id=' . $id, ['message' => 'tutor', 'aviso' => implode("\n", $avisos), 'documento' => $documentoId]);
    }
}

$docentes = $asignaciones->docentes();
$recomendada = (int) MgParametro::entero('tutor_carga_recomendada', 3);
$maximo = MgParametro::entero('tutor_max_estudiantes');
$title = ($vigente ? 'Cambiar tutor · ' : 'Asignar tutor · ') . $expediente['estudiante'];

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1><?= $vigente ? 'Cambiar tutor' : 'Asignar tutor' ?></h1>
        <p><strong><?= e($expediente['estudiante']) ?></strong> · <?= e($expediente['modalidad']) ?> · <?= e($expediente['cohorte']) ?> · <?= mg_badge_etapa((string) $expediente['etapa_actual']) ?></p>
        <?php if ($vigente): ?>
            <p class="notice-warning mg-aviso">Tutor vigente: <strong><?= e($vigente['tutor']) ?></strong> desde <?= e(mg_fecha_corta($vigente['fecha_asignacion'])) ?>. Al cambiarlo, su asignación queda como <em>reemplazada</em> en el historial (nunca se borra) y se emite una carta nueva.</p>
        <?php endif; ?>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <fieldset class="mg-docentes">
                <legend>Docente · la carga vigente y las materias ayudan a revisar afinidad (RN-MG-05)</legend>
                <?php foreach ($docentes as $docente): ?>
                    <?php
                    $carga = (int) $docente['carga'];
                    $esVigente = $vigente && (int) $vigente['id_tutor'] === (int) $docente['id_tutor'];
                    $excede = $carga + ($esVigente ? 0 : 1) > $recomendada;
                    ?>
                    <label class="mg-docente<?= $excede ? ' mg-docente-carga' : '' ?>">
                        <input type="radio" name="id_tutor" value="<?= (int) $docente['id_tutor'] ?>" <?= (int) $data['id_tutor'] === (int) $docente['id_tutor'] ? 'checked' : '' ?> <?= $esVigente ? 'disabled' : '' ?> required>
                        <span><strong><?= e($docente['nombre']) ?></strong><?= $esVigente ? ' <span class="badge badge-info">Tutor actual</span>' : '' ?>
                            <small><?= e((string) ($docente['especialidad'] ?: 'Sin especialidad registrada')) ?><?= $docente['materias'] ? ' · ' . e($docente['materias']) : '' ?></small>
                            <small>Tesistas vigentes: <strong><?= $carga ?></strong><?= $excede ? ' · <span class="badge badge-warning">supera la carga recomendada (' . $recomendada . ')</span>' : '' ?></small></span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$docentes): ?><p class="empty-state">No hay docentes habilitados con cuenta activa.</p><?php endif; ?>
            </fieldset>
            <p class="form-hint">C-01: la carga recomendada (<?= $recomendada ?>) y el máximo (<?= $maximo === null ? 'sin definir' : $maximo ?>) solo advierten; nunca bloquean.</p>

            <div class="form-grid">
                <div>
                    <label for="fecha_asignacion">Fecha de asignación</label>
                    <input id="fecha_asignacion" name="fecha_asignacion" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e((string) $data['fecha_asignacion']) ?>">
                </div>
                <div>
                    <label for="referencia_decanatura">Referencia de Decanatura</label>
                    <input id="referencia_decanatura" name="referencia_decanatura" type="text" maxlength="100" required value="<?= e((string) $data['referencia_decanatura']) ?>" placeholder="Ej.: Nota DEC-FCE/045/2026" aria-describedby="ayuda-decanatura">
                    <small id="ayuda-decanatura" class="form-hint">Número de la nota o resolución con la que Decanatura validó que el docente es afín al tema (RN-MG-05). Cópialo tal como figura en el documento físico: lo emite Decanatura, por eso el sistema no lo genera. Queda impreso en la carta de asignación.</small>
                </div>
            </div>
            <label class="mg-check"><input type="checkbox" name="disponibilidad_consultada" value="1" <?= !empty($data['disponibilidad_consultada']) ? 'checked' : '' ?> required> Se consultó la disponibilidad del docente (RN-MG-06)</label>

            <?php if ($vigente): ?>
                <label for="motivo_fin">Motivo del cambio o renuncia</label>
                <textarea id="motivo_fin" name="motivo_fin" rows="2" maxlength="500" required><?= e((string) $data['motivo_fin']) ?></textarea>
                <label for="fecha_nota_renuncia">Fecha de la nota de renuncia (opcional)</label>
                <input id="fecha_nota_renuncia" name="fecha_nota_renuncia" type="date" value="<?= e((string) $data['fecha_nota_renuncia']) ?>">
            <?php endif; ?>

            <label for="observaciones">Observaciones (opcional)</label>
            <input id="observaciones" name="observaciones" type="text" maxlength="500" value="<?= e((string) $data['observaciones']) ?>">

            <p class="form-hint">Al guardar se emite la carta de asignación (una hoja para el tutor y otra para el estudiante) y se notifica a ambos.<?= $expediente['etapa_actual'] === 'previa' ? ' El expediente pasa a MG1.' : '' ?></p>
            <button type="submit"><?= $vigente ? 'Cambiar tutor' : 'Asignar tutor' ?></button>
            <a class="button secondary" href="<?= e(app_url('mg/expedientes/ver.php?id=' . $id)) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
