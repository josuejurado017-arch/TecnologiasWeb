<?php

// Seguimiento de un expediente (HU-034/035/037): reuniones e informes de avance.
// La ven el equipo MG, el tutor vigente (registra) y el estudiante titular (consulta).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();

$id = (int) filter_input(INPUT_GET, 'expediente', FILTER_VALIDATE_INT);
$expediente = $id ? (new MgExpediente())->find($id) : null;
$acceso = $expediente ? MgSeguimientoController::acceso($expediente) : null;
if ($expediente === null) {
    http_response_code(404);
    exit('Expediente no encontrado.');
}
if ($acceso === null) {
    Auth::denegar('No tienes permisos para ver el seguimiento de este expediente.');
}
$activePage = $acceso === 'tutor' ? 'mg-mis-tesistas' : ($acceso === 'estudiante' ? 'mg-mi-modalidad' : 'mg-expedientes');

[$message, $error, $aviso] = mg_flash([
    'reunion' => 'Reunión registrada. Queda por validar por la Coordinación.',
    'reunion_editada' => 'Reunión actualizada.',
    'validada' => 'Reunión validada.',
    'observada' => 'Reunión observada. Se notificó al tutor.',
    'informe' => 'Informe de avance guardado.',
]);

$reuniones = (new MgReunion())->porExpediente($id);
$resumen = (new MgReunion())->resumen($id);
$informes = (new MgInforme())->porExpediente($expediente);
$alertas = $acceso === 'estudiante' ? [] : (new MgAlerta())->delExpediente($id);
$enCurso = $expediente['estado'] === 'activo' && in_array($expediente['etapa_actual'], ['mg1', 'mg2'], true);
$puedeRegistrar = $acceso === 'tutor' && $enCurso;
$puedeInforme = ($acceso === 'tutor' || ($acceso === 'gestion' && Auth::canDo('mg.informe'))) && (int) $expediente['requiere_tutor'] === 1;
$puedeValidar = Auth::canDo('mg.validar');
$userId = (int) Auth::user()['id_usuario'];
$volver = $acceso === 'tutor' ? 'mg/mis-tesistas.php' : ($acceso === 'estudiante' ? 'mg/mi-modalidad.php' : 'mg/expedientes/ver.php?id=' . $id);
$title = 'Seguimiento · ' . $expediente['estudiante'];

