<?php

// Agenda de defensas por fecha (HU-029), con citaciones en lote por dia (HU-030).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$title = 'Agenda de defensas';
$activePage = 'mg-defensas';

$desde = is_string($_GET['desde'] ?? null) && mg_fecha_valida($_GET['desde']) ? $_GET['desde'] : date('Y-m-d');
$hasta = is_string($_GET['hasta'] ?? null) && mg_fecha_valida($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d', strtotime($desde . ' +30 days'));
if ($hasta < $desde) {
    $hasta = $desde;
}
$estado = is_string($_GET['estado'] ?? null) && isset(MgDefensa::ESTADOS[$_GET['estado']]) ? $_GET['estado'] : null;
$defensas = (new MgDefensa())->agenda($desde, $hasta, $estado);
$porDia = [];
foreach ($defensas as $defensa) {
    $porDia[$defensa['fecha']][] = $defensa;
}
[$message, $error] = mg_flash([]);

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Agenda de defensas</h1>
            <p>No todos defienden el mismo día: la fecha depende de tribunales, horarios y ambientes (RN-MG-15). Las defensas se programan desde la ficha de cada expediente.</p>
        </div>
    </div>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form method="get" class="card mg-filtros">
        <div><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($desde) ?>"></div>
        <div><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($hasta) ?>"></div>
        <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><?php foreach (MgDefensa::ESTADOS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="mg-filtros-acciones"><button type="submit">Ver</button></div>
    </form>

    <?php if (!$porDia): ?>
        <p class="empty-state card">No hay defensas entre el <?= e(mg_fecha_corta($desde)) ?> y el <?= e(mg_fecha_corta($hasta)) ?>.</p>
    <?php endif; ?>
    <?php foreach ($porDia as $fecha => $lista): ?>
        <?php $sinCitar = count(array_filter($lista, static fn (array $d): bool => $d['estado'] === 'programada' && (int) $d['citaciones'] === 0)); ?>
        <section class="card mg-seccion">
            <div class="section-heading"><div><span class="eyebrow"><?= e(MgDocumento::fechaLarga($fecha)) ?></span><h2><?= count($lista) ?> defensa<?= count($lista) === 1 ? '' : 's' ?></h2></div>
                <?php if ($sinCitar > 0 && Auth::canDo('mg.documentos')): ?>
                    <form method="post" action="<?= e(app_url('mg/defensas/citaciones.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                        <button class="small" type="submit">Generar citaciones del día (<?= $sinCitar ?>)</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Horario</th><th>Ambiente</th><th>Estudiante</th><th>Etapa</th><th>Estado</th><th>Citaciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($lista as $defensa): ?>
                            <tr>
                                <td><?= e(substr((string) $defensa['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $defensa['hora_fin'], 0, 5)) ?></td>
                                <td><?= e($defensa['ambiente']) ?></td>
                                <td><a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $defensa['id_expediente'])) ?>"><?= e($defensa['estudiante']) ?></a><br><small><?= e($defensa['modalidad']) ?> · <?= e($defensa['cohorte']) ?></small></td>
                                <td><?= e(MgTribunal::ETAPAS[$defensa['etapa']]) ?></td>
                                <td><span class="badge badge-<?= e($defensa['estado']) ?>"><?= e(MgDefensa::ESTADOS[$defensa['estado']]) ?></span></td>
                                <td><?= (int) $defensa['citaciones'] > 0 ? (int) $defensa['citaciones'] : ($defensa['estado'] === 'programada' ? '<span class="badge badge-warning">Pendientes</span>' : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
