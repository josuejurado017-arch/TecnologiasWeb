<?php

// Programar (?expediente=) o reprogramar (?reprogramar=) una defensa (HU-029).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.defensa');
$activePage = 'mg-defensas';

$defensasModel = new MgDefensa();
$reprogramarId = (int) filter_input(INPUT_GET, 'reprogramar', FILTER_VALIDATE_INT);
$anterior = $reprogramarId ? $defensasModel->find($reprogramarId) : null;
$expedienteId = $anterior ? (int) $anterior['id_expediente'] : (int) filter_input(INPUT_GET, 'expediente', FILTER_VALIDATE_INT);
$expediente = $expedienteId ? (new MgExpediente())->find($expedienteId) : null;
if ($expediente === null || ($reprogramarId && $anterior === null)) {
    http_response_code(404);
    exit('Expediente o defensa no encontrados.');
}
$etapa = $anterior ? (string) $anterior['etapa'] : (string) $expediente['etapa_actual'];
$data = ['fecha' => '', 'hora_inicio' => '', 'hora_fin' => '', 'ambiente' => $anterior['ambiente'] ?? '', 'autorizado_por' => '', 'referencia_autorizacion' => '', 'motivo' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $data = array_merge($data, array_intersect_key(array_map(static fn ($v) => is_string($v) ? $v : '', $_POST), $data));
    $controller = new MgDefensasController();
    [$errors, $avisos, $nuevaId] = $anterior
        ? $controller->reprogramar($reprogramarId, $_POST, (int) Auth::user()['id_usuario'])
        : $controller->programar($expedienteId, $_POST, (int) Auth::user()['id_usuario']);
    if (!$errors) {
        mg_redirect('expedientes/ver.php?id=' . $expedienteId, ['message' => $anterior ? 'reprogramada' : 'defensa', 'aviso' => implode("\n", $avisos)]);
    }
}

$tribunales = (new MgTribunal())->vigentes($expedienteId, $etapa);
$ambientes = array_values(array_unique(array_column(Database::connection()->query('SELECT ambiente FROM defensas_mg ORDER BY id_defensa DESC LIMIT 50')->fetchAll(), 'ambiente')));
$title = ($anterior ? 'Reprogramar defensa · ' : 'Programar defensa · ') . $expediente['estudiante'];

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1><?= $anterior ? 'Reprogramar defensa' : 'Programar defensa' ?> de <?= e(MgTribunal::ETAPAS[$etapa] ?? $etapa) ?></h1>
        <p><strong><?= e($expediente['estudiante']) ?></strong> · <?= e($expediente['modalidad']) ?> · Tutor: <?= e((string) ($expediente['tutor'] ?? 'sin asignar')) ?></p>
        <p>Tribunales: <?= $tribunales ? e(implode(' · ', array_column($tribunales, 'docente'))) : '<span class="badge badge-warning">Sin tribunales</span>' ?></p>
        <?php if ($anterior): ?><p class="notice-warning mg-aviso">Defensa actual: <?= e(mg_fecha_corta($anterior['fecha'])) ?> <?= e(substr((string) $anterior['hora_inicio'], 0, 5)) ?> en <?= e($anterior['ambiente']) ?>. Quedará como <em>reprogramada</em> en el historial.</p><?php endif; ?>
        <p class="panel-note">El sistema rechaza choques en el mismo horario: ambiente ocupado, el estudiante con otra defensa, o un tribunal o el tutor en otra defensa.</p>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <div><label for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" min="<?= e(date('Y-m-d')) ?>" required value="<?= e($data['fecha']) ?>"></div>
                <div><label for="ambiente">Ambiente</label><input id="ambiente" name="ambiente" type="text" maxlength="100" required value="<?= e($data['ambiente']) ?>" list="ambientes" placeholder="Ej. Auditorio, Aula 204"></div>
                <div><label for="hora_inicio">Hora de inicio</label><input id="hora_inicio" name="hora_inicio" type="time" required value="<?= e(substr($data['hora_inicio'], 0, 5)) ?>"></div>
                <div><label for="hora_fin">Hora de fin</label><input id="hora_fin" name="hora_fin" type="time" required value="<?= e(substr($data['hora_fin'], 0, 5)) ?>"></div>
            </div>
            <datalist id="ambientes"><?php foreach ($ambientes as $ambiente): ?><option value="<?= e($ambiente) ?>"><?php endforeach; ?></datalist>

            <details <?= $data['autorizado_por'] !== '' ? 'open' : '' ?>>
                <summary>Fecha fuera de calendario o prórroga (RN-MG-18)</summary>
                <p class="form-hint">El sistema registra la autorización; no la decide. Excepciones las aprueba Decanatura o Vicerrectorado Académico.</p>
                <div class="form-grid">
                    <div><label for="autorizado_por">Autorizado por</label><select id="autorizado_por" name="autorizado_por"><option value="">No aplica</option><?php foreach (MgDefensasController::AUTORIZACIONES as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $data['autorizado_por'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div><label for="referencia_autorizacion">Referencia</label><input id="referencia_autorizacion" name="referencia_autorizacion" type="text" maxlength="100" value="<?= e($data['referencia_autorizacion']) ?>" placeholder="Nro. de nota o resolución"></div>
                </div>
            </details>

            <?php if ($anterior): ?>
                <label for="motivo">Motivo de la reprogramación</label>
                <textarea id="motivo" name="motivo" rows="2" maxlength="500" required><?= e($data['motivo']) ?></textarea>
            <?php endif; ?>
            <button type="submit"><?= $anterior ? 'Reprogramar' : 'Programar defensa' ?></button>
            <a class="button secondary" href="<?= e(app_url('mg/expedientes/ver.php?id=' . $expedienteId)) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
