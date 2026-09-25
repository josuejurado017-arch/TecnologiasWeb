<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$hora = static fn (string $h): string => substr($h, 0, 5);
?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Ofertas de materias</h1>
            <p>Turnos que un tutor guardó en "Mis materias": el motor y el catálogo del estudiante solo los usan cuando apruebas la oferta. Aquí revisas tutor, materia, modalidad y turnos; el espacio, el aula o el enlace se definen después, en cada grupo por aprobar. Los días de cada grupo no los elige el tutor: los fija la demanda (LMV o MJS con menos de <?= Grupo::UMBRAL_GRUPO_NORMAL ?> estudiantes, lunes a viernes desde <?= Grupo::UMBRAL_GRUPO_NORMAL ?>).</p>
        </div>
        <div class="page-actions">
            <a class="button secondary" href="<?= e(app_url('tutores/')) ?>">Todos los tutores</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Tutor</th><th>Materia</th><th>Modalidad</th><th>Turnos ofrecidos</th><th>Guardado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($ofertas as $o): ?>
                    <tr>
                        <td><strong><?= e($o['tutor']) ?></strong></td>
                        <td><?= e($o['nombre_materia']) ?><?php if ($o['modalidad_requerida'] !== 'libre'): ?><br><small>Exige modalidad <?= e($o['modalidad_requerida']) ?></small><?php endif; ?></td>
                        <td><?= e(ucfirst((string) $o['modalidad'])) ?></td>
                        <td>
                            <?= e(implode(', ', array_map(static fn (string $t): string => $turnoLabels[$t] ?? $t, $o['turnos'])) ?: '—') ?>
                            <?php if ($o['cupo_recomendado']): ?><br><small>Cupo recomendado: <?= (int) $o['cupo_recomendado'] ?></small><?php endif; ?>
                        </td>
                        <td><?= e(date('d/m/Y', strtotime((string) $o['fecha_actualizacion']))) ?></td>
                        <td class="actions">
                            <form method="post" action="<?= e(app_url('tutores/ofertas_revisar.php')) ?>" class="inline-form" onsubmit="return confirm('¿Aprobar esta oferta? El sistema podrá proponerle grupos al tutor en ese horario.');">
                                <input type="hidden" name="id_tutor" value="<?= (int) $o['id_tutor'] ?>">
                                <input type="hidden" name="id_materia" value="<?= (int) $o['id_materia'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Aprobar</button>
                            </form>
                            <form method="post" action="<?= e(app_url('tutores/ofertas_revisar.php')) ?>" class="inline-form" onsubmit="return confirm('¿Rechazar esta oferta? Se le notificará el motivo al tutor.');">
                                <input type="hidden" name="id_tutor" value="<?= (int) $o['id_tutor'] ?>">
                                <input type="hidden" name="id_materia" value="<?= (int) $o['id_materia'] ?>">
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input class="inline-response" name="motivo" placeholder="Motivo del rechazo" required minlength="10" maxlength="300">
                                <button class="link-button" type="submit">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ofertas): ?>
                    <tr><td colspan="6" class="empty-state">No hay ofertas de materias pendientes de revisión.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
