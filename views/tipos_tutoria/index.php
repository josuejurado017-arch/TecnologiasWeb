<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Tipos de tutoría</h1>
            <p>Cada período es de un tipo (Pregrado, Postgrado, Nivelación...). Tutorías de distinto tipo corren en paralelo: puede haber <strong>un período activo por tipo</strong>. Para ver lo que pasa en cada uno, cambia el tipo en la barra superior.</p>
        </div>
        <a class="button" href="<?= e(app_url('tipos-tutoria/create.php')) ?>">Nuevo tipo</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Tipo</th><th>Descripción</th><th>Duración máxima del período</th><th>Período activo</th><th>Períodos</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($tipos as $tipo): ?>
                    <tr>
                        <td><?= e($tipo['nombre']) ?></td>
                        <td><?= e($tipo['descripcion'] ?? '') ?></td>
                        <td><?= $tipo['duracion_max_dias'] !== null ? (int) $tipo['duracion_max_dias'] . ' días' : 'Sin tope' ?></td>
                        <td><?= $tipo['periodo_activo'] !== null ? e($tipo['periodo_activo']) : '<span class="panel-note">Ninguno</span>' ?></td>
                        <td><?= (int) $tipo['periodos'] ?></td>
                        <td><span class="badge badge-<?= $tipo['estado'] === 'activo' ? 'activa' : 'inactiva' ?>"><?= $tipo['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('tipos-tutoria/edit.php?id=' . (int) $tipo['id_tipo_tutoria'])) ?>">Editar</a>
                            <?php
                            $acciones = [$tipo['estado'] === 'activo' ? 'desactivar' : 'activar' => $tipo['estado'] === 'activo' ? 'Desactivar' : 'Activar'];
                            if ((int) $tipo['periodos'] === 0) {
                                $acciones['eliminar'] = 'Eliminar';
                            }
                            foreach ($acciones as $accion => $label):
                            ?>
                                <form method="post" action="<?= e(app_url('tipos-tutoria/estado.php')) ?>"<?= $accion === 'eliminar' ? ' onsubmit="return confirm(\'¿Eliminar este tipo de tutoría?\');"' : '' ?>>
                                    <input type="hidden" name="id" value="<?= (int) $tipo['id_tipo_tutoria'] ?>">
                                    <input type="hidden" name="accion" value="<?= e($accion) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button" type="submit"><?= e($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tipos): ?><tr><td colspan="7" class="empty-state">No hay tipos de tutoría registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
