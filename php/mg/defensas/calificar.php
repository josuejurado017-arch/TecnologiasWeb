<?php

// Registrar o corregir la nota de una defensa realizada (HU-031). Todo cambio va a la bitacora.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.calificacion');
$activePage = 'mg-defensas';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$defensa = $id ? (new MgDefensa())->find($id) : null;
if ($defensa === null) {
    http_response_code(404);
    exit('Defensa no encontrada.');
}
$error = null;
$data = ['nota' => $defensa['nota'] ?? '', 'observaciones' => $defensa['obs_calificacion'] ?? '', 'publicada' => (int) ($defensa['publicada'] ?? 0) === 1, 'motivo_correccion' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $data = ['nota' => (string) ($_POST['nota'] ?? ''), 'observaciones' => (string) ($_POST['observaciones'] ?? ''),
        'publicada' => !empty($_POST['publicada']), 'motivo_correccion' => (string) ($_POST['motivo_correccion'] ?? '')];
    [$error, $sugerencia] = (new MgDefensasController())->calificar($id, $_POST, (int) Auth::user()['id_usuario']);
    if ($error === null) {
        mg_redirect('expedientes/ver.php?id=' . (int) $defensa['id_expediente'], ['message' => 'nota', 'aviso' => $sugerencia]);
    }
}

$tribunales = (new MgTribunal())->vigentes((int) $defensa['id_expediente'], (string) $defensa['etapa']);
$expediente = (new MgExpediente())->find((int) $defensa['id_expediente']);
$title = 'Nota · ' . $defensa['estudiante'];
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Nota de la defensa de <?= e(MgTribunal::ETAPAS[$defensa['etapa']]) ?></h1>
        <table class="mg-tabla-datos">
            <tr><th>Estudiante</th><td><?= e($defensa['estudiante']) ?> · R.U. <?= e((string) $defensa['registro_universitario']) ?></td></tr>
            <tr><th>Modalidad</th><td><?= e($defensa['modalidad']) ?> · <?= e($defensa['cohorte']) ?></td></tr>
            <tr><th>Tutor</th><td><?= e((string) ($expediente['tutor'] ?? '—')) ?></td></tr>
            <tr><th>Tribunales</th><td><?= $tribunales ? e(implode(' · ', array_column($tribunales, 'docente'))) : '—' ?></td></tr>
            <tr><th>Defensa</th><td><?= e(mg_fecha_corta($defensa['fecha'])) ?> <?= e(substr((string) $defensa['hora_inicio'], 0, 5)) ?> · <?= e($defensa['ambiente']) ?> · <span class="badge badge-<?= e($defensa['estado']) ?>"><?= e(MgDefensa::ESTADOS[$defensa['estado']]) ?></span></td></tr>
        </table>
        <?php if ($error !== null): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if ($defensa['estado'] !== 'realizada'): ?>
            <p class="alert" role="alert">Solo se califica una defensa realizada.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="nota">Nota (<?= (int) MgParametro::entero('nota_minima', 0) ?> a <?= (int) MgParametro::entero('nota_maxima', 100) ?>)</label>
                <input id="nota" name="nota" type="number" step="0.01" min="<?= (int) MgParametro::entero('nota_minima', 0) ?>" max="<?= (int) MgParametro::entero('nota_maxima', 100) ?>" required value="<?= e((string) $data['nota']) ?>">
                <label for="observaciones">Observaciones (opcional)</label>
                <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000"><?= e((string) $data['observaciones']) ?></textarea>
                <label class="mg-check"><input type="checkbox" name="publicada" value="1" <?= $data['publicada'] ? 'checked' : '' ?>> Publicar: el estudiante y su tutor ven la nota</label>
                <?php if ($defensa['nota'] !== null): ?>
                    <label for="motivo_correccion">Motivo de la corrección (obligatorio: la nota ya estaba registrada)</label>
                    <input id="motivo_correccion" name="motivo_correccion" type="text" maxlength="500" required value="<?= e($data['motivo_correccion']) ?>">
                <?php endif; ?>
                <p class="form-hint">Escala y nota de aprobación son parámetros pendientes de validar. El promedio MG1/MG2 es informativo (RN-MG-16).</p>
                <button type="submit">Guardar nota</button>
                <a class="button secondary" href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $defensa['id_expediente'])) ?>">Cancelar</a>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
