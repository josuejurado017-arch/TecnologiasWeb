<?php

// Seguimiento de un expediente (HU-034/035/037): reuniones e informes de avance.
// La ven el equipo MG, el tutor vigente (registra) y el estudiante titular (consulta).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();

$id = (int) filter_input(INPUT_GET, 'expediente', FILTER_VALIDATE_INT);
$expediente = $id ? (new MgExpediente())->find($id) : null;
$acceso = $expediente ? MgSeguimientoController::acceso($expediente) : null;
if ($expediente === null || $acceso === null) {
    http_response_code($expediente === null ? 404 : 403);
    exit($expediente === null ? 'Expediente no encontrado.' : 'No tiene permisos para ver este expediente.');
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

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Seguimiento de <?= e($expediente['estudiante']) ?></h1>
            <p><?= e($expediente['modalidad']) ?> · <?= e($expediente['cohorte']) ?> · <?= mg_badge_etapa((string) $expediente['etapa_actual']) ?> <?= mg_badge_estado((string) $expediente['estado']) ?> · Tutor: <?= e((string) ($expediente['tutor'] ?? 'sin asignar')) ?></p>
        </div>
        <div class="page-heading-actions">
            <?php if ($puedeRegistrar): ?><a class="button" href="<?= e(app_url('mg/reuniones/form.php?expediente=' . $id)) ?>">Registrar reunión</a><?php endif; ?>
            <a class="button secondary" href="<?= e(app_url($volver)) ?>">Volver</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($aviso)): ?><p class="notice-warning mg-aviso" role="status"><?= nl2br(e($aviso)) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($alertas): ?>
        <section class="card mg-seccion">
            <div class="section-heading"><div><span class="eyebrow">HU-038</span><h2>Alertas abiertas</h2></div>
                <?php if (Auth::canDo('mg.alertas')): ?><a href="<?= e(app_url('mg/alertas.php?q=' . rawurlencode($expediente['estudiante']))) ?>">Atender en el panel</a><?php endif; ?></div>
            <ul class="mg-lista">
                <?php foreach ($alertas as $alerta): ?><li><?= MgAlerta::badge($alerta['severidad']) ?> <strong><?= e($alerta['titulo']) ?></strong> · <?= e($alerta['detalle']) ?></li><?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="stat-grid mg-seccion" aria-label="Resumen de reuniones">
        <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Reuniones</span><span class="stat-icon">RE</span></div><strong class="stat-value"><?= (int) ($resumen['total'] ?? 0) ?></strong><span class="stat-caption"><?= (int) ($resumen['validadas'] ?? 0) ?> validadas · <?= (int) ($resumen['por_validar'] ?? 0) ?> por validar · <?= (int) ($resumen['observadas'] ?? 0) ?> observadas</span></article>
        <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Inasistencias</span><span class="stat-icon">AS</span></div><strong class="stat-value"><?= (int) ($resumen['faltas_estudiante'] ?? 0) ?></strong><span class="stat-caption">del estudiante · <?= (int) ($resumen['faltas_tutor'] ?? 0) ?> del tutor</span></article>
        <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Última reunión</span><span class="stat-icon">UL</span></div><strong class="stat-value"><?= e(mg_fecha_corta($resumen['ultima'] ?? null)) ?></strong><span class="stat-caption">mínimo MG1: <?= (int) MgParametro::entero('reuniones_min_semana_perfil', 2) ?> por semana</span></article>
        <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Informes</span><span class="stat-icon">IN</span></div><strong class="stat-value"><?= count(array_filter($informes, static fn (array $h): bool => in_array($h['estado_informe'], ['presentado', 'tarde'], true))) ?>/<?= count(array_filter($informes, static fn (array $h): bool => $h['estado_informe'] !== 'no_aplica')) ?></strong><span class="stat-caption">presentados de los que aplican</span></article>
    </section>

    <section class="card mg-seccion" id="informes">
        <div class="section-heading"><div><span class="eyebrow">HU-037</span><h2>Informes de avance</h2></div></div>
        <?php if ((int) $expediente['requiere_tutor'] !== 1): ?>
            <p class="panel-note">La modalidad <?= e($expediente['modalidad']) ?> no presenta informes de avance.</p>
        <?php elseif (!$informes): ?>
            <p class="empty-state">La cohorte aún no tiene hitos de informe en su calendario.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Hito</th><th>Fecha límite</th><th>Estado</th><th>Presentado</th><th>Avance</th><th>Formato</th><?php if ($puedeInforme): ?><th>Acciones</th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php foreach ($informes as $hito): ?>
                            <tr>
                                <td><?= e(MgCatalogo::ETAPAS_HITO[$hito['etapa']] ?? $hito['etapa']) ?> · <?= e($hito['nombre']) ?></td>
                                <td><?= e(mg_fecha_corta($hito['fecha_limite'])) ?></td>
                                <td><?= MgInforme::badge($hito['estado_informe']) ?></td>
                                <td><?= $hito['id_informe'] ? e(mg_fecha_corta($hito['fecha_presentacion'])) . '<br><small>por ' . e((string) ($hito['presentado_por_nombre'] ?? '—')) . '</small>' : '—' ?></td>
                                <td><?php if ($hito['id_informe']): ?><?= (int) $hito['porcentaje_avance'] ?>%<?php if ($hito['avance_esperado_pct'] !== null): ?> <small>(esperado ~<?= (int) $hito['avance_esperado_pct'] ?>%)</small><?php endif; ?><?php if ($hito['bajo_esperado']): ?> <span class="badge badge-warning">Bajo lo esperado</span><?php endif; ?><?php else: ?>—<?php endif; ?></td>
                                <td><?= $hito['id_informe'] ? e(MgInforme::FORMATOS[$hito['formato']]) . ((int) $hito['respaldo_fisico'] === 1 ? ' · respaldo impreso' : '') : '—' ?></td>
                                <?php if ($puedeInforme): ?>
                                    <td><?php if ($hito['id_informe'] || $expediente['estado'] === 'activo'): ?><a href="<?= e(app_url('mg/informes/form.php?expediente=' . $id . '&hito=' . (int) $hito['id_hito'])) ?>"><?= $hito['id_informe'] ? 'Corregir' : 'Registrar' ?></a><?php endif; ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($hito['obs_informe']): ?><tr><td colspan="<?= $puedeInforme ? 7 : 6 ?>"><small><strong>Observaciones:</strong> <?= e($hito['obs_informe']) ?></small></td></tr><?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="panel-note">Un informe tardío o con poco avance no se rechaza ni cambia el estado del expediente: solo genera una alerta para la Coordinación (RN-MG-13, RN-MG-22).</p>
        <?php endif; ?>
    </section>

    <section class="card mg-seccion" id="reuniones">
        <div class="section-heading"><div><span class="eyebrow">HU-034/035</span><h2>Reuniones</h2></div></div>
        <?php if (!$reuniones): ?>
            <p class="empty-state">Sin reuniones registradas.<?= $puedeRegistrar ? ' Registra cada reunión cuando termine (hasta ' . (int) MgParametro::entero('plazo_registro_reunion_dias', 7) . ' días después).' : '' ?></p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Fecha</th><th>Modalidad</th><th>Temas y avance</th><th>Asistencia</th><th>Validación</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($reuniones as $reunion): ?>
                            <?php $editableTutor = $acceso === 'tutor' && $reunion['estado_validacion'] !== 'validada' && (int) $reunion['id_usuario_tutor'] === $userId && (int) $reunion['id_asignacion'] === (int) $expediente['id_asignacion']; ?>
                            <tr>
                                <td><?= e(mg_fecha_corta($reunion['fecha'])) ?><br><small><?= e(substr((string) $reunion['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $reunion['hora_fin'], 0, 5)) ?> · <?= e($reunion['tutor']) ?></small></td>
                                <td><?= e(MgReunion::MODALIDADES[$reunion['modalidad']]) ?><br><small><?= e($reunion['lugar_o_enlace']) ?></small></td>
                                <td><?= e($reunion['temas']) ?><?php if ($reunion['avance_sesion']): ?><br><small><strong>Avance:</strong> <?= e($reunion['avance_sesion']) ?></small><?php endif; ?><?php if ($reunion['observaciones']): ?><br><small><strong>Obs.:</strong> <?= e($reunion['observaciones']) ?></small><?php endif; ?></td>
                                <td><small>Estudiante: <?= $reunion['asistio_estudiante'] === 'si' ? 'sí' : '<strong>no</strong>' ?><br>Tutor: <?= $reunion['asistio_tutor'] === 'si' ? 'sí' : '<strong>no</strong>' ?></small></td>
                                <td><span class="badge <?= $reunion['estado_validacion'] === 'validada' ? 'badge-success' : ($reunion['estado_validacion'] === 'observada' ? 'badge-danger' : 'badge-warning') ?>"><?= e(MgReunion::ESTADOS[$reunion['estado_validacion']]) ?></span>
                                    <?php if ($reunion['motivo_observacion'] && $reunion['estado_validacion'] === 'observada'): ?><br><small><?= e($reunion['motivo_observacion']) ?></small><?php endif; ?>
                                    <?php if ($reunion['validador']): ?><br><small><?= e($reunion['validador']) ?> · <?= e(mg_fecha_corta($reunion['fecha_validacion'])) ?></small><?php endif; ?></td>
                                <td class="actions mg-acciones">
                                    <?php if ($editableTutor || $puedeValidar): ?><a href="<?= e(app_url('mg/reuniones/form.php?id=' . (int) $reunion['id_reunion'])) ?>"><?= $puedeValidar ? 'Corregir' : 'Editar' ?></a><?php endif; ?>
                                    <?php if ($puedeValidar && $reunion['estado_validacion'] !== 'validada'): ?>
                                        <form method="post" action="<?= e(app_url('mg/reuniones/validar.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $reunion['id_reunion'] ?>">
                                            <input type="hidden" name="accion" value="validar">
                                            <input type="hidden" name="volver" value="seguimiento">
                                            <button class="link-button mg-link" type="submit">Validar</button>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="panel-note">Sin fotos ni archivos: la evidencia digital de reuniones está pendiente de definir con el Coordinador (C-03, P3). En una reunión virtual se guarda el enlace o ID de Teams como referencia.</p>
    </section>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
