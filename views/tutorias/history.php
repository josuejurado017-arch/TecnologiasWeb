<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading"><div><span class="eyebrow">Trazabilidad</span><h1>Historial de tutoria</h1><p><?= e($tutoria['nombre_materia']) ?> · <?= e($tutoria['fecha']) ?></p></div><a class="button secondary" href="<?= e(app_url('tutorias/')) ?>">Volver</a></div>
    <section class="card timeline-card">
        <?php if (!$history): ?><div class="empty-state">No hay cambios registrados.</div><?php else: ?><div class="tutoria-timeline"><?php foreach ($history as $event): ?><?php $before = json_decode((string) ($event['datos_anteriores'] ?? ''), true) ?: []; $after = json_decode((string) ($event['datos_nuevos'] ?? ''), true) ?: []; $beforeLabel = implode(' · ', array_filter([(string) ($before['fecha'] ?? ''), (string) ($before['hora_inicio'] ?? ''), (string) ($before['modalidad'] ?? '')])); $afterLabel = implode(' · ', array_filter([(string) ($after['fecha'] ?? ''), (string) ($after['hora_inicio'] ?? ''), (string) ($after['modalidad'] ?? '')])); ?><article class="timeline-item"><span class="timeline-dot"></span><div><div class="timeline-meta"><strong><?= e(ucfirst(str_replace('_', ' ', $event['tipo_evento']))) ?></strong><time><?= e($event['fecha_cambio']) ?></time></div><p><?= e($event['motivo'] ?: 'Actualizacion registrada en el sistema.') ?></p><small>Responsable: <?= e($event['responsable']) ?><?php if ($event['estado_anterior'] || $event['estado_nuevo']): ?> · <?= e($event['estado_anterior'] ?: 'inicio') ?> &rarr; <?= e($event['estado_nuevo'] ?: '-') ?><?php endif; ?></small><?php if ($beforeLabel || $afterLabel): ?><small class="timeline-change">Antes: <?= e($beforeLabel ?: '-') ?> | Despues: <?= e($afterLabel ?: '-') ?></small><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
