<?php
require __DIR__ . '/../layouts/header.php';
$t = $data['totals'] ?? [];
$sat = $data['satisfaccion'] ?? [];
$asis = [];
foreach (($data['asistencia'] ?? []) as $row) { $asis[$row['estado']] = (int) $row['total']; }
$totalAsis = array_sum($asis);
$presentes = ($asis['asistio'] ?? 0) + ($asis['parcial'] ?? 0) + ($asis['retraso'] ?? 0);
$pctAsistencia = $totalAsis > 0 ? round($presentes * 100 / $totalAsis) : 0;
?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Reportes por periodo</h1>
            <p>Métricas académicas del período de tutorías.</p>
        </div>
        <form method="get" action="<?= e(app_url('reportes/campania.php')) ?>">
            <select name="periodo" onchange="this.form.submit()">
                <?php foreach ($periodos as $p): ?>
                    <option value="<?= (int) $p['id_periodo'] ?>" <?= $periodo && (int) $periodo['id_periodo'] === (int) $p['id_periodo'] ? 'selected' : '' ?>><?= e($p['nombre']) ?> (<?= e($p['estado']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay periodos registrados.</p>
    <?php else: ?>
        <div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
            <article class="stat-card card"><span class="stat-label">Grupos</span><strong class="stat-value"><?= (int) ($t['grupos'] ?? 0) ?></strong><span class="stat-caption"><?= (int) ($t['grupos_confirmados'] ?? 0) ?> confirmados</span></article>
            <article class="stat-card card"><span class="stat-label">Estudiantes</span><strong class="stat-value"><?= (int) ($t['estudiantes'] ?? 0) ?></strong><span class="stat-caption"><?= (int) ($t['inscritos'] ?? 0) ?> inscripciones</span></article>
            <article class="stat-card card"><span class="stat-label">Tutores activos</span><strong class="stat-value"><?= (int) ($t['tutores'] ?? 0) ?></strong></article>
            <article class="stat-card card"><span class="stat-label">Asistencia</span><strong class="stat-value"><?= $pctAsistencia ?>%</strong><span class="stat-caption"><?= $presentes ?>/<?= $totalAsis ?> registros</span></article>
            <article class="stat-card card"><span class="stat-label">Cancelaciones</span><strong class="stat-value"><?= (int) ($t['grupos_cancelados'] ?? 0) ?></strong><span class="stat-caption">grupos cancelados</span></article>
            <article class="stat-card card"><span class="stat-label">Satisfacción</span><strong class="stat-value"><?= e((string) ($sat['general'] ?? '—')) ?></strong><span class="stat-caption"><?= (int) ($sat['evaluaciones'] ?? 0) ?> evaluaciones</span></article>
        </div>

        <div class="report-columns" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
            <section class="card">
                <h2>Tutores más activos</h2>
                <table><thead><tr><th>Tutor</th><th>Grupos</th><th>Estudiantes</th></tr></thead><tbody>
                    <?php foreach (($data['topTutores'] ?? []) as $r): ?><tr><td><?= e($r['tutor']) ?></td><td><?= (int) $r['grupos'] ?></td><td><?= (int) $r['estudiantes'] ?></td></tr><?php endforeach; ?>
                    <?php if (empty($data['topTutores'])): ?><tr><td colspan="3" class="empty-state">Sin datos.</td></tr><?php endif; ?>
                </tbody></table>
            </section>
            <section class="card">
                <h2>Materias más solicitadas</h2>
                <table><thead><tr><th>Materia</th><th>Inscripciones</th></tr></thead><tbody>
                    <?php foreach (($data['topMaterias'] ?? []) as $r): ?><tr><td><?= e($r['nombre_materia']) ?></td><td><?= (int) $r['solicitudes'] ?></td></tr><?php endforeach; ?>
                    <?php if (empty($data['topMaterias'])): ?><tr><td colspan="2" class="empty-state">Sin datos.</td></tr><?php endif; ?>
                </tbody></table>
            </section>
            <section class="card">
                <h2>Carreras con mayor demanda</h2>
                <table><thead><tr><th>Carrera</th><th>Estudiantes</th></tr></thead><tbody>
                    <?php foreach (($data['topCarreras'] ?? []) as $r): ?><tr><td><?= e($r['nombre_carrera']) ?></td><td><?= (int) $r['estudiantes'] ?></td></tr><?php endforeach; ?>
                    <?php if (empty($data['topCarreras'])): ?><tr><td colspan="2" class="empty-state">Sin datos.</td></tr><?php endif; ?>
                </tbody></table>
            </section>
            <section class="card">
                <h2>Satisfacción por criterio</h2>
                <table><tbody>
                    <tr><td>General</td><td><?= e((string) ($sat['general'] ?? '—')) ?></td></tr>
                    <tr><td>Puntualidad</td><td><?= e((string) ($sat['puntualidad'] ?? '—')) ?></td></tr>
                    <tr><td>Dominio</td><td><?= e((string) ($sat['dominio'] ?? '—')) ?></td></tr>
                    <tr><td>Claridad</td><td><?= e((string) ($sat['claridad'] ?? '—')) ?></td></tr>
                    <tr><td>Utilidad</td><td><?= e((string) ($sat['utilidad'] ?? '—')) ?></td></tr>
                </tbody></table>
            </section>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
