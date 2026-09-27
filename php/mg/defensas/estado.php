<?php

// Marcar una defensa como realizada (con observaciones) o cancelarla (HU-029).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.defensa');
$activePage = 'mg-defensas';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$defensa = $id ? (new MgDefensa())->find($id) : null;
if ($defensa === null) {
    http_response_code(404);
    exit('Defensa no encontrada.');
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $estado = (string) ($_POST['estado'] ?? '');
    $error = (new MgDefensasController())->cambiarEstado($id, $estado, $_POST, (int) Auth::user()['id_usuario']);
    if ($error === null) {
        mg_redirect('expedientes/ver.php?id=' . (int) $defensa['id_expediente'], ['message' => $estado]);
    }
}

$title = 'Defensa · ' . $defensa['estudiante'];
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Defensa de <?= e(MgTribunal::ETAPAS[$defensa['etapa']]) ?></h1>
        <p><strong><?= e($defensa['estudiante']) ?></strong> · <?= e(mg_fecha_corta($defensa['fecha'])) ?> <?= e(substr((string) $defensa['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $defensa['hora_fin'], 0, 5)) ?> · <?= e($defensa['ambiente']) ?> · <span class="badge badge-<?= e($defensa['estado']) ?>"><?= e(MgDefensa::ESTADOS[$defensa['estado']]) ?></span></p>
        <?php if ($error !== null): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

        <?php if ($defensa['estado'] !== 'programada'): ?>
            <p class="panel-note">Esta defensa ya no está programada.</p>
        <?php else: ?>
            <form method="post" class="mg-bloque">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="estado" value="realizada">
                <h2>Marcar como realizada</h2>
                <label for="obs_fondo">Observaciones de fondo (opcional)</label>
                <textarea id="obs_fondo" name="obs_fondo" rows="3" maxlength="5000"></textarea>
                <label for="obs_forma">Observaciones de forma (opcional)</label>
                <textarea id="obs_forma" name="obs_forma" rows="3" maxlength="5000"></textarea>
                <button type="submit" <?= $defensa['fecha'] > date('Y-m-d') ? 'disabled title="La defensa aún no ocurrió"' : '' ?>>Marcar realizada</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="estado" value="cancelada">
                <h2>Cancelar</h2>
                <label for="motivo">Motivo</label>
                <textarea id="motivo" name="motivo" rows="2" maxlength="500" required></textarea>
                <button type="submit" class="secondary">Cancelar defensa</button>
            </form>
        <?php endif; ?>
        <p><a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $defensa['id_expediente'])) ?>">Volver al expediente</a></p>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
