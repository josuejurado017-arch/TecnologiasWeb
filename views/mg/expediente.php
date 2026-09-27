<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1><?= e($expediente['estudiante']) ?></h1>
            <p>R.U. <?= e((string) $expediente['registro_universitario']) ?> · <?= e($expediente['carrera']) ?> · <?= e($expediente['modalidad']) ?><?php if ((int) $expediente['regla_por_validar'] === 1): ?> <span class="badge badge-warning">Regla por validar</span><?php endif; ?> · <?= e($expediente['cohorte']) ?></p>
            <p><?= mg_badge_etapa((string) $expediente['etapa_actual']) ?> <?= mg_badge_estado((string) $expediente['estado']) ?> · Inicio <?= e(mg_fecha_corta($expediente['fecha_inicio'])) ?><?= $expediente['fecha_cierre'] ? ' · Cierre ' . e(mg_fecha_corta($expediente['fecha_cierre'])) : '' ?></p>
        </div>
        <div class="page-heading-actions">
            <a class="button secondary" href="<?= e(app_url('mg/expedientes/reporte.php?id=' . (int) $expediente['id_expediente'])) ?>">Reporte imprimible</a>
            <a class="button secondary" href="<?= e(app_url('mg/expedientes/')) ?>">Volver</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($aviso)): ?><p class="notice-warning mg-aviso" role="status"><?= nl2br(e($aviso)) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($documentoNuevo): ?><p class="success" role="status"><a href="<?= e(app_url('mg/documentos/ver.php?id=' . $documentoNuevo)) ?>" target="_blank" rel="noopener">Abrir la carta de asignación para imprimir</a></p><?php endif; ?>

    <nav class="mg-tabs" aria-label="Secciones del expediente">
        <a href="#tutor">Tutor</a><a href="#tribunales">Tribunales y defensas</a><a href="#documentos">Documentos</a><a href="#estado">Estado y etapa</a><a href="#datos">Datos</a><a href="#seguimiento">Reuniones e informes</a>
    </nav>

    <div class="mg-grid">
        <section class="card" id="tutor">
            <div class="section-heading"><div><span class="eyebrow">HU-025/026</span><h2>Tutor</h2></div>
                <?php if (Auth::canDo('mg.tutor') && $activo && (int) $expediente['requiere_tutor'] === 1): ?><a class="button small" href="<?= e(app_url('mg/expedientes/tutor.php?id=' . (int) $expediente['id_expediente'])) ?>"><?= $tutorVigente ? 'Cambiar tutor' : 'Asignar tutor' ?></a><?php endif; ?>
            </div>
            <?php if ((int) $expediente['requiere_tutor'] !== 1): ?>
                <p class="panel-note">La modalidad <?= e($expediente['modalidad']) ?> no usa tutor (RN-MG-01).</p>
            <?php elseif ($tutorVigente): ?>
                <p><strong><?= e($tutorVigente['tutor']) ?></strong><?= $tutorVigente['especialidad'] ? ' · ' . e($tutorVigente['especialidad']) : '' ?><br>
                    <small>Desde <?= e(mg_fecha_corta($tutorVigente['fecha_asignacion'])) ?> · Decanatura: <?= e((string) $tutorVigente['referencia_decanatura']) ?></small></p>
            <?php else: ?>
                <p><span class="badge badge-warning">Sin tutor asignado</span></p>
            <?php endif; ?>
            <?php if (count($historialTutores) > 1 || ($historialTutores && !$tutorVigente)): ?>
                <h3 class="mg-subtitulo">Línea de tiempo</h3>
                <ol class="mg-timeline">
                    <?php foreach ($historialTutores as $item): ?>
                        <li><strong><?= e($item['tutor']) ?></strong> · <?= e(MgAsignacion::ESTADOS[$item['estado']]) ?> · <?= e(mg_fecha_corta($item['fecha_asignacion'])) ?><?= $item['fecha_fin'] ? ' al ' . e(mg_fecha_corta($item['fecha_fin'])) : '' ?>
                            <?php if ($item['motivo_fin']): ?><br><small>Motivo: <?= e($item['motivo_fin']) ?><?= $item['fecha_nota_renuncia'] ? ' · nota del ' . e(mg_fecha_corta($item['fecha_nota_renuncia'])) : '' ?></small><?php endif; ?>
                            <br><small>Decanatura: <?= e((string) $item['referencia_decanatura']) ?> · registró <?= e((string) $item['registrado']) ?></small></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section class="card" id="tribunales">
            <div class="section-heading"><div><span class="eyebrow">HU-028</span><h2>Tribunales</h2></div>
                <?php if (Auth::canDo('mg.tribunal') && $enDefensa): ?><a class="button small" href="<?= e(app_url('mg/expedientes/tribunales.php?id=' . (int) $expediente['id_expediente'] . '&etapa=' . e($expediente['etapa_actual']))) ?>">Tribunales de <?= e(MgTribunal::ETAPAS[$expediente['etapa_actual']]) ?></a><?php endif; ?>
            </div>
            <?php foreach ($tribunales as $etapa => $lista): ?>
                <p><strong><?= e(MgTribunal::ETAPAS[$etapa]) ?>:</strong>
                    <?php if (!$lista): ?><small>Sin tribunales.</small><?php else: ?><?= e(implode(' · ', array_map(static fn (array $t): string => $t['orden'] . '. ' . $t['docente'], $lista))) ?><?php endif; ?>
                    <?php if ($tutorVigente && in_array((int) $tutorVigente['id_tutor'], array_map(static fn (array $t): int => (int) $t['id_tutor'], $lista), true)): ?><span class="badge badge-warning" title="RN-MG-21">Tutor también es tribunal</span><?php endif; ?>
                </p>
            <?php endforeach; ?>
            <?php $reemplazados = array_filter($historialTribunales, static fn (array $t): bool => $t['estado'] === 'reemplazado'); ?>
            <?php if ($reemplazados): ?>
                <details><summary>Tribunales reemplazados (<?= count($reemplazados) ?>)</summary>
                    <ul class="mg-lista"><?php foreach ($reemplazados as $item): ?><li><?= e(MgTribunal::ETAPAS[$item['etapa']]) ?> · puesto <?= (int) $item['orden'] ?> · <?= e($item['docente']) ?><?= $item['motivo_cambio'] ? ' · ' . e($item['motivo_cambio']) : '' ?></li><?php endforeach; ?></ul>
                </details>
            <?php endif; ?>
        </section>
    </div>

    <section class="card mg-seccion">
        <div class="section-heading"><div><span class="eyebrow">HU-029/030/031</span><h2>Defensas y notas</h2></div>
            <?php if (Auth::canDo('mg.defensa') && $enDefensa && !$hayProgramada): ?><a class="button small" href="<?= e(app_url('mg/defensas/programar.php?expediente=' . (int) $expediente['id_expediente'])) ?>">Programar defensa de <?= e(MgTribunal::ETAPAS[$expediente['etapa_actual']]) ?></a><?php endif; ?>
        </div>
        <?php if ($notas): ?>
            <p>Notas: <?php foreach ($notas as $etapa => $nota): ?><strong><?= e(MgTribunal::ETAPAS[$etapa]) ?></strong> <?= e(number_format($nota, 2)) ?> · <?php endforeach; ?>
                <?php if (count($notas) === 2): ?>Promedio simple <strong><?= e(number_format(array_sum($notas) / 2, 2)) ?></strong> <span class="badge badge-warning" title="RN-MG-16: fórmula oficial pendiente">Provisional</span><?php endif; ?></p>
        <?php endif; ?>
        <?php if (!$defensas): ?>
            <p class="empty-state">Sin defensas registradas.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Etapa</th><th>Fecha y hora</th><th>Ambiente</th><th>Estado</th><th>Nota</th><th>Citaciones</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($defensas as $defensa): ?>
                            <tr>
                                <td><?= e(MgTribunal::ETAPAS[$defensa['etapa']]) ?></td>
                                <td><?= e(mg_fecha_corta($defensa['fecha'])) ?> <?= e(substr((string) $defensa['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $defensa['hora_fin'], 0, 5)) ?>
                                    <?php if ($defensa['autorizado_por']): ?><br><small>Autorizado por <?= e(MgDefensasController::AUTORIZACIONES[$defensa['autorizado_por']]) ?>: <?= e((string) $defensa['referencia_autorizacion']) ?></small><?php endif; ?></td>
                                <td><?= e($defensa['ambiente']) ?></td>
                                <td><span class="badge badge-<?= e($defensa['estado']) ?>"><?= e(MgDefensa::ESTADOS[$defensa['estado']]) ?></span><?php if ($defensa['motivo_estado']): ?><br><small><?= e($defensa['motivo_estado']) ?></small><?php endif; ?></td>
                                <td><?= $defensa['nota'] !== null ? e(number_format((float) $defensa['nota'], 2)) . ((int) $defensa['publicada'] === 1 ? ' <span class="badge badge-success">Publicada</span>' : ' <span class="badge badge-borrador">No publicada</span>') : '—' ?></td>
                                <td><?= (int) $defensa['citaciones'] ?></td>
                                <td class="actions mg-acciones">
                                    <?php if ($defensa['estado'] === 'programada' && Auth::canDo('mg.documentos')): ?>
                                        <form method="post" action="<?= e(app_url('mg/defensas/citaciones.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id_defensa" value="<?= (int) $defensa['id_defensa'] ?>">
                                            <button class="link-button mg-link" type="submit"><?= (int) $defensa['citaciones'] > 0 ? 'Regenerar citaciones' : 'Generar citaciones' ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($defensa['estado'] === 'programada' && Auth::canDo('mg.defensa')): ?>
                                        <a href="<?= e(app_url('mg/defensas/programar.php?reprogramar=' . (int) $defensa['id_defensa'])) ?>">Reprogramar</a>
                                        <a href="<?= e(app_url('mg/defensas/estado.php?id=' . (int) $defensa['id_defensa'])) ?>">Realizada / cancelar</a>
                                    <?php endif; ?>
                                    <?php if ($defensa['estado'] === 'realizada' && Auth::canDo('mg.calificacion')): ?>
                                        <a href="<?= e(app_url('mg/defensas/calificar.php?id=' . (int) $defensa['id_defensa'])) ?>"><?= $defensa['nota'] !== null ? 'Corregir nota' : 'Registrar nota' ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($defensa['obs_fondo'] || $defensa['obs_forma']): ?>
                                <tr><td colspan="7"><small><?= $defensa['obs_fondo'] ? '<strong>Fondo:</strong> ' . e($defensa['obs_fondo']) . ' ' : '' ?><?= $defensa['obs_forma'] ? '<strong>Forma:</strong> ' . e($defensa['obs_forma']) : '' ?></small></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card mg-seccion" id="documentos">
        <div class="section-heading"><div><span class="eyebrow">HU-027/030</span><h2>Documentos emitidos</h2></div></div>
        <?php if (!$documentos): ?>
            <p class="empty-state">Aún no se emitieron cartas ni citaciones.</p>
        <?php else: ?>
            <ul class="mg-lista">
                <?php foreach ($documentos as $documento): ?>
                    <li><strong><?= e($documento['numero']) ?></strong> · <?= e($documento['plantilla']) ?> · <?= e($documento['destinatario']) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $documento['fecha_generacion']))) ?>
                        · <a href="<?= e(app_url('mg/documentos/ver.php?id=' . (int) $documento['id_documento'])) ?>" target="_blank" rel="noopener">Ver / imprimir</a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="mg-grid">
        <section class="card" id="estado">
            <div class="section-heading"><div><span class="eyebrow">HU-024</span><h2>Estado y etapa</h2></div></div>
            <?php if (Auth::canDo('mg.expediente')): ?>
                <?php if ($activo && in_array($expediente['etapa_actual'], ['previa', 'mg1'], true)): ?>
                    <form method="post" action="<?= e(app_url('mg/expedientes/accion.php')) ?>" class="mg-bloque">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $expediente['id_expediente'] ?>">
                        <input type="hidden" name="accion" value="etapa">
                        <label for="resultado"><?= $expediente['etapa_actual'] === 'previa' ? 'Pasar a MG1 (perfil)' : 'Registrar ingreso a MG2' ?> · resultado de la etapa (opcional)</label>
                        <input id="resultado" name="resultado" type="text" maxlength="255" placeholder="<?= $expediente['etapa_actual'] === 'mg1' ? 'Ej. Perfil aprobado en defensa MG1' : 'Ej. Talleres completados' ?>">
                        <button type="submit"><?= $expediente['etapa_actual'] === 'previa' ? 'Pasar a MG1' : 'Registrar ingreso a MG2' ?></button>
                        <p class="form-hint">Cierra la etapa actual y abre la siguiente sin perder el historial.<?= $expediente['etapa_actual'] === 'previa' ? ' Asignar tutor también inicia MG1.' : '' ?></p>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(app_url('mg/expedientes/accion.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $expediente['id_expediente'] ?>">
                    <input type="hidden" name="accion" value="estado">
                    <label for="estado">Cambiar estado</label>
                    <select id="estado" name="estado">
                        <?php foreach (MgExpediente::ESTADOS as $valor => $label): ?><?php if ($valor !== $expediente['estado']): ?><option value="<?= e($valor) ?>"><?= e($label) ?><?= $valor === 'activo' ? ' (reabrir)' : '' ?></option><?php endif; ?><?php endforeach; ?>
                    </select>
                    <label for="motivo">Motivo</label>
                    <textarea id="motivo" name="motivo" rows="2" maxlength="500" placeholder="Obligatorio para reprobado, abandono, retirado o reabrir"></textarea>
                    <button type="submit" class="secondary">Cambiar estado</button>
                    <p class="form-hint">Lista de estados provisional. El abandono nunca se declara solo: lo decide la Coordinación (RN-MG-22). Cerrar el expediente finaliza la asignación del tutor.</p>
                </form>
            <?php else: ?>
                <p class="panel-note">Solo lectura.</p>
            <?php endif; ?>
            <h3 class="mg-subtitulo">Historial de etapas</h3>
            <ol class="mg-timeline">
                <?php foreach ($etapas as $item): ?>
                    <li><strong><?= e(MgExpediente::ETAPAS[$item['etapa']]) ?></strong> · <?= e(mg_fecha_corta($item['fecha_inicio'])) ?><?= $item['fecha_fin'] ? ' al ' . e(mg_fecha_corta($item['fecha_fin'])) : ' · en curso' ?><?= $item['resultado'] ? '<br><small>' . e($item['resultado']) . '</small>' : '' ?></li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="card" id="datos">
            <div class="section-heading"><div><span class="eyebrow">Expediente</span><h2>Datos</h2></div></div>
            <?php if (Auth::canDo('mg.expediente')): ?>
                <form method="post" action="<?= e(app_url('mg/expedientes/accion.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $expediente['id_expediente'] ?>">
                    <input type="hidden" name="accion" value="datos">
                    <label for="titulo_trabajo">Título o tema</label>
                    <input id="titulo_trabajo" name="titulo_trabajo" type="text" maxlength="255" value="<?= e((string) $expediente['titulo_trabajo']) ?>">
                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="4" maxlength="1000"><?= e((string) $expediente['observaciones']) ?></textarea>
                    <button type="submit">Guardar datos</button>
                </form>
            <?php else: ?>
                <p><strong>Tema:</strong> <?= e((string) ($expediente['titulo_trabajo'] ?? '—')) ?></p>
                <p><?= nl2br(e((string) $expediente['observaciones'])) ?></p>
            <?php endif; ?>
            <p class="panel-note">Origen: <?= $expediente['origen'] === 'importacion' ? 'importación del padrón' : 'alta manual' ?> · registrado el <?= e(mg_fecha_corta($expediente['fecha_registro'])) ?>.</p>
        </section>
    </div>

    <section class="card mg-seccion" id="seguimiento">
        <div class="section-heading"><div><span class="eyebrow">MVP-2 (P2)</span><h2>Reuniones e informes de avance</h2></div></div>
        <p class="panel-note">El registro de reuniones con asistencia de tutor y estudiante, los informes de avance por hito y las alertas llegan en el Sprint 5. Los hitos de informe ya se cargan en el <a href="<?= e(app_url('mg/cohortes/calendario.php?id=' . (int) $expediente['id_cohorte'])) ?>">calendario de la cohorte</a>.</p>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
