<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading"><div><h1>Cobertura de tutores</h1><p>Los horarios los configura cada tutor por materia en "Mis materias". Un tutor sin ninguna materia configurada no puede recibir grupos.</p></div></div>

    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <article class="stat-card card"><span class="stat-label">Tutores activos</span><strong class="stat-value"><?= $totalTutores ?></strong></article>
        <article class="stat-card card"><span class="stat-label">Con horarios</span><strong class="stat-value"><?= $totalTutores - $totalSinHorarios ?></strong></article>
        <article class="stat-card card"><span class="stat-label">Sin horarios</span><strong class="stat-value<?= $totalSinHorarios > 0 ? ' report-warning' : '' ?>"><?= $totalSinHorarios ?></strong></article>
    </div>

    <div class="table-wrapper card">
        <div class="page-heading" style="margin-bottom:1rem;">
            <div></div>
            <div class="form-grid" style="grid-template-columns:auto auto;gap:0.5rem;">
                <a class="button<?= $filter === 'todos' ? '' : ' secondary' ?>" href="<?= e(app_url('cobertura-tutores/')) ?>">Todos</a>
                <a class="button<?= $filter === 'sin_horarios' ? '' : ' secondary' ?>" href="<?= e(app_url('cobertura-tutores/?filtro=sin_horarios')) ?>">Sin horarios</a>
            </div>
        </div>
        <table><thead><tr><th>Tutor</th><th>Estado</th><th>Materias con horarios</th></tr></thead><tbody>
            <?php foreach ($coverage as $tutor): ?>
                <tr>
                    <td><?= e($tutor['tutor']) ?></td>
                    <td><span class="status <?= $tutor['configuradas'] > 0 ? 'status-configurada' : 'status-sin-configurar' ?>"><?= $tutor['configuradas'] > 0 ? 'Con horarios' : '⚠ Sin horarios' ?></span></td>
                    <td><?= $tutor['configuradas'] ?> de <?= $tutor['materias'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$coverage): ?><tr><td colspan="3">No hay tutores para este filtro.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
