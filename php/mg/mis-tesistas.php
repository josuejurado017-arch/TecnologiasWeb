<?php

// Vista del tutor (HU-025, matriz V*): sus tesistas y las defensas donde es tribunal.
// Solo lectura y solo lo propio; las notas se ven cuando estan publicadas.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireAction('mg.propio');
$title = 'Mis tesistas';
$activePage = 'mg-mis-tesistas';

$tutorId = (new Tutor())->findIdByUserId((int) Auth::user()['id_usuario']) ?? 0;
$tesistas = $tutorId ? (new MgExpediente())->listar(['id_tutor' => $tutorId]) : [];
$defensas = new MgDefensa();

$comoTribunal = [];
if ($tutorId) {
    $consulta = Database::connection()->prepare(
        "SELECT tr.id_expediente, tr.etapa, tr.orden FROM tribunales_mg tr
         WHERE tr.id_tutor = :t AND tr.estado = 'vigente' ORDER BY tr.id_expediente"
    );
    $consulta->execute(['t' => $tutorId]);
    foreach ($consulta->fetchAll() as $fila) {
        $expediente = (new MgExpediente())->find((int) $fila['id_expediente']);
        if ($expediente !== null && $expediente['estado'] === 'activo') {
            $comoTribunal[] = $fila + ['expediente' => $expediente, 'defensa' => $defensas->programada((int) $fila['id_expediente'], (string) $fila['etapa'])];
        }
    }
}

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Mis tesistas</h1>
            <p>Estudiantes de Modalidades de Grado que acompañas como tutor desde MG1 hasta terminar MG2, y defensas donde eres tribunal.</p>
        </div>
    </div>

    <section class="card">
        <div class="section-heading"><div><span class="eyebrow">Como tutor</span><h2><?= count($tesistas) ?> tesista<?= count($tesistas) === 1 ? '' : 's' ?> vigente<?= count($tesistas) === 1 ? '' : 's' ?></h2></div></div>
        <?php if (!$tesistas): ?><p class="empty-state">No tienes tesistas asignados.</p><?php endif; ?>
        <?php foreach ($tesistas as $item): ?>
            <?php $notas = $defensas->notasPorEtapa((int) $item['id_expediente'], true); ?>
            <?php $proxima = $item['estado'] === 'activo' && in_array($item['etapa_actual'], ['mg1', 'mg2'], true) ? $defensas->programada((int) $item['id_expediente'], (string) $item['etapa_actual']) : null; ?>
            <article class="mg-ficha-breve">
                <h3><?= e($item['estudiante']) ?> <small>R.U. <?= e((string) $item['registro_universitario']) ?> · <?= e($item['correo_estudiante']) ?></small></h3>
                <p><?= e($item['modalidad']) ?> · <?= e($item['cohorte']) ?> · <?= mg_badge_etapa((string) $item['etapa_actual']) ?> <?= mg_badge_estado((string) $item['estado']) ?></p>
                <?php if ($item['titulo_trabajo']): ?><p><em><?= e($item['titulo_trabajo']) ?></em></p><?php endif; ?>
                <p><?= $proxima ? 'Defensa de ' . e(MgTribunal::ETAPAS[$proxima['etapa']]) . ': ' . e(mg_fecha_corta($proxima['fecha'])) . ' ' . e(substr((string) $proxima['hora_inicio'], 0, 5)) . ' · ' . e($proxima['ambiente']) : 'Sin defensa programada.' ?>
                    <?php foreach ($notas as $etapa => $nota): ?> · Nota <?= e(MgTribunal::ETAPAS[$etapa]) ?>: <strong><?= e(number_format($nota, 2)) ?></strong><?php endforeach; ?></p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="card mg-seccion">
        <div class="section-heading"><div><span class="eyebrow">Como tribunal</span><h2>Defensas donde evalúas</h2></div></div>
        <?php if (!$comoTribunal): ?><p class="empty-state">No eres tribunal de ningún expediente activo.</p><?php endif; ?>
        <ul class="mg-lista">
            <?php foreach ($comoTribunal as $item): ?>
                <li><strong><?= e($item['expediente']['estudiante']) ?></strong> · <?= e($item['expediente']['modalidad']) ?> · <?= e(MgTribunal::ETAPAS[$item['etapa']]) ?> (tribunal <?= (int) $item['orden'] ?>)
                    · <?= $item['defensa'] ? e(mg_fecha_corta($item['defensa']['fecha'])) . ' ' . e(substr((string) $item['defensa']['hora_inicio'], 0, 5)) . ' · ' . e($item['defensa']['ambiente']) : 'defensa sin programar' ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="panel-note">El registro de reuniones con tus tesistas llega en el MVP-2.</p>
    </section>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
