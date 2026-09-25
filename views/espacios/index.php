<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Espacios de tutoría</h1>
            <p>Categorías de lugar para los grupos. No son aulas reservadas: el sistema no conoce la ocupación de la universidad. El aula o el enlace concreto de cada grupo se registra en <a href="<?= e(app_url('grupos/?estado=sin_ubicacion')) ?>">Grupos → Ubicación</a>.</p>
        </div>
        <a class="button" href="<?= e(app_url('espacios/create.php')) ?>">Nuevo espacio</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <p class="panel-note">El espacio <strong>predeterminado</strong> de cada modalidad es el que el sistema asigna a los grupos nuevos. No se puede desactivar.</p>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Espacio</th><th>Modalidad</th><th>Descripción</th><th>Grupos vigentes</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($espacios as $espacio): ?>
                    <tr>
                        <td><?= e($espacio['nombre']) ?><?php if ((int) $espacio['predeterminado'] === 1): ?> <span class="badge badge-info">Predeterminado</span><?php endif; ?></td>
                        <td><?= e(EspacioTutoria::MODALIDADES[$espacio['modalidad']] ?? $espacio['modalidad']) ?></td>
                        <td><?= e($espacio['descripcion'] ?? '') ?></td>
                        <td><?= (int) $espacio['grupos_activos'] ?></td>
                        <td><span class="badge badge-<?= $espacio['estado'] === 'activo' ? 'activa' : 'inactiva' ?>"><?= $espacio['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('espacios/edit.php?id=' . (int) $espacio['id_espacio'])) ?>">Editar</a>
                            <?php
                            $acciones = [];
                            if ((int) $espacio['predeterminado'] !== 1) {
                                $acciones['predeterminar'] = 'Hacer predeterminado';
                                $acciones[$espacio['estado'] === 'activo' ? 'desactivar' : 'activar'] = $espacio['estado'] === 'activo' ? 'Desactivar' : 'Activar';
                            }
                            foreach ($acciones as $accion => $label):
                            ?>
                                <form method="post" action="<?= e(app_url('espacios/estado.php')) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $espacio['id_espacio'] ?>">
                                    <input type="hidden" name="accion" value="<?= e($accion) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button" type="submit"><?= e($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$espacios): ?><tr><td colspan="6" class="empty-state">No hay espacios registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
