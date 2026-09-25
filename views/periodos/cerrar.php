<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container narrow-wide">
    <section class="card">
        <h1>Cerrar período</h1>
        <p><strong><?= e($periodo['nombre']) ?></strong> · <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_inicio']))) ?> al <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_fin']))) ?></p>
        <p class="banner-warning" role="status">El cierre es definitivo: el período pasa a ser historial y no se puede reactivar, editar ni eliminar. Después solo podrás consultarlo y agregar observaciones.</p>

        <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

        <h2>Qué va a pasar</h2>
        <div class="table-wrapper"><table><tbody>
            <tr><td>Grupos confirmados o en curso que pasan a <strong>finalizado</strong></td><td><?= $impacto['grupos_a_finalizar'] ?></td></tr>
            <tr><td>Grupos en formación con sesiones dictadas que pasan a <strong>finalizado</strong></td><td><?= $impacto['grupos_formacion_con_sesiones'] ?></td></tr>
            <tr><td>Grupos en formación o por aprobar, sin sesiones dictadas, que se <strong>cancelan</strong></td><td><?= $impacto['grupos_a_cancelar'] ?></td></tr>
            <tr><td>Sesiones futuras que se <strong>cancelan</strong></td><td><?= $impacto['sesiones_a_cancelar'] ?></td></tr>
            <tr><td>Sesiones pasadas sin asistencia que quedan <strong>sin registro</strong></td><td><strong<?= $impacto['sesiones_sin_registro'] > 0 ? ' class="report-warning"' : '' ?>><?= $impacto['sesiones_sin_registro'] ?></strong></td></tr>
            <tr><td>Solicitudes sin atender que quedan <strong>vencidas</strong></td><td><?= $impacto['demanda_a_vencer'] ?></td></tr>
            <tr><td>Inscripciones en lista de espera que se cancelan</td><td><?= $impacto['lista_espera_a_cancelar'] ?></td></tr>
            <tr><td>Evaluaciones pendientes (se pueden completar durante <?= Periodo::DIAS_GRACIA_EVALUACION ?> días)</td><td><?= $impacto['evaluaciones_pendientes'] ?></td></tr>
        </tbody></table></div>
        <?php if ($impacto['sesiones_sin_registro'] > 0): ?>
            <p class="form-hint">Si esas sesiones sí se dictaron, pide a los tutores que registren la asistencia antes de cerrar: después ya no se puede.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('periodos/cerrar.php?id=' . (int) $periodo['id_periodo'])) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="confirmacion">Escribe <strong>CERRAR</strong> para confirmar</label>
            <input id="confirmacion" name="confirmacion" required pattern="CERRAR" autocomplete="off">
            <button type="submit">Cerrar período definitivamente</button>
            <a class="button secondary" href="<?= e(app_url('periodos/ver.php?id=' . (int) $periodo['id_periodo'])) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
