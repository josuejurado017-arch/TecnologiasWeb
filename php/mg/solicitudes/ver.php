<?php

// Detalle de una solicitud: datos del estudiante (con su carnet), documento y decision.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.solicitudes');
$title = 'Solicitud de grado';
$activePage = 'mg-solicitudes';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$modelo = new MgSolicitud();
$solicitud = $id ? $modelo->find($id) : null;
if ($solicitud === null) {
    mg_redirect('solicitudes/', ['error' => 'La solicitud no existe.']);
}
$cohortes = (new MgCatalogo())->cohortes(true);
$historial = array_values(array_filter($modelo->porEstudiante((int) $solicitud['id_estudiante']), static fn (array $s): bool => (int) $s['id_solicitud'] !== $id));
$abierta = in_array($solicitud['estado'], MgSolicitud::ABIERTAS, true);
$documento = app_url('mg/solicitudes/documento.php?id=' . $id);
$esImagen = str_starts_with((string) $solicitud['documento_mime'], 'image/');
[$message, $error] = mg_flash([]);

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Solicitud de <?= e($solicitud['estudiante']) ?></h1>
            <p><?= e($solicitud['modalidad']) ?> · <?= mg_badge_solicitud((string) $solicitud['estado']) ?> · enviada el <?= e(mg_fecha_corta($solicitud['fecha_solicitud'])) ?></p>
        </div>
        <div class="page-heading-actions"><a class="button secondary" href="<?= e(app_url('mg/solicitudes/')) ?>">Volver</a></div>
    </div>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <section class="card mg-seccion">
        <h2>Estudiante</h2>
        <ul class="mg-lista">
            <li><strong>Situación declarada:</strong> <?= e(MgSolicitud::SITUACIONES[$solicitud['situacion']] ?? $solicitud['situacion']) ?></li>
            <li><strong>Carnet de identidad:</strong> <?= e((string) ($solicitud['carnet_identidad'] ?? 'sin registrar')) ?></li>
            <li><strong>R.U.:</strong> <?= e((string) $solicitud['registro_universitario']) ?> · <strong>Carrera:</strong> <?= e($solicitud['carrera']) ?> · <strong>Semestre:</strong> <?= (int) $solicitud['semestre'] ?></li>
            <li><strong>Correo:</strong> <?= e((string) $solicitud['correo_estudiante']) ?><?= $solicitud['telefono'] ? ' · <strong>Teléfono:</strong> ' . e($solicitud['telefono']) : '' ?></li>
            <?php if ($solicitud['titulo_propuesto']): ?><li><strong>Tema propuesto:</strong> <?= e($solicitud['titulo_propuesto']) ?></li><?php endif; ?>
            <?php if ($solicitud['mensaje']): ?><li><strong>Mensaje:</strong> <?= e($solicitud['mensaje']) ?></li><?php endif; ?>
        </ul>
        <?php if ($historial): ?>
            <p class="panel-note">Otras solicitudes de este estudiante:
                <?php foreach ($historial as $otra): ?><a href="<?= e(app_url('mg/solicitudes/ver.php?id=' . (int) $otra['id_solicitud'])) ?>"><?= e($otra['modalidad']) ?> (<?= e(MgSolicitud::ESTADOS[$otra['estado']]) ?>, <?= e(mg_fecha_corta($otra['fecha_solicitud'])) ?>)</a> · <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="card mg-seccion">
        <h2>Documento de respaldo</h2>
        <p><?= e($solicitud['documento_nombre']) ?> · <?= e(mg_tamano_legible((int) $solicitud['documento_tamano'])) ?> · <a href="<?= e($documento) ?>" target="_blank" rel="noopener">Abrir en otra pestaña</a></p>
        <?php if ($esImagen): ?>
            <img src="<?= e($documento) ?>" alt="Documento de notas de <?= e($solicitud['estudiante']) ?>" style="max-width:100%;height:auto;border:1px solid #ccd;border-radius:6px;">
        <?php else: ?>
            <iframe src="<?= e($documento) ?>" title="Documento de notas" style="width:100%;height:70vh;border:1px solid #ccd;border-radius:6px;"></iframe>
        <?php endif; ?>
        <p class="panel-note">Verifica a mano que el documento corresponda al carnet y muestre que el estudiante está en esta etapa. Huella SHA-256: <code><?= e(substr((string) $solicitud['documento_hash'], 0, 16)) ?>…</code></p>
    </section>

    <?php if (!$abierta): ?>
        <section class="card mg-seccion">
            <h2>Resultado</h2>
            <p><?= mg_badge_solicitud((string) $solicitud['estado']) ?> <?= $solicitud['revisor'] ? 'por ' . e($solicitud['revisor']) : '' ?> el <?= e(mg_fecha_corta($solicitud['fecha_revision'])) ?><?= $solicitud['motivo_revision'] ? ' — ' . e($solicitud['motivo_revision']) : '' ?></p>
            <?php if ($solicitud['id_expediente']): ?><p><a class="button" href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $solicitud['id_expediente'])) ?>">Ver expediente</a></p><?php endif; ?>
        </section>
    <?php else: ?>
        <?php if ($solicitud['estado'] === 'pendiente'): ?>
        <section class="card mg-seccion">
            <h2>Aprobar</h2>
            <p class="panel-note">Al aprobar se crea el expediente del estudiante con la modalidad solicitada y se le avisa. Elige su cohorte y etapa inicial (sugerida según su situación: quien aún cursa entra a la etapa previa; el egresado, a MG1).</p>
            <?php if (!$cohortes): ?><p class="alert" role="alert">No hay cohortes activas. <?php if (Auth::canDo('mg.catalogo')): ?><a href="<?= e(app_url('mg/cohortes/form.php')) ?>">Crea una</a>.<?php endif; ?></p><?php endif; ?>
            <form method="post" action="<?= e(app_url('mg/solicitudes/accion.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="accion" value="aprobar">
                <div class="form-grid">
                    <div><label for="id_cohorte">Cohorte</label><select id="id_cohorte" name="id_cohorte" required><option value="">Selecciona…</option><?php foreach ($cohortes as $item): ?><option value="<?= (int) $item['id_cohorte'] ?>"><?= e($item['nombre']) ?></option><?php endforeach; ?></select></div>
                    <div><label for="etapa_actual">Etapa inicial</label><select id="etapa_actual" name="etapa_actual"><option value="previa" <?= $solicitud['situacion'] === 'cursando_ultimo' ? 'selected' : '' ?>>Etapa previa (talleres)</option><option value="mg1" <?= $solicitud['situacion'] === 'egresado' ? 'selected' : '' ?>>MG1 · Perfil</option></select></div>
                    <div><label for="fecha_inicio">Fecha de inicio</label><input id="fecha_inicio" name="fecha_inicio" type="date" required value="<?= e(date('Y-m-d')) ?>"></div>
                </div>
                <button type="submit" <?= $cohortes ? '' : 'disabled' ?>>Aprobar y crear expediente</button>
            </form>
        </section>
        <section class="card mg-seccion">
            <h2>Observar</h2>
            <p class="panel-note">Pide al estudiante corregir algo (por ejemplo, un documento ilegible). Podrá reenviar la solicitud.</p>
            <form method="post" action="<?= e(app_url('mg/solicitudes/accion.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="accion" value="observar">
                <label for="motivo_observar">Observación</label>
                <textarea id="motivo_observar" name="motivo" required minlength="5" maxlength="500" rows="2"></textarea>
                <button type="submit" class="secondary">Observar</button>
            </form>
        </section>
        <?php endif; ?>
        <section class="card mg-seccion">
            <h2>Rechazar</h2>
            <form method="post" action="<?= e(app_url('mg/solicitudes/accion.php')) ?>" onsubmit="return confirm('¿Rechazar esta solicitud?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="accion" value="rechazar">
                <label for="motivo_rechazar">Motivo del rechazo</label>
                <textarea id="motivo_rechazar" name="motivo" required minlength="5" maxlength="500" rows="2"></textarea>
                <button type="submit" class="secondary">Rechazar</button>
            </form>
        </section>
    <?php endif; ?>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
