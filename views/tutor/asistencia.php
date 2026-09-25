<?php
require __DIR__ . '/../layouts/header.php';
$sesion = $data['sesion'];
$inscritos = $data['inscritos'];
$registradas = $data['registradas'];
$estados = ['asistio' => 'Asistio', 'parcial' => 'Parcial', 'retraso' => 'Retraso', 'no_asistio' => 'No asistio'];
?>

<main class="container narrow-wide">
    <section class="card">
        <div class="section-heading">
            <div>
                <span class="eyebrow"><?= e($sesion['dia_semana']) ?> · <?= e(substr((string) $sesion['hora_inicio'], 0, 5)) ?>-<?= e(substr((string) $sesion['hora_fin'], 0, 5)) ?></span>
                <h1>Asistencia · <?= e($sesion['nombre_materia']) ?></h1>
            </div>
            <span class="badge badge-<?= e($sesion['estado']) ?>"><?= e(ucfirst((string) $sesion['estado'])) ?></span>
        </div>
        <p class="panel-note">Sesion del <strong><?= e($sesion['fecha']) ?></strong></p>

        <?php if ($saved): ?><p class="success" role="status">Asistencia registrada correctamente.</p><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php $soloLectura = $sesion['estado_periodo'] !== 'activa'; ?>
        <?php if ($soloLectura): ?>
            <p class="banner-warning" role="status">El período de esta sesión está cerrado: la asistencia se muestra solo para consulta.</p>
        <?php endif; ?>

        <?php if (!$inscritos): ?>
            <p class="empty-state">Este grupo aun no tiene inscritos.</p>
        <?php else: ?>
            <form method="post" action="<?= e(app_url('tutor/asistencia.php?sesion=' . (int) $sesion['id_sesion'])) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Estudiante</th><th>Estado</th><th>Min. retraso</th><th>Observaciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($inscritos as $ins): $iid = (int) $ins['id_inscripcion']; $reg = $registradas[$iid] ?? null; ?>
                                <tr>
                                    <td><?= e($ins['estudiante']) ?></td>
                                    <td>
                                        <select name="estado[<?= $iid ?>]">
                                            <option value="">Sin marcar</option>
                                            <?php foreach ($estados as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= ($reg['estado'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="minutos[<?= $iid ?>]" min="0" max="300" value="<?= e((string) ($reg['minutos_retraso'] ?? '')) ?>" style="max-width:6rem;"></td>
                                    <td><input type="text" name="observaciones[<?= $iid ?>]" maxlength="500" value="<?= e((string) ($reg['observaciones'] ?? '')) ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$soloLectura): ?><button type="submit">Guardar asistencia</button><?php endif; ?>
                <a class="button secondary" href="<?= e(app_url('mis-grupos/')) ?>">Volver a mis grupos</a>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
