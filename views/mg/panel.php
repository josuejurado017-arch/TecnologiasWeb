<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Modalidades de Grado</h1>
            <p>Expedientes de Proyecto de Grado, Tesis y Trabajo Dirigido: tutor, reuniones, informes, tribunales, defensas, notas y documentos.</p>
        </div>
        <div class="page-heading-actions">
            <?php if (Auth::canDo('mg.expediente')): ?><a class="button" href="<?= e(app_url('mg/expedientes/nuevo.php')) ?>">Nuevo expediente</a><?php endif; ?>
            <?php if (Auth::canDo('mg.importar')): ?><a class="button secondary" href="<?= e(app_url('mg/importar.php')) ?>">Importar padrón</a><?php endif; ?>
        </div>
    </div>

    <section class="stat-grid" aria-label="Indicadores">
        <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Expedientes activos</span><span class="stat-icon">EX</span></div><strong class="stat-value"><?= (int) $panel['activos'] ?></strong><span class="stat-caption"><?= (int) ($panel['por_etapa']['previa'] ?? 0) ?> previa · <?= (int) ($panel['por_etapa']['mg1'] ?? 0) ?> MG1 · <?= (int) ($panel['por_etapa']['mg2'] ?? 0) ?> MG2</span></article>
        <?php if (Auth::canDo('mg.alertas')): ?>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Alertas altas</span><span class="stat-icon">AL</span></div><strong class="stat-value"><?= count($alertasAltas) ?></strong><span class="stat-caption"><a href="<?= e(app_url('mg/alertas.php')) ?>"><?= count($alertasAbiertas) ?> abiertas en total</a></span></article>
        <?php else: ?>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Sin tutor</span><span class="stat-icon">TU</span></div><strong class="stat-value"><?= count($panel['sin_tutor']) ?></strong><span class="stat-caption">modalidades que requieren tutor</span></article>
        <?php endif; ?>
        <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Defensas</span><span class="stat-icon">DF</span></div><strong class="stat-value"><?= count($panel['proximas']) ?></strong><span class="stat-caption">en los próximos <?= (int) $diasDefensas ?> días</span></article>
        <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Documentos</span><span class="stat-icon">DO</span></div><strong class="stat-value"><?= (int) $panel['documentos_mes'] ?></strong><span class="stat-caption">cartas y citaciones este mes · <a href="<?= e(app_url('mg/reuniones/')) ?>"><?= $reunionesPorValidar ?> reuniones por validar</a></span></article>
    </section>

    <?php if ($alertasAltas): ?>
        <section class="card">
            <div class="section-heading"><div><span class="eyebrow">HU-038 · prioridad</span><h2>Alertas altas abiertas</h2></div><a href="<?= e(app_url('mg/alertas.php?severidad=alta')) ?>">Ver todas</a></div>
            <ul class="mg-lista">
                <?php foreach (array_slice($alertasAltas, 0, 8) as $alerta): ?>
                    <li><?= MgAlerta::badge('alta') ?> <strong><?= e($alerta['titulo']) ?></strong> · <?php if ($alerta['estudiante']): ?><a href="<?= e(app_url($alerta['url'])) ?>"><?= e($alerta['estudiante']) ?></a> · <?php endif; ?><?= e($alerta['detalle']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($alertasAltas) > 8): ?><p class="panel-note">Y <?= count($alertasAltas) - 8 ?> más.</p><?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="mg-grid">
        <article class="card chart-card">
            <div class="section-heading"><div><span class="eyebrow">Expedientes activos</span><h2>Por etapa</h2></div></div>
            <div class="chart-wrap"><canvas data-dashboard-chart data-chart-type="doughnut" data-chart-data="<?= e(json_encode($graficoEtapas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Expedientes activos por etapa" role="img"></canvas></div>
            <p class="panel-note"><?= e(implode(' · ', array_map(static fn ($l, $v): string => $l . ': ' . $v, $graficoEtapas['labels'], $graficoEtapas['values']))) ?></p>
        </article>
        <article class="card chart-card">
            <div class="section-heading"><div><span class="eyebrow">C-01 · referencia <?= (int) MgParametro::entero('tutor_carga_recomendada', 3) ?></span><h2>Carga por tutor</h2></div></div>
            <?php if (!$carga): ?>
                <p class="empty-state">Aún no hay tutores asignados.</p>
            <?php else: ?>
                <div class="chart-wrap"><canvas data-dashboard-chart data-chart-type="bar" data-chart-data="<?= e(json_encode($graficoCarga, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Tesistas vigentes por tutor" role="img"></canvas></div>
                <p class="panel-note"><?= e(implode(' · ', array_map(static fn (array $c): string => $c['docente'] . ': ' . $c['carga'], $carga))) ?></p>
            <?php endif; ?>
        </article>
    </div>

    <div class="mg-grid">
        <section class="card">
            <div class="section-heading"><div><span class="eyebrow">Agenda</span><h2>Próximas defensas</h2></div><a href="<?= e(app_url('mg/defensas/')) ?>">Ver agenda</a></div>
            <?php if (!$panel['proximas']): ?>
                <p class="empty-state">No hay defensas programadas en los próximos <?= (int) $diasDefensas ?> días.</p>
            <?php else: ?>
                <ul class="mg-lista">
                    <?php foreach ($panel['proximas'] as $defensa): ?>
                        <li>
                            <a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $defensa['id_expediente'])) ?>"><strong><?= e($defensa['estudiante']) ?></strong></a>
                            · <?= e(MgTribunal::ETAPAS[$defensa['etapa']]) ?> · <?= e(mg_fecha_corta($defensa['fecha'])) ?> <?= e(substr((string) $defensa['hora_inicio'], 0, 5)) ?> · <?= e($defensa['ambiente']) ?>
                            <?php if ($defensa['tribunal_urgente']): ?><span class="badge badge-danger">Faltan tribunales</span><?php elseif ($defensa['faltan_tribunales']): ?><span class="badge badge-warning">Faltan tribunales</span><?php endif; ?>
                            <?php if ((int) $defensa['citaciones'] === 0): ?><span class="badge badge-warning">Sin citaciones</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="section-heading"><div><span class="eyebrow">Pendiente</span><h2>Expedientes sin tutor</h2></div></div>
            <?php if (!$panel['sin_tutor']): ?>
                <p class="empty-state">Todos los expedientes que requieren tutor lo tienen.</p>
            <?php else: ?>
                <ul class="mg-lista">
                    <?php foreach ($panel['sin_tutor'] as $expediente): ?>
                        <li><a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $expediente['id_expediente'])) ?>"><strong><?= e($expediente['estudiante']) ?></strong></a> · <?= e($expediente['modalidad']) ?> · <?= e($expediente['cohorte']) ?> <?= mg_badge_etapa((string) $expediente['etapa_actual']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($panel['sobrecarga']): ?>
        <section class="card mg-seccion">
            <div class="section-heading"><div><span class="eyebrow">C-01 · advertencia</span><h2>Tutores sobre la carga recomendada</h2></div></div>
            <p class="panel-note">Carga recomendada: <?= (int) MgParametro::entero('tutor_carga_recomendada', 3) ?> tesistas vigentes. No es un límite: el máximo formal está pendiente de validar con el Coordinador.</p>
            <ul class="mg-lista">
                <?php foreach ($panel['sobrecarga'] as $docente): ?>
                    <li><a href="<?= e(app_url('mg/expedientes/?id_tutor=' . (int) $docente['id_tutor'])) ?>"><strong><?= e($docente['docente']) ?></strong></a> · <?= (int) $docente['carga'] ?> tesistas</li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
