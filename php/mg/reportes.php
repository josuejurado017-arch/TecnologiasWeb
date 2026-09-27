<?php

// Reporte general por cohorte (HU-033): filtros, totales por etapa y estado, grafico y CSV.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.reportes');
$title = 'Reporte de Modalidades de Grado';
$activePage = 'mg-reportes';

$filtros = [
    'q' => is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '',
    'id_cohorte' => (int) filter_input(INPUT_GET, 'id_cohorte', FILTER_VALIDATE_INT),
    'id_modalidad' => (int) filter_input(INPUT_GET, 'id_modalidad', FILTER_VALIDATE_INT),
    'etapa' => is_string($_GET['etapa'] ?? null) ? $_GET['etapa'] : '',
    'estado' => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '',
];
$modelo = new MgExpediente();
$expedientes = $modelo->listar($filtros);
$totales = $modelo->totales($filtros);
$avances = (new MgInforme())->ultimosAvances(array_column($expedientes, 'id_expediente'));

if (($_GET['export'] ?? '') === 'csv') {
    // CSV para Excel: BOM, separador ';' y celdas neutralizadas contra formulas (includes/Csv.php).
    $salida = csv_download('modalidades-grado-' . date('Y-m-d'));
    csv_row($salida, ['Estudiante', 'R.U.', 'Carrera', 'Modalidad', 'Cohorte', 'Etapa', 'Estado', 'Tutor', 'Inicio', 'Cierre', 'Avance (%)', 'Último informe', 'Tema']);
    foreach ($expedientes as $item) {
        $avance = $avances[(int) $item['id_expediente']] ?? null;
        csv_row($salida, [$item['estudiante'], $item['registro_universitario'], $item['carrera'], $item['modalidad'], $item['cohorte'],
            MgExpediente::ETAPAS[$item['etapa_actual']], MgExpediente::ESTADOS[$item['estado']], $item['tutor'] ?? '',
            $item['fecha_inicio'], $item['fecha_cierre'] ?? '', $avance ? (int) $avance['porcentaje_avance'] : '', $avance['hito'] ?? '', $item['titulo_trabajo'] ?? '']);
    }
    fclose($salida);
    exit;
}

$catalogo = new MgCatalogo();
$cohortes = $catalogo->cohortes();
$modalidades = $catalogo->modalidades();
$graficoEtapas = ['labels' => array_values(MgExpediente::ETAPAS), 'values' => array_map(static fn (string $k): int => (int) ($totales['etapa'][$k] ?? 0), array_keys(MgExpediente::ETAPAS))];
$graficoEstados = ['labels' => array_values(MgExpediente::ESTADOS), 'values' => array_map(static fn (string $k): int => (int) ($totales['estado'][$k] ?? 0), array_keys(MgExpediente::ESTADOS))];
$query = http_build_query(array_filter($filtros));

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Reporte por cohorte</h1>
            <p>Situación de los expedientes según los filtros. El avance es el del último informe presentado.</p>
        </div>
        <a class="button" href="<?= e(app_url('mg/reportes.php?' . ($query !== '' ? $query . '&' : '') . 'export=csv')) ?>">Exportar CSV (Excel)</a>
    </div>

    <form method="get" class="card mg-filtros">
        <div><label for="id_cohorte">Cohorte</label><select id="id_cohorte" name="id_cohorte"><option value="">Todas</option><?php foreach ($cohortes as $item): ?><option value="<?= (int) $item['id_cohorte'] ?>" <?= $filtros['id_cohorte'] === (int) $item['id_cohorte'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></div>
        <div><label for="id_modalidad">Modalidad</label><select id="id_modalidad" name="id_modalidad"><option value="">Todas</option><?php foreach ($modalidades as $item): ?><option value="<?= (int) $item['id_modalidad'] ?>" <?= $filtros['id_modalidad'] === (int) $item['id_modalidad'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></div>
        <div><label for="etapa">Etapa</label><select id="etapa" name="etapa"><option value="">Todas</option><?php foreach (MgExpediente::ETAPAS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['etapa'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><?php foreach (MgExpediente::ESTADOS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="mg-filtros-acciones"><button type="submit">Aplicar</button></div>
    </form>

    <div class="mg-grid">
        <article class="card chart-card">
            <div class="section-heading"><div><span class="eyebrow">Por etapa</span><h2><?= count($expedientes) ?> expedientes</h2></div></div>
            <div class="chart-wrap"><canvas data-dashboard-chart data-chart-type="bar" data-chart-data="<?= e(json_encode($graficoEtapas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Expedientes por etapa" role="img"></canvas></div>
            <p class="panel-note"><?= e(implode(' · ', array_map(static fn ($l, $v): string => $l . ': ' . $v, $graficoEtapas['labels'], $graficoEtapas['values']))) ?></p>
        </article>
        <article class="card chart-card">
            <div class="section-heading"><div><span class="eyebrow">Por estado</span><h2>Resultado</h2></div></div>
            <div class="chart-wrap"><canvas data-dashboard-chart data-chart-type="doughnut" data-chart-data="<?= e(json_encode($graficoEstados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Expedientes por estado" role="img"></canvas></div>
            <p class="panel-note"><?= e(implode(' · ', array_map(static fn ($l, $v): string => $l . ': ' . $v, $graficoEstados['labels'], $graficoEstados['values']))) ?></p>
        </article>
    </div>

    <div class="table-wrapper card mg-seccion">
        <table>
            <thead><tr><th>Estudiante</th><th>Modalidad</th><th>Cohorte</th><th>Etapa</th><th>Inicio</th><th>Avance</th><th>Tutor</th><th>Estado</th></tr></thead>
            <tbody>
                <?php foreach ($expedientes as $item): ?>
                    <tr>
                        <td><a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $item['id_expediente'])) ?>"><?= e($item['estudiante']) ?></a><br><small>R.U. <?= e((string) $item['registro_universitario']) ?></small></td>
                        <td><?= e($item['modalidad']) ?></td>
                        <td><?= e($item['cohorte']) ?></td>
                        <td><?= mg_badge_etapa((string) $item['etapa_actual']) ?></td>
                        <td><?= e(mg_fecha_corta($item['fecha_inicio'])) ?></td>
                        <?php $avance = $avances[(int) $item['id_expediente']] ?? null; ?>
                        <td><?= $avance ? (int) $avance['porcentaje_avance'] . '%<br><small>' . e($avance['hito']) . '</small>' : '—' ?></td>
                        <td><?= e((string) ($item['tutor'] ?? '—')) ?></td>
                        <td><?= mg_badge_estado((string) $item['estado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$expedientes): ?><tr><td colspan="8" class="empty-state">Sin expedientes con esos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
