<?php

// Calendario de hitos de una cohorte (HU-022). Los informes de MG2 son hitos tipo "informe" (C-02).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$activePage = 'mg-cohortes';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$catalogo = new MgCatalogo();
$cohorte = $id ? $catalogo->cohorte($id) : null;
if ($cohorte === null) {
    http_response_code(404);
    exit('Cohorte no encontrada.');
}
$puedeEditar = Auth::canDo('mg.catalogo');
$hitoId = filter_input(INPUT_GET, 'hito', FILTER_VALIDATE_INT) ?: null;
$hito = $hitoId ? $catalogo->hito($hitoId) : null;
if ($hito !== null && (int) $hito['id_cohorte'] !== $id) {
    $hito = null;
    $hitoId = null;
}
$data = $hito ?? ['etapa' => 'mg2', 'tipo' => 'informe', 'nombre' => '', 'orden' => 1, 'fecha_limite' => '', 'avance_esperado_pct' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    Auth::requireAction('mg.catalogo');
    $controller = new MgCatalogoController();
    if (($_POST['accion'] ?? '') === 'eliminar') {
        $borrar = (int) filter_var($_POST['id_hito'] ?? 0, FILTER_VALIDATE_INT);
        $hitoBorrar = $catalogo->hito($borrar);
        $error = $hitoBorrar !== null && (int) $hitoBorrar['id_cohorte'] === $id ? $controller->eliminarHito($borrar) : 'El hito no existe.';
        mg_redirect('cohortes/calendario.php?id=' . $id, $error ? ['error' => $error] : ['message' => 'eliminado']);
    }
    [$data, $errors] = $controller->guardarHito($id, $hitoId, $_POST);
    if (!$errors) {
        mg_redirect('cohortes/calendario.php?id=' . $id, ['message' => 'guardado']);
    }
}

[$message, $error] = mg_flash(['guardado' => 'Hito guardado.', 'eliminado' => 'Hito eliminado.']);
$hitos = $catalogo->hitos($id);
$hoy = date('Y-m-d');
$title = 'Calendario · ' . $cohorte['nombre'];
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1><?= e($cohorte['nombre']) ?></h1>
            <p>Calendario de hitos · inicio <?= e(mg_fecha_corta($cohorte['fecha_inicio'])) ?>. Informes de MG2 cargados: <strong><?= count(array_filter($hitos, static fn (array $h): bool => $h['tipo'] === 'informe')) ?></strong>.</p>
        </div>
        <div class="page-heading-actions">
            <a class="button" href="<?= e(app_url('mg/cohortes/linea.php?id=' . $id)) ?>">Línea de tiempo</a>
            <a class="button secondary" href="<?= e(app_url('mg/cohortes/')) ?>">Volver a cohortes</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Etapa</th><th>Hito</th><th>Tipo</th><th>Fecha límite</th><th>Avance esperado</th><th>Situación</th><?php if ($puedeEditar): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($hitos as $item): ?>
                    <?php $dias = (int) floor((strtotime((string) $item['fecha_limite']) - strtotime($hoy)) / 86400); ?>
                    <tr>
                        <td><?= e(MgCatalogo::ETAPAS_HITO[$item['etapa']] ?? $item['etapa']) ?></td>
                        <td><?= (int) $item['orden'] ?>. <?= e($item['nombre']) ?></td>
                        <td><?= e(MgCatalogo::TIPOS_HITO[$item['tipo']] ?? $item['tipo']) ?></td>
                        <td><?= e(mg_fecha_corta($item['fecha_limite'])) ?></td>
                        <td><?= $item['avance_esperado_pct'] !== null ? (int) $item['avance_esperado_pct'] . '%' : '—' ?></td>
                        <td><?= $dias < 0 ? '<span class="badge badge-cerrada">Pasado</span>' : ($dias <= 14 ? '<span class="badge badge-warning">En ' . $dias . ' días</span>' : '<span class="badge badge-info">Próximo</span>') ?></td>
                        <?php if ($puedeEditar): ?>
                            <td class="actions">
                                <a href="<?= e(app_url('mg/cohortes/calendario.php?id=' . $id . '&hito=' . (int) $item['id_hito'])) ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('¿Eliminar este hito?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_hito" value="<?= (int) $item['id_hito'] ?>">
                                    <button class="link-button" type="submit">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$hitos): ?><tr><td colspan="7" class="empty-state">La cohorte aún no tiene hitos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($puedeEditar): ?>
        <section class="card mg-seccion narrow-wide">
            <h2><?= $hito ? 'Editar hito' : 'Agregar hito' ?></h2>
            <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="<?= e(app_url('mg/cohortes/calendario.php?id=' . $id . ($hito ? '&hito=' . (int) $hito['id_hito'] : ''))) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div>
                        <label for="etapa">Etapa</label>
                        <select id="etapa" name="etapa"><?php foreach (MgCatalogo::ETAPAS_HITO as $valor => $label): ?><option value="<?= e($valor) ?>" <?= ($data['etapa'] ?? '') === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    </div>
                    <div>
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo"><?php foreach (MgCatalogo::TIPOS_HITO as $valor => $label): ?><option value="<?= e($valor) ?>" <?= ($data['tipo'] ?? '') === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    </div>
                </div>
                <label for="nombre">Nombre</label>
                <input id="nombre" name="nombre" type="text" maxlength="120" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Primer informe de avance">
                <div class="form-grid">
                    <div>
                        <label for="orden">Orden</label>
                        <input id="orden" name="orden" type="number" min="1" max="99" required value="<?= e((string) ($data['orden'] ?? 1)) ?>">
                    </div>
                    <div>
                        <label for="fecha_limite">Fecha límite</label>
                        <input id="fecha_limite" name="fecha_limite" type="date" required value="<?= e($data['fecha_limite'] ?? '') ?>">
                    </div>
                </div>
                <label for="avance_esperado_pct">Avance esperado (%) · opcional</label>
                <input id="avance_esperado_pct" name="avance_esperado_pct" type="number" min="0" max="100" value="<?= e((string) ($data['avance_esperado_pct'] ?? '')) ?>" aria-describedby="avance-ayuda">
                <p class="form-hint" id="avance-ayuda">Referencia (~50%, ~70%, ~85-90%) solo para alertas: nunca rechaza un informe (RN-MG-13).</p>
                <button type="submit">Guardar hito</button>
                <?php if ($hito): ?><a class="button secondary" href="<?= e(app_url('mg/cohortes/calendario.php?id=' . $id)) ?>">Cancelar</a><?php endif; ?>
            </form>
        </section>
    <?php endif; ?>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
