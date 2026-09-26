<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar período' : 'Nuevo período';
$action = $isEditing ? app_url('periodos/edit.php?id=' . (int) $data['id_periodo']) : app_url('periodos/create.php');
$editables = $editables ?? ['nombre', 'id_tipo_tutoria', 'fecha_inicio', 'fecha_fin', 'cupo_min_grupo', 'cupo_max_default', 'max_grupos_tutor', 'modalidad_ambas'];
$esActivo = ($data['estado'] ?? 'borrador') === 'activa';
$bloqueado = static fn (string $campo): string => in_array($campo, $editables, true) ? '' : ' disabled';
require __DIR__ . '/../layouts/header.php';
?>

<main class="container narrow">
    <section class="card">
        <h1><?= e($title) ?></h1>
        <?php if ($isEditing): ?>
            <p class="panel-note">Estado: <span class="badge badge-<?= e($data['estado']) ?>"><?= e(PeriodosController::ESTADOS[$data['estado']] ?? $data['estado']) ?></span></p>
        <?php else: ?>
            <p class="panel-note">El período se crea en <strong>borrador</strong>. Actívalo desde la lista cuando esté listo.</p>
        <?php endif; ?>
        <?php if ($esActivo): ?>
            <p class="banner-warning" role="status">Período activo: los cambios de cupo y de modalidad aplican solo a los grupos nuevos. La fecha de fin solo se puede extender; los grupos aprobados reciben las sesiones de las semanas nuevas.<?= in_array('fecha_inicio', $editables, true) ? '' : ' La fecha de inicio ya no se cambia porque el período tiene grupos.' ?></p>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="<?= e($action) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="nombre">Nombre del período</label>
            <input id="nombre" name="nombre" type="text" minlength="3" maxlength="120" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Tutorías Julio 2027 (Gestión I)"<?= $bloqueado('nombre') ?>>
            <label for="id_tipo_tutoria">Tipo de tutoría</label>
            <select id="id_tipo_tutoria" name="id_tipo_tutoria" required aria-describedby="tipo_ayuda"<?= $bloqueado('id_tipo_tutoria') ?>>
                <?php foreach ($tipos as $tipoOpcion): ?>
                    <option value="<?= (int) $tipoOpcion['id_tipo_tutoria'] ?>" <?= (int) ($data['id_tipo_tutoria'] ?? 0) === (int) $tipoOpcion['id_tipo_tutoria'] ? 'selected' : '' ?>><?= e($tipoOpcion['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint" id="tipo_ayuda">
                <?= in_array('id_tipo_tutoria', $editables, true) ? 'Solo se cambia mientras el período está en borrador.' : 'El tipo ya no se cambia: el período está activo.' ?>
                Duración máxima: <?= e(implode(' · ', array_map(static fn (array $t): string => $t['nombre'] . ' ' . ($t['duracion_max_dias'] !== null ? (int) $t['duracion_max_dias'] . ' días' : 'sin tope'), $tipos))) ?>.
                <?php if (!$tipos): ?>No hay tipos activos: <a href="<?= e(app_url('tipos-tutoria/create.php')) ?>">crea uno</a>.<?php endif; ?>
            </p>
            <div class="form-grid">
                <div>
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" required value="<?= e($data['fecha_inicio'] ?? '') ?>"<?= $bloqueado('fecha_inicio') ?>>
                </div>
                <div>
                    <label for="fecha_fin">Fecha de fin</label>
                    <input id="fecha_fin" name="fecha_fin" type="date" required value="<?= e($data['fecha_fin'] ?? '') ?>"<?= $esActivo ? ' min="' . e($periodo['fecha_fin'] ?? '') . '"' : '' ?><?= $bloqueado('fecha_fin') ?>>
                </div>
                <div>
                    <label for="cupo_min_grupo">Cupo mínimo por grupo</label>
                    <input id="cupo_min_grupo" name="cupo_min_grupo" type="number" min="1" max="100" required value="<?= e((string) ($data['cupo_min_grupo'] ?? 3)) ?>"<?= $bloqueado('cupo_min_grupo') ?>>
                </div>
                <div>
                    <label for="cupo_max_default">Cupo máximo por grupo</label>
                    <input id="cupo_max_default" name="cupo_max_default" type="number" min="1" max="200" required value="<?= e((string) ($data['cupo_max_default'] ?? 20)) ?>"<?= $bloqueado('cupo_max_default') ?>>
                </div>
                <div>
                    <label for="max_grupos_tutor">Máximo de grupos por tutor</label>
                    <input id="max_grupos_tutor" name="max_grupos_tutor" type="number" min="1" max="2" required value="<?= e((string) ($data['max_grupos_tutor'] ?? 2)) ?>" aria-describedby="max_grupos_tutor_ayuda"<?= $bloqueado('max_grupos_tutor') ?>>
                </div>
            </div>
            <p class="form-hint">El período no tiene que ocupar el mes completo: puede durar lo que se necesite dentro del máximo de su tipo.</p>
            <p class="form-hint" id="max_grupos_tutor_ayuda">Cada tutor lleva como máximo esta cantidad de grupos en el período, siempre en turnos distintos. Cada estudiante lleva una sola tutoría.</p>
            <label for="modalidad_ambas">Modalidad cuando el tutor acepta ambas</label>
            <select id="modalidad_ambas" name="modalidad_ambas" aria-describedby="modalidad_ambas_ayuda"<?= $bloqueado('modalidad_ambas') ?>>
                <?php foreach (['virtual' => 'Virtual primero', 'presencial' => 'Presencial primero'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($data['modalidad_ambas'] ?? 'virtual') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint" id="modalidad_ambas_ayuda">Se aplica a los grupos nuevos de materias sin modalidad requerida cuyo tutor acepta presencial y virtual. La coordinación puede cambiar la modalidad de un grupo mientras esté por aprobar.</p>
            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('periodos/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
