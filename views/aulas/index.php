<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Aulas</h1>
            <p>Aulas fisicas y salas virtuales disponibles para los grupos de tutoria.</p>
        </div>
        <a class="button" href="<?= e(app_url('aulas/create.php')) ?>">Nueva aula</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-toolbar">
        <div class="search-field">
            <span class="search-icon" aria-hidden="true">/</span>
            <label class="sr-only" for="aula-search">Buscar aulas</label>
            <input id="aula-search" type="search" placeholder="Buscar aula..." data-table-search>
        </div>
        <span class="table-meta" data-table-count><?= count($aulas) ?> resultado<?= count($aulas) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Capacidad</th><th>Ubicacion / Plataforma</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($aulas as $aula): ?>
                    <tr data-row>
                        <td><?= (int) $aula['id_aula'] ?></td>
                        <td><?= e($aula['nombre']) ?></td>
                        <td><?= $aula['tipo'] === 'virtual' ? 'Virtual' : 'Fisica' ?></td>
                        <td><?= (int) $aula['capacidad'] ?></td>
                        <td><?= e($aula['tipo'] === 'virtual' ? ($aula['plataforma'] ?? 'Virtual') : ($aula['ubicacion'] ?? '-')) ?></td>
                        <td><span class="badge badge-<?= e($aula['estado']) ?>"><?= e(ucfirst($aula['estado'])) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('aulas/edit.php?id=' . (int) $aula['id_aula'])) ?>">Editar</a>
                            <form method="post" action="<?= e(app_url('aulas/delete.php')) ?>" onsubmit="return confirm('Eliminar esta aula?');">
                                <input type="hidden" name="id" value="<?= (int) $aula['id_aula'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr data-search-empty hidden><td colspan="7" class="empty-state">No se encontraron aulas.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
