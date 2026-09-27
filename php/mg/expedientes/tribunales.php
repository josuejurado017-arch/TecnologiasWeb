<?php

// Tribunales de una etapa del expediente (HU-028): un docente por puesto, con historial.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.tribunal');
$activePage = 'mg-expedientes';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$etapa = is_string($_GET['etapa'] ?? null) ? $_GET['etapa'] : '';
$expediente = $id ? (new MgExpediente())->find($id) : null;
if ($expediente === null || !isset(MgTribunal::ETAPAS[$etapa])) {
    http_response_code(404);
    exit('Expediente o etapa no encontrados.');
}
$vigentes = (new MgTribunal())->vigentes($id, $etapa);
$puestos = max(1, (int) MgParametro::entero('tribunales_por_defensa_' . $etapa, 2));
$seleccion = [];
foreach ($vigentes as $orden => $tribunal) {
    $seleccion[$orden] = (int) $tribunal['id_tutor'];
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    foreach ((array) ($_POST['tribunal'] ?? []) as $orden => $tutorId) {
        $seleccion[(int) $orden] = (int) $tutorId;
    }
    [$errors, $avisos] = (new MgExpedientesController())->guardarTribunales($id, $etapa, $_POST, (int) Auth::user()['id_usuario']);
    if (!$errors) {
        mg_redirect('expedientes/ver.php?id=' . $id . '#tribunales', ['message' => 'tribunales', 'aviso' => implode("\n", $avisos)]);
    }
}

$docentes = (new MgAsignacion())->docentes();
$programada = (new MgDefensa())->programada($id, $etapa);
$title = 'Tribunales ' . MgTribunal::ETAPAS[$etapa] . ' · ' . $expediente['estudiante'];

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Tribunales de <?= e(MgTribunal::ETAPAS[$etapa]) ?></h1>
        <p><strong><?= e($expediente['estudiante']) ?></strong> · <?= e($expediente['modalidad']) ?> · Tutor: <?= e((string) ($expediente['tutor'] ?? 'sin asignar')) ?></p>
        <p class="panel-note">Se asignan <?= $puestos ?> tribunales (parámetro <code>tribunales_por_defensa_<?= e($etapa) ?></code>), con unos <?= (int) MgParametro::entero('dias_anticipacion_tribunal', 14) ?> días de anticipación a la defensa<?= $programada ? ' (programada para el ' . e(mg_fecha_corta($programada['fecha'])) . ')' : '' ?>. Reemplazar un tribunal lo deja en el historial.</p>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php for ($orden = 1; $orden <= $puestos; $orden++): ?>
                <label for="tribunal-<?= $orden ?>">Tribunal <?= $orden ?></label>
                <select id="tribunal-<?= $orden ?>" name="tribunal[<?= $orden ?>]">
                    <option value="0">Sin asignar</option>
                    <?php foreach ($docentes as $docente): ?>
                        <option value="<?= (int) $docente['id_tutor'] ?>" <?= ($seleccion[$orden] ?? 0) === (int) $docente['id_tutor'] ? 'selected' : '' ?>><?= e($docente['nombre']) ?><?= (int) $docente['id_tutor'] === (int) ($expediente['id_tutor'] ?? 0) ? ' (tutor del expediente)' : '' ?><?= $docente['especialidad'] ? ' · ' . e($docente['especialidad']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endfor; ?>
            <label for="motivo_cambio">Motivo del cambio (obligatorio si reemplazas a un tribunal)</label>
            <input id="motivo_cambio" name="motivo_cambio" type="text" maxlength="500">
            <p class="form-hint">RN-MG-21 (propuesta): si el tutor queda también como tribunal se advierte, pero no se bloquea hasta validarlo con UPDS.</p>
            <button type="submit">Guardar tribunales</button>
            <a class="button secondary" href="<?= e(app_url('mg/expedientes/ver.php?id=' . $id)) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
