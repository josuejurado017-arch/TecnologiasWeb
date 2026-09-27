<?php

// Listado de expedientes con filtros (HU-024).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$title = 'Expedientes de grado';
$activePage = 'mg-expedientes';

$filtros = [
    'q' => is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '',
    'id_cohorte' => (int) filter_input(INPUT_GET, 'id_cohorte', FILTER_VALIDATE_INT),
    'id_modalidad' => (int) filter_input(INPUT_GET, 'id_modalidad', FILTER_VALIDATE_INT),
    'etapa' => is_string($_GET['etapa'] ?? null) ? $_GET['etapa'] : '',
    'estado' => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '',
    'id_tutor' => (int) filter_input(INPUT_GET, 'id_tutor', FILTER_VALIDATE_INT),
];
$expedientes = (new MgExpediente())->listar($filtros);
$catalogo = new MgCatalogo();
$cohortes = $catalogo->cohortes();
$modalidades = $catalogo->modalidades();
[$message, $error] = mg_flash(['creado' => 'Expediente creado.']);

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Expedientes de grado</h1>
            <p>Un expediente es un estudiante en un proceso de grado (trabajo individual). Filtra por cohorte, modalidad, etapa o estado.</p>
        </div>
        <div class="page-heading-actions">
            <?php if (Auth::canDo('mg.expediente')): ?><a class="button" href="<?= e(app_url('mg/expedientes/nuevo.php')) ?>">Nuevo expediente</a><?php endif; ?>
            <?php if (Auth::canDo('mg.reportes')): ?><a class="button secondary" href="<?= e(app_url('mg/reportes.php?' . http_build_query(array_filter($filtros)))) ?>">Ver como reporte</a><?php endif; ?>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form method="get" class="card mg-filtros">
        <div>
            <label for="q">Buscar</label>
            <input id="q" name="q" type="search" value="<?= e($filtros['q']) ?>" placeholder="Nombre, R.U. o título">
        </div>
        <div>
            <label for="id_cohorte">Cohorte</label>
            <select id="id_cohorte" name="id_cohorte"><option value="">Todas</option><?php foreach ($cohortes as $item): ?><option value="<?= (int) $item['id_cohorte'] ?>" <?= $filtros['id_cohorte'] === (int) $item['id_cohorte'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select>
        </div>
        <div>
            <label for="id_modalidad">Modalidad</label>
            <select id="id_modalidad" name="id_modalidad"><option value="">Todas</option><?php foreach ($modalidades as $item): ?><option value="<?= (int) $item['id_modalidad'] ?>" <?= $filtros['id_modalidad'] === (int) $item['id_modalidad'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select>
        </div>
        <div>
            <label for="etapa">Etapa</label>
            <select id="etapa" name="etapa"><option value="">Todas</option><?php foreach (MgExpediente::ETAPAS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['etapa'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <?php if ($filtros['id_tutor']): ?>
            <div>
                <label for="id_tutor">Tutor</label>
                <select id="id_tutor" name="id_tutor"><option value="">Todos</option><option value="<?= (int) $filtros['id_tutor'] ?>" selected><?= e((string) ($expedientes[0]['tutor'] ?? 'Tutor #' . $filtros['id_tutor'])) ?></option></select>
            </div>
        <?php endif; ?>
        <div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado"><option value="">Todos</option><?php foreach (MgExpediente::ESTADOS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <div class="mg-filtros-acciones"><button type="submit">Filtrar</button><a class="button secondary" href="<?= e(app_url('mg/expedientes/')) ?>">Limpiar</a></div>
    </form>

    <div class="table-toolbar"><span class="table-meta"><?= count($expedientes) ?> expediente<?= count($expedientes) === 1 ? '' : 's' ?></span></div>
    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Estudiante</th><th>R.U.</th><th>Modalidad</th><th>Cohorte</th><th>Etapa</th><th>Estado</th><th>Tutor</th><th>Inicio</th></tr></thead>
            <tbody>
                <?php foreach ($expedientes as $item): ?>
                    <tr>
                        <td><a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $item['id_expediente'])) ?>"><?= e($item['estudiante']) ?></a><?php if ($item['titulo_trabajo']): ?><br><small><?= e($item['titulo_trabajo']) ?></small><?php endif; ?></td>
                        <td><?= e((string) $item['registro_universitario']) ?></td>
                        <td><?= e($item['modalidad']) ?></td>
                        <td><?= e($item['cohorte']) ?></td>
                        <td><?= mg_badge_etapa((string) $item['etapa_actual']) ?></td>
                        <td><?= mg_badge_estado((string) $item['estado']) ?></td>
                        <td><?= $item['tutor'] ? e($item['tutor']) : ((int) $item['requiere_tutor'] === 1 ? '<span class="badge badge-warning">Sin tutor</span>' : '<small>No aplica</small>') ?></td>
                        <td><?= e(mg_fecha_corta($item['fecha_inicio'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$expedientes): ?><tr><td colspan="8" class="empty-state">No hay expedientes con esos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
