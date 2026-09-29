<?php

// Bandeja de solicitudes de Modalidad de Grado (db/049): la Coordinacion revisa a quien pide iniciar su proceso.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.solicitudes');
$title = 'Solicitudes de grado';
$activePage = 'mg-solicitudes';

$filtros = [
    'q' => is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '',
    'estado' => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '',
];
$solicitudes = (new MgSolicitud())->listar($filtros);
[$message, $error] = mg_flash(['aprobada' => 'Solicitud aprobada: el expediente ya está creado.', 'observada' => 'Solicitud observada: se avisó al estudiante.', 'rechazada' => 'Solicitud rechazada.']);

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Solicitudes de grado</h1>
            <p>Estudiantes que piden iniciar su modalidad de grado. Revisa el documento de notas y decide: aprobar (crea el expediente), observar o rechazar.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form method="get" class="card mg-filtros">
        <div>
            <label for="q">Buscar</label>
            <input id="q" name="q" type="search" value="<?= e($filtros['q']) ?>" placeholder="Nombre, R.U. o carnet de identidad">
        </div>
        <div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado"><option value="">Todos</option><?php foreach (MgSolicitud::ESTADOS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <div class="mg-filtros-acciones"><button type="submit">Filtrar</button><a class="button secondary" href="<?= e(app_url('mg/solicitudes/')) ?>">Limpiar</a></div>
    </form>

    <div class="table-toolbar"><span class="table-meta"><?= count($solicitudes) ?> solicitud<?= count($solicitudes) === 1 ? '' : 'es' ?></span></div>
    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Estudiante</th><th>C.I.</th><th>R.U.</th><th>Modalidad</th><th>Situación</th><th>Estado</th><th>Enviada</th></tr></thead>
            <tbody>
                <?php foreach ($solicitudes as $item): ?>
                    <tr>
                        <td><a href="<?= e(app_url('mg/solicitudes/ver.php?id=' . (int) $item['id_solicitud'])) ?>"><?= e($item['estudiante']) ?></a><br><small><?= e($item['carrera']) ?></small></td>
                        <td><?= e((string) ($item['carnet_identidad'] ?? '—')) ?></td>
                        <td><?= e((string) $item['registro_universitario']) ?></td>
                        <td><?= e($item['modalidad']) ?></td>
                        <td><?= e(MgSolicitud::SITUACIONES[$item['situacion']] ?? '') ?></td>
                        <td><?= mg_badge_solicitud((string) $item['estado']) ?></td>
                        <td><?= e(mg_fecha_corta($item['fecha_solicitud'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$solicitudes): ?><tr><td colspan="7" class="empty-state">No hay solicitudes con esos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
