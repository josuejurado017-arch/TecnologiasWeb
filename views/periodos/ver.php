<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$fecha = static fn (?string $valor): string => $valor ? date('d/m/Y H:i', strtotime($valor)) : '—';
$etiquetasResumen = [
    'grupos_a_finalizar' => 'Grupos confirmados o en curso (se finalizan)',
    'grupos_formacion_con_sesiones' => 'Grupos en formación con sesiones dictadas (se finalizan)',
    'grupos_a_cancelar' => 'Grupos en formación o por aprobar sin sesiones (se cancelan)',
    'grupos_ya_finalizados' => 'Grupos ya finalizados',
    'grupos_ya_cancelados' => 'Grupos ya cancelados',
    'sesiones_realizadas' => 'Sesiones realizadas',
    'sesiones_sin_registro' => 'Sesiones pasadas sin asistencia (quedan "sin registro")',
    'sesiones_a_cancelar' => 'Sesiones futuras (se cancelan)',
    'estudiantes_atendidos' => 'Estudiantes atendidos',
    'demanda_atendida' => 'Solicitudes atendidas',
    'demanda_a_vencer' => 'Solicitudes sin atender (quedan "vencidas")',
    'lista_espera_a_cancelar' => 'Inscripciones en lista de espera (se cancelan)',
    'evaluaciones_pendientes' => 'Evaluaciones pendientes de los estudiantes',
];
?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1><?= e($periodo['nombre']) ?></h1>
            <p><span class="badge badge-<?= e($periodo['estado']) ?>"><?= e(PeriodosController::ESTADOS[$periodo['estado']] ?? $periodo['estado']) ?></span>
                · <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_inicio']))) ?> al <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_fin']))) ?></p>
        </div>
        <div class="actions">
            <?php if ($periodo['estado'] !== 'cerrada'): ?>
                <a class="button secondary" href="<?= e(app_url('periodos/edit.php?id=' . (int) $periodo['id_periodo'])) ?>">Editar</a>
            <?php endif; ?>
            <?php if ($periodo['estado'] === 'activa'): ?>
                <a class="button" href="<?= e(app_url('periodos/cerrar.php?id=' . (int) $periodo['id_periodo'])) ?>">Cerrar período</a>
            <?php endif; ?>
            <?php if ($periodo['estado'] !== 'borrador'): ?>
                <a class="button secondary" href="<?= e(app_url('reportes/campania.php?periodo=' . (int) $periodo['id_periodo'])) ?>">Reportes</a>
            <?php endif; ?>
            <a class="button secondary" href="<?= e(app_url('periodos/')) ?>">Volver</a>
        </div>
    </div>

    <?php if ($message): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($periodo['estado'] === 'cerrada'): ?>
        <p class="panel-note">Período cerrado el <strong><?= e($fecha($periodo['fecha_cierre'])) ?></strong><?= $periodo['cerrado_por'] ? ' por ' . e($periodo['cerrado_por']) : '' ?>. Es historial: no se edita, reactiva ni elimina.
            <?php if ($periodo['evaluaciones_hasta']): ?>Los estudiantes pudieron evaluar hasta el <?= e(date('d/m/Y', strtotime((string) $periodo['evaluaciones_hasta']))) ?>.<?php endif; ?></p>
    <?php endif; ?>

    <section class="card">
        <h2>Configuración</h2>
        <div class="table-wrapper"><table><tbody>
            <tr><td>Cupo mínimo / máximo por grupo</td><td><?= (int) $periodo['cupo_min_grupo'] ?> / <?= (int) $periodo['cupo_max_default'] ?></td></tr>
            <tr><td>Máximo de grupos por tutor</td><td><?= (int) $periodo['max_grupos_tutor'] ?></td></tr>
            <tr><td>Modalidad si el tutor acepta ambas</td><td><?= $periodo['modalidad_ambas'] === 'presencial' ? 'Presencial' : 'Virtual' ?></td></tr>
            <tr><td>Creado</td><td><?= e($fecha($periodo['fecha_registro'])) ?></td></tr>
            <tr><td>Activado</td><td><?= e($fecha($periodo['fecha_activacion'])) ?><?= !empty($periodo['activado_por']) ? ' · ' . e($periodo['activado_por']) : '' ?></td></tr>
            <tr><td>Cerrado</td><td><?= e($fecha($periodo['fecha_cierre'])) ?><?= !empty($periodo['cerrado_por']) ? ' · ' . e($periodo['cerrado_por']) : '' ?></td></tr>
        </tbody></table></div>
    </section>

    <?php if ($resumen): ?>
        <section class="card">
            <h2><?= $periodo['estado'] === 'cerrada' ? 'Resumen al cierre' : 'Situación actual' ?></h2>
            <?php if ($periodo['estado'] === 'activa'): ?><p class="form-hint">Lo que pasaría si cerraras el período hoy.</p><?php endif; ?>
            <div class="table-wrapper"><table><tbody>
                <?php foreach ($etiquetasResumen as $clave => $etiqueta): ?>
                    <?php if (!array_key_exists($clave, $resumen)) { continue; } ?>
                    <tr><td><?= e($etiqueta) ?></td><td><?= (int) $resumen[$clave] ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php elseif ($periodo['estado'] === 'cerrada'): ?>
        <p class="panel-note">Este período se cerró antes de que el sistema guardara resúmenes de cierre. Consulta sus reportes.</p>
    <?php endif; ?>

    <section class="card">
        <h2>Observaciones administrativas</h2>
        <form method="post" action="<?= e(app_url('periodos/observacion.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $periodo['id_periodo'] ?>">
            <label for="texto">Nueva observación</label>
            <textarea id="texto" name="texto" required minlength="4" maxlength="1000" rows="3"></textarea>
            <p class="form-hint">Las observaciones quedan registradas con tu nombre y la fecha; no se editan ni se borran.</p>
            <button type="submit">Agregar observación</button>
        </form>
        <?php foreach ($observaciones as $o): ?>
            <article class="panel-note">
                <strong><?= e($o['autor']) ?></strong> · <small><?= e($fecha($o['fecha'])) ?></small>
                <p><?= nl2br(e($o['texto'])) ?></p>
            </article>
        <?php endforeach; ?>
        <?php if (!$observaciones): ?><p class="empty-state">Sin observaciones.</p><?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
