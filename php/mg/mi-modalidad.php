<?php

// Vista del estudiante (matriz V*): su expediente, tutor, tribunales, defensas y notas publicadas.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireAction('mg.propio');
$title = 'Mi modalidad de grado';
$activePage = 'mg-mi-modalidad';

$modelo = new MgExpediente();
$estudianteId = $modelo->estudianteIdPorUsuario((int) Auth::user()['id_usuario']) ?? 0;
$expedientes = $estudianteId ? $modelo->listar(['id_estudiante' => $estudianteId]) : [];
$defensas = new MgDefensa();
$tribunales = new MgTribunal();
$reunionesModelo = new MgReunion();
$informesModelo = new MgInforme();
$solicitudesModelo = new MgSolicitud();
$solicitudes = $estudianteId ? $solicitudesModelo->porEstudiante($estudianteId) : [];
$solicitudAbierta = null;
foreach ($solicitudes as $fila) {
    if (in_array($fila['estado'], MgSolicitud::ABIERTAS, true)) {
        $solicitudAbierta = $fila;
        break;
    }
}
$perfilEstudiante = $solicitudesModelo->perfilEstudiante((int) Auth::user()['id_usuario']);
$puedeSolicitar = $estudianteId && $solicitudAbierta === null && !$solicitudesModelo->tieneExpedienteActivo($estudianteId)
    && MgSolicitud::semestreHabilitado((int) ($perfilEstudiante['semestre'] ?? 0));
[$message] = mg_flash(['enviada' => 'Solicitud enviada. La Coordinación la revisará y te avisará aquí.', 'reenviada' => 'Solicitud corregida y reenviada a la Coordinación.']);

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Mi modalidad de grado</h1>
            <p>Tu proceso de grado: etapa, tutor, tribunales, defensas y notas publicadas por la Coordinación.</p>
        </div>
    </div>
    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($solicitudes): ?>
        <section class="card mg-seccion">
            <div class="section-heading"><div><span class="eyebrow">Solicitudes</span><h2>Mi solicitud de modalidad de grado</h2></div></div>
            <ul class="mg-lista">
                <?php foreach ($solicitudes as $fila): ?>
                    <li><?= e($fila['modalidad']) ?> (<?= e(MgSolicitud::SITUACIONES[$fila['situacion']] ?? '') ?>) · <?= mg_badge_solicitud((string) $fila['estado']) ?> · enviada el <?= e(mg_fecha_corta($fila['fecha_solicitud'])) ?>
                        <?php if ($fila['estado'] === 'pendiente'): ?> · en revisión por la Coordinación<?php endif; ?>
                        <?php if ($fila['motivo_revision']): ?> · <em><?= e($fila['motivo_revision']) ?></em><?php endif; ?>
                        · <a href="<?= e(app_url('mg/solicitudes/documento.php?id=' . (int) $fila['id_solicitud'])) ?>" target="_blank" rel="noopener">Mi documento</a>
                        <?php if ($fila['estado'] === 'observada'): ?> · <a href="<?= e(app_url('mg/solicitar.php')) ?>"><strong>Corregir y reenviar</strong></a><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    <?php if (!$expedientes): ?>
        <p class="empty-state card">No tienes un expediente de Modalidades de Grado.<?php if ($puedeSolicitar): ?> <a class="button" href="<?= e(app_url('mg/solicitar.php')) ?>">Solicitar modalidad de grado</a><?php endif; ?></p>
    <?php endif; ?>
    <?php foreach ($expedientes as $item): ?>
        <?php $id = (int) $item['id_expediente']; ?>
        <?php $notas = $defensas->notasPorEtapa($id, true); ?>
        <section class="card mg-seccion">
            <div class="section-heading"><div><span class="eyebrow"><?= e($item['cohorte']) ?></span><h2><?= e($item['modalidad']) ?></h2></div><p><?= mg_badge_etapa((string) $item['etapa_actual']) ?> <?= mg_badge_estado((string) $item['estado']) ?></p></div>
            <?php if ($item['titulo_trabajo']): ?><p><em><?= e($item['titulo_trabajo']) ?></em></p><?php endif; ?>
            <p><strong>Tutor(a):</strong> <?= (int) $item['requiere_tutor'] === 1 ? e((string) ($item['tutor'] ?? 'por asignar')) : 'no aplica en esta modalidad' ?></p>
            <?php foreach (['mg1', 'mg2'] as $etapa): ?>
                <?php $lista = $tribunales->vigentes($id, $etapa); ?>
                <?php if ($lista): ?><p><strong>Tribunales <?= e(MgTribunal::ETAPAS[$etapa]) ?>:</strong> <?= e(implode(' · ', array_column($lista, 'docente'))) ?></p><?php endif; ?>
            <?php endforeach; ?>
            <h3 class="mg-subtitulo">Defensas</h3>
            <ul class="mg-lista">
                <?php foreach ($defensas->porExpediente($id) as $defensa): ?>
                    <?php if (in_array($defensa['estado'], ['programada', 'realizada'], true)): ?>
                        <li><?= e(MgTribunal::ETAPAS[$defensa['etapa']]) ?> · <?= e(mg_fecha_corta($defensa['fecha'])) ?> <?= e(substr((string) $defensa['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $defensa['hora_fin'], 0, 5)) ?> · <?= e($defensa['ambiente']) ?> · <span class="badge badge-<?= e($defensa['estado']) ?>"><?= e(MgDefensa::ESTADOS[$defensa['estado']]) ?></span>
                            <?php if ($defensa['nota'] !== null && (int) $defensa['publicada'] === 1): ?> · Nota: <strong><?= e(number_format((float) $defensa['nota'], 2)) ?></strong><?php elseif ($defensa['estado'] === 'realizada'): ?> · Nota pendiente de publicación<?php endif; ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <?php if (count($notas) === 2): ?><p class="panel-note">Promedio simple MG1/MG2: <?= e(number_format(array_sum($notas) / 2, 2)) ?> (provisional).</p><?php endif; ?>
            <?php $resumen = $reunionesModelo->resumen($id); ?>
            <?php $informes = $informesModelo->porExpediente($item); ?>
            <h3 class="mg-subtitulo">Seguimiento</h3>
            <p>Reuniones registradas por tu tutor: <strong><?= (int) ($resumen['total'] ?? 0) ?></strong> · última <?= e(mg_fecha_corta($resumen['ultima'] ?? null)) ?> · inasistencias tuyas: <?= (int) ($resumen['faltas_estudiante'] ?? 0) ?></p>
            <?php if ($informes): ?>
                <ul class="mg-lista">
                    <?php foreach ($informes as $hito): ?>
                        <li><?= e($hito['nombre']) ?> · vence <?= e(mg_fecha_corta($hito['fecha_limite'])) ?> · <?= MgInforme::badge($hito['estado_informe']) ?><?= $hito['id_informe'] ? ' · ' . (int) $hito['porcentaje_avance'] . '%' : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p><a href="<?= e(app_url('mg/seguimiento.php?expediente=' . $id)) ?>">Ver el detalle de reuniones e informes</a></p>
        </section>
    <?php endforeach; ?>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
