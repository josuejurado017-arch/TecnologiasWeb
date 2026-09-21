<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Evaluaciones</h1>
            <p>Evalúa a tus tutores cuando tu grupo ya haya tenido al menos una sesión.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay un periodo de tutoría activo.</p>
    <?php else: ?>
        <section class="card">
            <h2>Pendientes de evaluar</h2>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Materia</th><th>Tutor</th><th>Horario</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): ?>
                            <tr>
                                <td><?= e($p['nombre_materia']) ?></td>
                                <td><?= e($p['tutor']) ?></td>
                                <td><?= e($p['dia_semana']) ?> <?= e(substr((string) $p['hora_inicio'], 0, 5)) ?>-<?= e(substr((string) $p['hora_fin'], 0, 5)) ?></td>
                                <td><a href="<?= e(app_url('evaluaciones/evaluar.php?inscripcion=' . (int) $p['id_inscripcion'])) ?>">Evaluar</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$pendientes): ?><tr><td colspan="4" class="empty-state">No tienes evaluaciones pendientes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" style="margin-top:1rem;">
            <h2>Evaluaciones realizadas</h2>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Materia</th><th>Tutor</th><th>General</th><th>Punt.</th><th>Dominio</th><th>Claridad</th><th>Utilidad</th></tr></thead>
                    <tbody>
                        <?php foreach ($realizadas as $r): ?>
                            <tr>
                                <td><?= e($r['nombre_materia']) ?></td>
                                <td><?= e($r['tutor']) ?></td>
                                <td><?= (int) $r['calificacion_general'] ?>/5</td>
                                <td><?= (int) $r['puntualidad'] ?></td>
                                <td><?= (int) $r['dominio'] ?></td>
                                <td><?= (int) $r['claridad'] ?></td>
                                <td><?= (int) $r['utilidad'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$realizadas): ?><tr><td colspan="7" class="empty-state">Aún no has evaluado ninguna tutoría.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
