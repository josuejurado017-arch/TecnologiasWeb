<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Cohortes y calendario</h1>
            <p>Los estudiantes se agrupan por cohorte de inicio (ej. Grupo 1 · marzo, Grupo 2 · septiembre). Cada cohorte tiene su calendario de hitos; la cantidad de informes de MG2 es la cantidad de hitos tipo informe (C-02).</p>
        </div>
        <div class="page-heading-actions">
            <?php if (Auth::canDo('mg.catalogo')): ?><a class="button" href="<?= e(app_url('mg/cohortes/form.php')) ?>">Nueva cohorte</a><?php endif; ?>
            <a class="button secondary" href="<?= e(app_url('mg/modalidades.php')) ?>">Modalidades</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Cohorte</th><th>Código</th><th>Inicio</th><th>Fin</th><th>Expedientes</th><th>Hitos</th><th>Informes MG2</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($cohortes as $cohorte): ?>
                    <tr>
                        <td><?= e($cohorte['nombre']) ?></td>
                        <td><?= e($cohorte['codigo']) ?></td>
                        <td><?= e(mg_fecha_corta($cohorte['fecha_inicio'])) ?></td>
                        <td><?= e(mg_fecha_corta($cohorte['fecha_fin'])) ?></td>
                        <td><a href="<?= e(app_url('mg/expedientes/?id_cohorte=' . (int) $cohorte['id_cohorte'])) ?>"><?= (int) $cohorte['expedientes'] ?></a></td>
                        <td><?= (int) $cohorte['hitos'] ?></td>
                        <td><?= (int) $cohorte['informes'] ?></td>
                        <td><span class="badge badge-<?= (int) $cohorte['activa'] === 1 ? 'activa' : 'inactiva' ?>"><?= (int) $cohorte['activa'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('mg/cohortes/calendario.php?id=' . (int) $cohorte['id_cohorte'])) ?>">Calendario</a>
                            <a href="<?= e(app_url('mg/cohortes/linea.php?id=' . (int) $cohorte['id_cohorte'])) ?>">Línea de tiempo</a>
                            <?php if (Auth::canDo('mg.catalogo')): ?>
                                <a href="<?= e(app_url('mg/cohortes/form.php?id=' . (int) $cohorte['id_cohorte'])) ?>">Editar</a>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $cohorte['id_cohorte'] ?>">
                                    <input type="hidden" name="accion" value="<?= (int) $cohorte['activa'] === 1 ? 'desactivar' : 'activar' ?>">
                                    <button class="link-button" type="submit"><?= (int) $cohorte['activa'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$cohortes): ?><tr><td colspan="9" class="empty-state">No hay cohortes registradas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="panel-note">[PENDIENTE] Término institucional oficial de la cohorte (pregunta 7 al Coordinador).</p>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
