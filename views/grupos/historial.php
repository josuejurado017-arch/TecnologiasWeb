<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Historial del grupo</h1>
            <p><?= e($grupo['nombre_materia']) ?> · Estado actual: <span class="badge badge-<?= e($grupo['estado']) ?>"><?= e(ucfirst((string) $grupo['estado'])) ?></span></p>
        </div>
        <a class="button secondary" href="<?= e(app_url('grupos/')) ?>">Volver</a>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Fecha</th><th>Evento</th><th>Cambio</th><th>Responsable</th><th>Motivo</th></tr></thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?= e($h['fecha_evento']) ?></td>
                        <td><span class="badge badge-info"><?= e(ucfirst(str_replace('_', ' ', (string) $h['tipo_evento']))) ?></span></td>
                        <td><?= e(($h['estado_anterior'] ?? '—') . ' → ' . ($h['estado_nuevo'] ?? '—')) ?></td>
                        <td><?= e($h['responsable']) ?></td>
                        <td><?= e($h['motivo'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$historial): ?><tr><td colspan="5" class="empty-state">Sin eventos registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