// Datos para la ficha y el resumen.
$informesAplican = array_values(array_filter($informes, static fn (array $h): bool => $h['estado_informe'] !== 'no_aplica'));
$informesPresentados = array_filter($informesAplican, static fn (array $h): bool => in_array($h['estado_informe'], ['presentado', 'tarde'], true));
$informesVencidos = array_filter($informesAplican, static fn (array $h): bool => $h['estado_informe'] === 'no_presentado');
$proximoInforme = null;
foreach ($informesAplican as $hito) {
    if ($hito['estado_informe'] === 'pendiente' && ($proximoInforme === null || $hito['fecha_limite'] < $proximoInforme['fecha_limite'])) {
        $proximoInforme = $hito;
    }
}
$porValidar = (int) ($resumen['por_validar'] ?? 0);
$observadas = (int) ($resumen['observadas'] ?? 0);
$altas = count(array_filter($alertas, static fn (array $a): bool => $a['severidad'] === 'alta'));
$badgeReunion = static fn (string $estado): string => $estado === 'validada' ? 'badge-success' : ($estado === 'observada' ? 'badge-danger' : 'badge-warning');
$asiste = static fn (string $v, string $quien): string => '<span class="mg-asiste ' . ($v === 'si' ? 'is-si' : 'is-no') . '" title="' . e($quien) . ($v === 'si' ? ' asistió' : ' no asistió') . '">'
    . ($v === 'si' ? '✓' : '✗') . ' ' . e($quien) . '</span>';

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Seguimiento de <?= e($expediente['estudiante']) ?></h1>
            <p><?= e($expediente['modalidad']) ?><?= $expediente['titulo_trabajo'] ? ' · «' . e($expediente['titulo_trabajo']) . '»' : '' ?></p>
        </div>
        <div class="page-heading-actions">
            <?php if ($puedeRegistrar): ?><a class="button" href="<?= e(app_url('mg/reuniones/form.php?expediente=' . $id)) ?>">Registrar reunión</a><?php endif; ?>
            <a class="button secondary" href="<?= e(app_url($volver)) ?>">Volver</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($aviso)): ?><p class="notice-warning mg-aviso" role="status"><?= nl2br(e($aviso)) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <dl class="card mg-ficha">
        <div><dt>Etapa</dt><dd><?= mg_badge_etapa((string) $expediente['etapa_actual']) ?> <?= mg_badge_estado((string) $expediente['estado']) ?></dd></div>
        <div><dt>Cohorte</dt><dd><?= e($expediente['cohorte']) ?></dd></div>
        <div><dt>Tutor</dt><dd><?= $expediente['tutor'] ? e($expediente['tutor']) : '<span class="badge badge-danger">Sin tutor</span>' ?></dd></div>
        <div><dt>Última reunión</dt><dd><?= $resumen['ultima'] ?? null ? e(mg_fecha_corta($resumen['ultima'])) : 'Ninguna' ?></dd></div>
        <div><dt>Próximo informe</dt><dd><?= $proximoInforme ? e($proximoInforme['nombre']) . '<br><small>vence el ' . e(mg_fecha_corta($proximoInforme['fecha_limite'])) . '</small>' : '—' ?></dd></div>
    </dl>

    <div class="mg-pestanas" data-tabs>
        <div class="mg-pestanas-barra" role="tablist" aria-label="Secciones del seguimiento">
            <button type="button" role="tab" data-tab="resumen">Resumen</button>
            <button type="button" role="tab" data-tab="reuniones">Reuniones <span class="mg-contador"><?= (int) ($resumen['total'] ?? 0) ?></span><?php if ($porValidar + $observadas > 0): ?> <span class="mg-contador is-aviso" title="por validar u observadas"><?= $porValidar + $observadas ?></span><?php endif; ?></button>
            <button type="button" role="tab" data-tab="informes">Informes <span class="mg-contador"><?= count($informesPresentados) ?>/<?= count($informesAplican) ?></span></button>
            <?php if ($acceso !== 'estudiante'): ?>
                <button type="button" role="tab" data-tab="alertas">Alertas <span class="mg-contador<?= $alertas ? ($altas ? ' is-alta' : ' is-aviso') : '' ?>"><?= count($alertas) ?></span></button>
            <?php endif; ?>
        </div>

        <section class="mg-pestana" id="resumen" data-panel="resumen" role="tabpanel">
            <div class="stat-grid" aria-label="Resumen">
                <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Reuniones</span><span class="stat-icon">RE</span></div><strong class="stat-value"><?= (int) ($resumen['total'] ?? 0) ?></strong><span class="stat-caption"><?= (int) ($resumen['validadas'] ?? 0) ?> validadas · <?= $porValidar ?> por validar · <?= $observadas ?> observadas</span></article>
                <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Inasistencias</span><span class="stat-icon">AS</span></div><strong class="stat-value"><?= (int) ($resumen['faltas_estudiante'] ?? 0) ?></strong><span class="stat-caption">del estudiante · <?= (int) ($resumen['faltas_tutor'] ?? 0) ?> del tutor</span></article>
                <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Informes</span><span class="stat-icon">IN</span></div><strong class="stat-value"><?= count($informesPresentados) ?>/<?= count($informesAplican) ?></strong><span class="stat-caption">presentados · <?= count($informesVencidos) ?> vencidos sin presentar</span></article>
                <?php if ($acceso !== 'estudiante'): ?>
                    <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Alertas abiertas</span><span class="stat-icon">AL</span></div><strong class="stat-value"><?= count($alertas) ?></strong><span class="stat-caption"><?= $altas ?> de severidad alta</span></article>
                <?php endif; ?>
            </div>

            <div class="card mg-seccion">
                <h2>Qué requiere atención</h2>
                <?php
                $pendientes = [];
                foreach (array_slice($alertas, 0, 4) as $alerta) {
                    $pendientes[] = MgAlerta::badge($alerta['severidad']) . ' <strong>' . e($alerta['titulo']) . '</strong> · ' . e($alerta['detalle']);
                }
                if ($porValidar > 0 && $puedeValidar) {
                    $pendientes[] = '<span class="badge badge-warning">Reuniones</span> ' . $porValidar . ' reunión(es) esperan tu validación.';
                } elseif ($porValidar > 0) {
                    $pendientes[] = '<span class="badge badge-warning">Reuniones</span> ' . $porValidar . ' reunión(es) esperan la validación de la Coordinación.';
                }
                if ($observadas > 0) {
                    $pendientes[] = '<span class="badge badge-danger">Reuniones</span> ' . $observadas . ' reunión(es) observada(s): el tutor debe corregirlas.';
                }
                if ($proximoInforme !== null) {
                    $pendientes[] = '<span class="badge badge-info">Informe</span> ' . e($proximoInforme['nombre']) . ' vence el ' . e(mg_fecha_corta($proximoInforme['fecha_limite'])) . '.';
                }
                ?>
                <?php if ($pendientes): ?>
                    <ul class="mg-lista"><?php foreach ($pendientes as $item): ?><li><?= $item ?></li><?php endforeach; ?></ul>
                <?php else: ?>
                    <p class="empty-state">Todo al día: sin alertas ni pendientes.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="mg-pestana card" id="reuniones" data-panel="reuniones" role="tabpanel">
            <div class="mg-pestana-cabecera">
                <h2>Reuniones</h2>
                <?php if ($reuniones): ?>
                    <div class="mg-chips" data-filtro-reuniones>
                        <button type="button" class="is-activo" data-estado="">Todas (<?= count($reuniones) ?>)</button>
                        <button type="button" data-estado="registrada">Por validar (<?= $porValidar ?>)</button>
                        <button type="button" data-estado="observada">Observadas (<?= $observadas ?>)</button>
                        <button type="button" data-estado="validada">Validadas (<?= (int) ($resumen['validadas'] ?? 0) ?>)</button>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!$reuniones): ?>
                <p class="empty-state">Sin reuniones registradas.<?= $puedeRegistrar ? ' Registra cada reunión cuando termine (hasta ' . (int) MgParametro::entero('plazo_registro_reunion_dias', 7) . ' días después).' : '' ?></p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="mg-tabla-reuniones">
                        <thead><tr><th>Fecha</th><th>Temas y avance</th><th>Asistencia</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($reuniones as $reunion): ?>
                                <?php $editableTutor = $acceso === 'tutor' && $reunion['estado_validacion'] !== 'validada' && (int) $reunion['id_usuario_tutor'] === $userId && (int) $reunion['id_asignacion'] === (int) $expediente['id_asignacion']; ?>
                                <tr data-estado="<?= e($reunion['estado_validacion']) ?>">
                                    <td><strong><?= e(mg_fecha_corta($reunion['fecha'])) ?></strong><br><small><?= e(substr((string) $reunion['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $reunion['hora_fin'], 0, 5)) ?> · <?= e(MgReunion::MODALIDADES[$reunion['modalidad']]) ?></small></td>
                                    <td><?= e($reunion['temas']) ?>
                                        <?php if ($reunion['avance_sesion'] || $reunion['observaciones']): ?>
                                            <br><small class="mg-texto-suave"><?= $reunion['avance_sesion'] ? 'Avance: ' . e($reunion['avance_sesion']) : '' ?><?= $reunion['observaciones'] ? ($reunion['avance_sesion'] ? ' · ' : '') . 'Obs.: ' . e($reunion['observaciones']) : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mg-asistencia"><?= $asiste((string) $reunion['asistio_estudiante'], 'Estudiante') ?><?= $asiste((string) $reunion['asistio_tutor'], 'Tutor') ?></td>
                                    <td><span class="badge <?= $badgeReunion((string) $reunion['estado_validacion']) ?>"><?= e(MgReunion::ESTADOS[$reunion['estado_validacion']]) ?></span>
                                        <?php if ($reunion['motivo_observacion'] && $reunion['estado_validacion'] === 'observada'): ?><br><small><?= e($reunion['motivo_observacion']) ?></small><?php endif; ?>
                                        <?php if ($reunion['validador']): ?><br><small class="mg-texto-suave"><?= e($reunion['validador']) ?> · <?= e(mg_fecha_corta($reunion['fecha_validacion'])) ?></small><?php endif; ?></td>
                                    <td class="actions mg-acciones">
                                        <?php if ($puedeValidar && $reunion['estado_validacion'] !== 'validada'): ?>
                                            <form method="post" action="<?= e(app_url('mg/reuniones/validar.php')) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $reunion['id_reunion'] ?>">
                                                <input type="hidden" name="accion" value="validar">
                                                <input type="hidden" name="volver" value="seguimiento">
                                                <button class="small" type="submit">Validar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($puedeValidar && $reunion['estado_validacion'] !== 'observada'): ?>
                                            <details><summary>Observar</summary>
                                                <form method="post" action="<?= e(app_url('mg/reuniones/validar.php')) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $reunion['id_reunion'] ?>">
                                                    <input type="hidden" name="accion" value="observar">
                                                    <input type="hidden" name="volver" value="seguimiento">
                                                    <label for="motivo-<?= (int) $reunion['id_reunion'] ?>">Qué debe corregir</label>
                                                    <textarea id="motivo-<?= (int) $reunion['id_reunion'] ?>" name="motivo" rows="2" maxlength="500" required></textarea>
                                                    <button class="small" type="submit">Observar</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                        <?php if ($editableTutor || $puedeValidar): ?><a href="<?= e(app_url('mg/reuniones/form.php?id=' . (int) $reunion['id_reunion'])) ?>"><?= $puedeValidar ? 'Corregir' : 'Editar' ?></a><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <p class="panel-note">Sin fotos ni archivos: la evidencia digital de reuniones está pendiente de definir con el Coordinador. En una reunión virtual se guarda el enlace o ID de Teams como referencia.</p>
        </section>

        <section class="mg-pestana card" id="informes" data-panel="informes" role="tabpanel">
            <h2>Informes de avance</h2>
            <?php if ((int) $expediente['requiere_tutor'] !== 1): ?>
                <p class="panel-note">La modalidad <?= e($expediente['modalidad']) ?> no presenta informes de avance.</p>
            <?php elseif (!$informes): ?>
                <p class="empty-state">La cohorte aún no tiene hitos de informe en su calendario.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Hito</th><th>Vence</th><th>Estado</th><th>Presentado</th><th>Avance</th><?php if ($puedeInforme): ?><th>Acciones</th><?php endif; ?></tr></thead>
                        <tbody>
                            <?php foreach ($informes as $hito): ?>
                                <tr class="<?= $hito['estado_informe'] === 'no_aplica' ? 'mg-fila-tenue' : '' ?>">
                                    <td><strong><?= e($hito['nombre']) ?></strong><br><small class="mg-texto-suave"><?= e(MgCatalogo::ETAPAS_HITO[$hito['etapa']] ?? $hito['etapa']) ?></small>
                                        <?php if ($hito['obs_informe']): ?><br><small>Obs.: <?= e($hito['obs_informe']) ?></small><?php endif; ?></td>
                                    <td><?= e(mg_fecha_corta($hito['fecha_limite'])) ?></td>
                                    <td><?= MgInforme::badge($hito['estado_informe']) ?></td>
                                    <td><?= $hito['id_informe'] ? e(mg_fecha_corta($hito['fecha_presentacion'])) . '<br><small class="mg-texto-suave">' . e(MgInforme::FORMATOS[$hito['formato']]) . ((int) $hito['respaldo_fisico'] === 1 ? ' · respaldo impreso' : '') . '</small>' : '—' ?></td>
                                    <td>
                                        <?php if ($hito['id_informe']): ?>
                                            <?php $pct = (int) $hito['porcentaje_avance']; ?>
                                            <div class="mg-barra" aria-label="Avance <?= $pct ?>%"><span style="width: <?= min(100, $pct) ?>%" class="<?= $hito['bajo_esperado'] ? 'is-bajo' : '' ?>"></span></div>
                                            <small><?= $pct ?>%<?= $hito['avance_esperado_pct'] !== null ? ' · esperado ~' . (int) $hito['avance_esperado_pct'] . '%' : '' ?><?= $hito['bajo_esperado'] ? ' · <strong>bajo lo esperado</strong>' : '' ?></small>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <?php if ($puedeInforme): ?>
                                        <td><?php if ($hito['id_informe'] || $expediente['estado'] === 'activo'): ?><a href="<?= e(app_url('mg/informes/form.php?expediente=' . $id . '&hito=' . (int) $hito['id_hito'])) ?>"><?= $hito['id_informe'] ? 'Corregir' : 'Registrar' ?></a><?php endif; ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="panel-note">Un informe tardío o con poco avance no se rechaza ni cambia el estado del expediente: solo genera una alerta para la Coordinación.</p>
            <?php endif; ?>
        </section>

        <?php if ($acceso !== 'estudiante'): ?>
            <section class="mg-pestana card" id="alertas" data-panel="alertas" role="tabpanel">
                <div class="mg-pestana-cabecera">
                    <h2>Alertas abiertas</h2>
                    <?php if ($alertas && Auth::canDo('mg.alertas')): ?><a class="button small secondary" href="<?= e(app_url('mg/alertas.php?q=' . rawurlencode($expediente['estudiante']))) ?>">Atender en el panel de alertas</a><?php endif; ?>
                </div>
                <?php if (!$alertas): ?>
                    <p class="empty-state">Este expediente no tiene alertas abiertas.</p>
                <?php else: ?>
                    <ul class="mg-alertas-lista">
                        <?php foreach ($alertas as $alerta): ?>
                            <li class="is-<?= e($alerta['severidad']) ?>"><?= MgAlerta::badge($alerta['severidad']) ?> <strong><?= e($alerta['titulo']) ?></strong><br><small><?= e($alerta['detalle']) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
