<?php

// Reuniones de Modalidades de Grado para la Coordinacion (HU-035): por validar,
// validadas y observadas, con filtros. Validar/observar requiere mg.validar.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$title = 'Reuniones de grado';
$activePage = 'mg-reuniones';

$modelo = new MgReunion();
$filtros = [
    'estado' => is_string($_GET['estado'] ?? null) ? $_GET['estado'] : 'registrada',
    'id_cohorte' => (int) filter_input(INPUT_GET, 'id_cohorte', FILTER_VALIDATE_INT),
    'id_tutor' => (int) filter_input(INPUT_GET, 'id_tutor', FILTER_VALIDATE_INT),
    'desde' => is_string($_GET['desde'] ?? null) && mg_fecha_valida($_GET['desde']) ? $_GET['desde'] : '',
    'hasta' => is_string($_GET['hasta'] ?? null) && mg_fecha_valida($_GET['hasta']) ? $_GET['hasta'] : '',
];
if ($filtros['estado'] !== 'todas' && !isset(MgReunion::ESTADOS[$filtros['estado']])) {
    $filtros['estado'] = 'registrada';
}
$reuniones = $modelo->listar(['estado' => $filtros['estado'] === 'todas' ? '' : $filtros['estado']] + $filtros);
$conteo = $modelo->contarPorEstado();
$cohortes = (new MgCatalogo())->cohortes();
$tutores = Database::connection()->query(
    "SELECT DISTINCT t.id_tutor, CONCAT(u.apellido, ', ', u.nombre) AS docente FROM asignaciones_tutor_mg a
     INNER JOIN tutores t ON t.id_tutor = a.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario ORDER BY docente"
)->fetchAll();
$puedeValidar = Auth::canDo('mg.validar');
[$message, $error] = mg_flash(['validada' => 'Reunión validada.', 'observada' => 'Reunión observada. Se notificó al tutor.']);
$ocultos = static function (array $filtros): string {
    $html = '';
    foreach ($filtros as $clave => $valor) {
        if ($valor !== '' && $valor !== 0) {
            $html .= '<input type="hidden" name="' . e($clave) . '" value="' . e((string) $valor) . '">';
        }
    }

    return $html;
};

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Reuniones</h1>
            <p>Reuniones que registran los tutores con sus tesistas. La Coordinación las valida u observa; una validada ya no la edita el tutor.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <nav class="mg-tabs" aria-label="Estado de validación">
        <?php foreach (MgReunion::ESTADOS + ['todas' => 'Todas'] as $valor => $label): ?>
            <a href="<?= e(app_url('mg/reuniones/?' . http_build_query(['estado' => $valor] + array_filter(array_diff_key($filtros, ['estado' => 1]))))) ?>" <?= $filtros['estado'] === $valor ? 'aria-current="page"' : '' ?>><?= e($label) ?><?= $valor !== 'todas' ? ' (' . (int) ($conteo[$valor] ?? 0) . ')' : '' ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="card mg-filtros">
        <input type="hidden" name="estado" value="<?= e($filtros['estado']) ?>">
        <div><label for="id_cohorte">Cohorte</label><select id="id_cohorte" name="id_cohorte"><option value="">Todas</option><?php foreach ($cohortes as $item): ?><option value="<?= (int) $item['id_cohorte'] ?>" <?= $filtros['id_cohorte'] === (int) $item['id_cohorte'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></div>
        <div><label for="id_tutor">Tutor</label><select id="id_tutor" name="id_tutor"><option value="">Todos</option><?php foreach ($tutores as $item): ?><option value="<?= (int) $item['id_tutor'] ?>" <?= $filtros['id_tutor'] === (int) $item['id_tutor'] ? 'selected' : '' ?>><?= e($item['docente']) ?></option><?php endforeach; ?></select></div>
        <div><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
        <div><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
        <div class="mg-filtros-acciones"><button type="submit">Filtrar</button></div>
    </form>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Fecha</th><th>Estudiante</th><th>Tutor</th><th>Modalidad y temas</th><th>Asistencia</th><th>Estado</th><?php if ($puedeValidar): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($reuniones as $reunion): ?>
                    <tr>
                        <td><?= e(mg_fecha_corta($reunion['fecha'])) ?><br><small><?= e(substr((string) $reunion['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $reunion['hora_fin'], 0, 5)) ?></small></td>
                        <td><a href="<?= e(app_url('mg/seguimiento.php?expediente=' . (int) $reunion['id_expediente'] . '#reuniones')) ?>"><?= e($reunion['estudiante']) ?></a><br><small><?= e($reunion['cohorte']) ?> · <?= e(MgExpediente::ETAPAS[$reunion['etapa_actual']]) ?></small></td>
                        <td><?= e($reunion['tutor']) ?></td>
                        <td><small><?= e(MgReunion::MODALIDADES[$reunion['modalidad']]) ?> · <?= e($reunion['lugar_o_enlace']) ?></small><br><?= e(mb_strimwidth((string) $reunion['temas'], 0, 140, '…')) ?></td>
                        <td><small>Est.: <?= $reunion['asistio_estudiante'] === 'si' ? 'sí' : '<strong>no</strong>' ?> · Tutor: <?= $reunion['asistio_tutor'] === 'si' ? 'sí' : '<strong>no</strong>' ?></small></td>
                        <td><span class="badge <?= $reunion['estado_validacion'] === 'validada' ? 'badge-success' : ($reunion['estado_validacion'] === 'observada' ? 'badge-danger' : 'badge-warning') ?>"><?= e(MgReunion::ESTADOS[$reunion['estado_validacion']]) ?></span>
                            <?php if ($reunion['estado_validacion'] === 'observada' && $reunion['motivo_observacion']): ?><br><small><?= e($reunion['motivo_observacion']) ?></small><?php endif; ?></td>
                        <?php if ($puedeValidar): ?>
                            <td class="actions mg-acciones">
                                <?php if ($reunion['estado_validacion'] !== 'validada'): ?>
                                    <form method="post" action="<?= e(app_url('mg/reuniones/validar.php')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $reunion['id_reunion'] ?>">
                                        <input type="hidden" name="accion" value="validar">
                                        <?= $ocultos($filtros) ?>
                                        <button class="link-button mg-link" type="submit">Validar</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($reunion['estado_validacion'] !== 'observada'): ?>
                                    <details><summary>Observar</summary>
                                        <form method="post" action="<?= e(app_url('mg/reuniones/validar.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $reunion['id_reunion'] ?>">
                                            <input type="hidden" name="accion" value="observar">
                                            <?= $ocultos($filtros) ?>
                                            <label for="motivo-<?= (int) $reunion['id_reunion'] ?>">Qué debe corregir</label>
                                            <textarea id="motivo-<?= (int) $reunion['id_reunion'] ?>" name="motivo" rows="2" maxlength="500" required></textarea>
                                            <button class="small" type="submit">Observar</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <a href="<?= e(app_url('mg/reuniones/form.php?id=' . (int) $reunion['id_reunion'])) ?>">Corregir</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$reuniones): ?><tr><td colspan="<?= $puedeValidar ? 7 : 6 ?>" class="empty-state">No hay reuniones con esos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
