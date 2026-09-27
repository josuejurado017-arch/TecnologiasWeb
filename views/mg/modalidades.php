<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Modalidades de grado</h1>
            <p>Ingeniería de Sistemas oferta 5 modalidades (RN-MG-01). Solo Proyecto de Grado, Tesis y Trabajo Dirigido usan tutor.</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('mg/cohortes/')) ?>">Cohortes</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Modalidad</th><th>Código</th><th>Usa tutor</th><th>Expedientes</th><th>Estado</th><?php if (Auth::canDo('mg.catalogo')): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($modalidades as $modalidad): ?>
                    <tr>
                        <td><?= e($modalidad['nombre']) ?><?php if ((int) $modalidad['regla_por_validar'] === 1): ?> <span class="badge badge-warning" title="Reglas pendientes de validar con UPDS">Regla por validar</span><?php endif; ?></td>
                        <td><?= e($modalidad['codigo']) ?></td>
                        <td><?= (int) $modalidad['requiere_tutor'] === 1 ? 'Sí' : 'No' ?></td>
                        <td><?= (int) $modalidad['expedientes'] ?></td>
                        <td><span class="badge badge-<?= (int) $modalidad['activa'] === 1 ? 'activa' : 'inactiva' ?>"><?= (int) $modalidad['activa'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                        <?php if (Auth::canDo('mg.catalogo')): ?>
                            <td class="actions">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $modalidad['id_modalidad'] ?>">
                                    <input type="hidden" name="accion" value="<?= (int) $modalidad['activa'] === 1 ? 'desactivar' : 'activar' ?>">
                                    <button class="link-button" type="submit"><?= (int) $modalidad['activa'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="panel-note">Examen de Grado (mínimo de interesados) y Graduación por Excelencia (promedio) tienen reglas pendientes de normativa: se registran expedientes, pero su flujo propio queda para P3.</p>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
