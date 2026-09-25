<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Cuentas de acceso</h1>
            <p>Credenciales, roles y estado de acceso al sistema.</p>
        </div>
        <a class="button" href="<?= e(app_url('usuarios/create.php')) ?>">Nuevo usuario</a>
    </div>

    <?php if (!empty($message)): ?>
        <p class="success" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p class="alert" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="table-toolbar">
        <div class="search-field">
            <span class="search-icon" aria-hidden="true">/</span>
            <label class="sr-only" for="user-search">Buscar cuentas</label>
            <input id="user-search" type="search" placeholder="Buscar cuenta..." data-table-search>
        </div>
        <span class="table-meta" data-table-count><?= count($usuarios) ?> resultado<?= count($usuarios) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Perfil</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr data-row>
                        <td><?= e($usuario['nombre'] . ' ' . $usuario['apellido']) ?></td>
                        <td><?= e($usuario['usuario']) ?></td>
                        <td><?= e($usuario['correo']) ?></td>
                        <td><?= e($usuario['nombre_rol']) ?></td>
                        <td>
                            <?php if ($usuario['nombre_rol'] === 'estudiante'): ?>
                                <?php if (!empty($usuario['id_estudiante'])): ?><a href="<?= e(app_url('estudiantes/edit.php?id=' . (int) $usuario['id_estudiante'])) ?>">Ver perfil</a><?php else: ?><span class="table-meta">Sin perfil</span><?php endif; ?>
                            <?php elseif ($usuario['nombre_rol'] === 'tutor'): ?>
                                <?php if (!empty($usuario['id_tutor'])): ?><a href="<?= e(app_url('tutores/edit.php?id=' . (int) $usuario['id_tutor'])) ?>">Ver perfil</a><?php else: ?><span class="table-meta">Sin perfil</span><?php endif; ?>
                            <?php else: ?>
                                <span class="table-muted">No aplica</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="status status-<?= e($usuario['estado']) ?>"><?= e($usuario['estado']) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('usuarios/edit.php?id=' . (int) $usuario['id_usuario'])) ?>">Editar</a>
                            <?php if ($usuario['estado'] === 'activo'): ?>
                                <form method="post" action="<?= e(app_url('usuarios/delete.php')) ?>" onsubmit="return confirm('¿Desactivar este usuario?');">
                                    <input type="hidden" name="id" value="<?= (int) $usuario['id_usuario'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button" type="submit">Desactivar</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(app_url('usuarios/activate.php')) ?>" onsubmit="return confirm('¿Activar esta cuenta?');">
                                    <input type="hidden" name="id" value="<?= (int) $usuario['id_usuario'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button success-link" type="submit">Activar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$usuarios): ?>
                    <tr>
                        <td colspan="7">No hay cuentas registradas.</td>
                    </tr>
                <?php endif; ?>
                <tr data-search-empty hidden><td colspan="7" class="empty-state">No se encontraron cuentas.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
