<?php

// Registrar o corregir el informe de avance de un hito (HU-037).
// Lo registra el tutor vigente o la Coordinacion en su nombre (mg.informe).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireLogin();

$expedienteId = (int) filter_input(INPUT_GET, 'expediente', FILTER_VALIDATE_INT);
$hitoId = (int) filter_input(INPUT_GET, 'hito', FILTER_VALIDATE_INT);
$expediente = $expedienteId ? (new MgExpediente())->find($expedienteId) : null;
$hito = $hitoId ? (new MgCatalogo())->hito($hitoId) : null;
if ($expediente === null || $hito === null || $hito['tipo'] !== 'informe' || (int) $hito['id_cohorte'] !== (int) $expediente['id_cohorte']) {
    http_response_code(404);
    exit('Expediente o hito de informe no encontrados.');
}
$acceso = MgSeguimientoController::acceso($expediente);
if (!($acceso === 'tutor' || ($acceso === 'gestion' && Auth::canDo('mg.informe')))) {
    http_response_code(403);
    exit('No puedes registrar informes de este expediente.');
}
$activePage = $acceso === 'tutor' ? 'mg-mis-tesistas' : 'mg-expedientes';

$existente = (new MgInforme())->find($expedienteId, $hitoId);
$data = ['porcentaje_avance' => '', 'fecha_presentacion' => date('Y-m-d'), 'formato' => 'digital', 'respaldo_fisico' => 0, 'observaciones' => '', 'motivo' => ''];
if ($existente !== null) {
    $data = array_merge($data, array_intersect_key($existente, $data));
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $data = array_merge($data, array_intersect_key(array_map(static fn ($v) => is_string($v) ? $v : '', $_POST), $data));
    $data['respaldo_fisico'] = !empty($_POST['respaldo_fisico']) ? 1 : 0;
    [$errors, $aviso] = (new MgSeguimientoController())->guardarInforme($expedienteId, $hitoId, $_POST, (int) Auth::user()['id_usuario']);
    if (!$errors) {
        mg_redirect('seguimiento.php?expediente=' . $expedienteId . '#informes', ['message' => 'informe', 'aviso' => $aviso]);
    }
}

$title = 'Informe de avance · ' . $expediente['estudiante'];
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1><?= $existente ? 'Corregir informe de avance' : 'Registrar informe de avance' ?></h1>
        <p><strong><?= e($expediente['estudiante']) ?></strong> · <?= e($expediente['modalidad']) ?> · Tutor: <?= e((string) ($expediente['tutor'] ?? 'sin asignar')) ?></p>
        <p><strong><?= e($hito['nombre']) ?></strong> (<?= e(MgCatalogo::ETAPAS_HITO[$hito['etapa']] ?? $hito['etapa']) ?>) · fecha límite <?= e(mg_fecha_corta($hito['fecha_limite'])) ?><?= $hito['avance_esperado_pct'] !== null ? ' · avance esperado ~' . (int) $hito['avance_esperado_pct'] . '%' : '' ?></p>
        <p class="panel-note">Se acepta aunque sea tardío o con poco avance: el estado (a tiempo o tarde) se calcula con la fecha límite y el avance bajo solo genera una alerta.</p>
        <?php if ($acceso === 'gestion' && $expediente['tutor']): ?><p class="panel-note">Se registra en nombre del tutor <?= e($expediente['tutor']) ?>.</p><?php endif; ?>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <div><label for="fecha_presentacion">Fecha de presentación</label><input id="fecha_presentacion" name="fecha_presentacion" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e((string) $data['fecha_presentacion']) ?>"></div>
                <div><label for="porcentaje_avance">Avance (%)</label><input id="porcentaje_avance" name="porcentaje_avance" type="number" min="0" max="100" step="1" required value="<?= e((string) $data['porcentaje_avance']) ?>"></div>
                <div><label for="formato">Formato</label><select id="formato" name="formato"><?php foreach (MgInforme::FORMATOS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $data['formato'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <label class="mg-check"><input type="checkbox" name="respaldo_fisico" value="1" <?= (int) $data['respaldo_fisico'] === 1 ? 'checked' : '' ?>> Se entregó respaldo impreso</label>
            <label for="observaciones">Observaciones · opcional</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000"><?= e((string) $data['observaciones']) ?></textarea>
            <?php if ($existente): ?>
                <label for="motivo">Motivo de la corrección</label>
                <textarea id="motivo" name="motivo" rows="2" maxlength="500" required><?= e((string) $data['motivo']) ?></textarea>
                <p class="form-hint">La corrección queda en la bitácora con los datos anteriores.</p>
            <?php endif; ?>
            <button type="submit"><?= $existente ? 'Guardar corrección' : 'Registrar informe' ?></button>
            <a class="button secondary" href="<?= e(app_url('mg/seguimiento.php?expediente=' . $expedienteId . '#informes')) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
