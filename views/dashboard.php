<?php
$title = 'Resumen general';
$role = (string) ($user['nombre_rol'] ?? '');
$statValues = [
    'total_usuarios' => (int) ($stats['total_usuarios'] ?? 0),
    'usuarios_activos' => (int) ($stats['usuarios_activos'] ?? 0),
    'usuarios_pendientes' => (int) ($stats['usuarios_pendientes'] ?? 0),
    'total_tutores' => (int) ($stats['total_tutores'] ?? 0),
    'total_estudiantes' => (int) ($stats['total_estudiantes'] ?? 0),
    'total_carreras' => (int) ($stats['total_carreras'] ?? 0),
    'total_materias' => (int) ($stats['total_materias'] ?? 0),
    'tutorias_pendientes' => (int) ($stats['tutorias_pendientes'] ?? 0),
    'tutorias_confirmadas' => (int) ($stats['tutorias_confirmadas'] ?? 0),
    'tutorias_realizadas' => (int) ($stats['tutorias_realizadas'] ?? 0),
    'horarios_configurados' => (int) ($stats['horarios_configurados'] ?? 0),
    'evaluaciones_pendientes' => (int) ($stats['evaluaciones_pendientes'] ?? 0),
    'materias_disponibles' => (int) ($stats['materias_disponibles'] ?? 0),
    'semestre' => (int) ($stats['semestre'] ?? 0),
    'tutorias_totales' => (int) ($stats['tutorias_totales'] ?? 0),
];
$upcoming = is_array($upcoming ?? null) ? $upcoming : [];

$calendarStart = new DateTimeImmutable('first day of this month');
$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$monthLabel = $monthNames[(int) $calendarStart->format('n')] . ' ' . $calendarStart->format('Y');
$calendarDays = (int) $calendarStart->format('t');
$calendarOffset = (int) $calendarStart->format('N') - 1;
$calendarEvents = [];
foreach ($upcoming as $event) {
    $eventDate = (string) ($event['fecha'] ?? '');
    if ($eventDate !== '') {
        $calendarEvents[$eventDate] = ($calendarEvents[$eventDate] ?? 0) + 1;
    }
}

$activeRate = $statValues['total_usuarios'] > 0 ? min(100, (int) round($statValues['usuarios_activos'] / $statValues['total_usuarios'] * 100)) : 0;
$semesterProgress = min(100, $statValues['semestre'] > 0 ? $statValues['semestre'] * 10 : 0);
$sessionsProgress = $statValues['tutorias_totales'] > 0 ? min(100, (int) round($statValues['tutorias_realizadas'] / $statValues['tutorias_totales'] * 100)) : 0;
$tutorProgressTotal = $statValues['tutorias_pendientes'] + $statValues['tutorias_confirmadas'] + $statValues['tutorias_realizadas'];
$tutorProgress = $tutorProgressTotal > 0 ? min(100, (int) round($statValues['tutorias_realizadas'] / $tutorProgressTotal * 100)) : 0;

if ($role === 'administrador') {
    $chartType = 'doughnut';
    $chartData = ['labels' => ['Usuarios', 'Estudiantes', 'Tutores', 'Materias'], 'values' => [$statValues['total_usuarios'], $statValues['total_estudiantes'], $statValues['total_tutores'], $statValues['total_materias']]];
} elseif ($role === 'tutor') {
    $chartType = 'bar';
    $chartData = ['labels' => ['Pendientes', 'Confirmadas', 'Realizadas'], 'values' => [$statValues['tutorias_pendientes'], $statValues['tutorias_confirmadas'], $statValues['tutorias_realizadas']]];
} else {
    $chartType = 'doughnut';
    $chartData = ['labels' => ['Pendientes', 'Confirmadas', 'Por evaluar'], 'values' => [$statValues['tutorias_pendientes'], $statValues['tutorias_confirmadas'], $statValues['evaluaciones_pendientes']]];
}

require __DIR__ . '/layouts/header.php';
?>

<main class="container dashboard-page">
    <section class="hero-heading dashboard-hero">
        <div>
            <div class="hero-kicker">Panel principal</div>
            <h1>Hola, <?= e($user['nombre'] ?? 'usuario') ?>.</h1>
            <p>Una vista clara de lo que requiere tu atencion hoy.</p>
        </div>
        <div class="hero-actions">
            <span class="status status-activo"><span class="status-pulse"></span>Sesion activa</span>
            <?php if ($role === 'estudiante'): ?><a class="button hero-button" href="<?= e(app_url('tutorias/create.php')) ?>">Solicitar tutoria <span aria-hidden="true">+</span></a><?php elseif ($role === 'tutor'): ?><a class="button hero-button" href="<?= e(app_url('disponibilidad/')) ?>">Actualizar horarios <span aria-hidden="true">+</span></a><?php else: ?><a class="button hero-button" href="<?= e(app_url('usuarios/')) ?>">Gestionar cuentas <span aria-hidden="true">+</span></a><?php endif; ?>
        </div>
    </section>

    <?php if ($role === 'administrador'): ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del sistema">
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Usuarios</span><span class="stat-icon">US</span></div><strong class="stat-value"><?= $statValues['total_usuarios'] ?></strong><span class="stat-caption"><?= $statValues['usuarios_activos'] ?> activos</span></article>
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Estudiantes</span><span class="stat-icon">ES</span></div><strong class="stat-value"><?= $statValues['total_estudiantes'] ?></strong><span class="stat-caption">Perfiles academicos</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Tutores</span><span class="stat-icon">TU</span></div><strong class="stat-value"><?= $statValues['total_tutores'] ?></strong><span class="stat-caption">Perfiles profesionales</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Solicitudes</span><span class="stat-icon">TI</span></div><strong class="stat-value"><?= $statValues['tutorias_pendientes'] ?></strong><span class="stat-caption">Pendientes de atencion</span></article>
        </section>
    <?php elseif ($role === 'tutor'): ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del tutor">
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Mis materias</span><span class="stat-icon">MA</span></div><strong class="stat-value"><?= (int) ($stats['materias_asignadas'] ?? 0) ?></strong><span class="stat-caption">Asignaturas activas</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Pendientes</span><span class="stat-icon">PE</span></div><strong class="stat-value"><?= $statValues['tutorias_pendientes'] ?></strong><span class="stat-caption">Esperan respuesta</span></article>
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Confirmadas</span><span class="stat-icon">CO</span></div><strong class="stat-value"><?= $statValues['tutorias_confirmadas'] ?></strong><span class="stat-caption">Proximas sesiones</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Mi promedio</span><span class="stat-icon">EV</span></div><strong class="stat-value"><?= e(number_format((float) ($stats['promedio_calificacion'] ?? 0), 2)) ?><small>/5</small></strong><span class="stat-caption">Valoracion recibida</span></article>
        </section>
    <?php else: ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del estudiante">
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Pendientes</span><span class="stat-icon">PE</span></div><strong class="stat-value"><?= $statValues['tutorias_pendientes'] ?></strong><span class="stat-caption">Esperan confirmacion</span></article>
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Confirmadas</span><span class="stat-icon">CO</span></div><strong class="stat-value"><?= $statValues['tutorias_confirmadas'] ?></strong><span class="stat-caption">Proximas sesiones</span></article>
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Por evaluar</span><span class="stat-icon">EV</span></div><strong class="stat-value"><?= $statValues['evaluaciones_pendientes'] ?></strong><span class="stat-caption">Comparte tu experiencia</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Materias</span><span class="stat-icon">MA</span></div><strong class="stat-value"><?= $statValues['materias_disponibles'] ?></strong><span class="stat-caption">Con tutores activos</span></article>
        </section>
    <?php endif; ?>

    <div class="dashboard-layout">
        <div class="dashboard-main-column">
            <section class="dashboard-panels">
                <article class="card chart-card">
                    <div class="section-heading"><div><span class="eyebrow">Actividad reciente</span><h2><?= $role === 'administrador' ? 'Estado del sistema' : 'Resumen de tutorias' ?></h2></div><span class="chart-period">Actual</span></div>
                    <div class="chart-wrap"><canvas data-dashboard-chart data-chart-type="<?= e($chartType) ?>" data-chart-data="<?= e(json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Grafico de actividad" role="img"></canvas></div>
                </article>

                <?php if ($role === 'administrador'): ?>
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Salud operativa</span><h2>Usuarios activos</h2></div><span class="progress-value"><?= $activeRate ?>%</span></div><div class="progress-track"><span style="width: <?= $activeRate ?>%"></span></div><p class="panel-note"><?= $statValues['usuarios_pendientes'] ?> cuenta(s) pendiente(s) de aprobacion.</p><div class="metric-list"><div><span>Materias</span><strong><?= $statValues['total_materias'] ?></strong></div><div><span>Carreras</span><strong><?= $statValues['total_carreras'] ?></strong></div></div></article>
                <?php elseif ($role === 'tutor'): ?>
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Operacion</span><h2>Atencion completada</h2></div><span class="progress-value"><?= $tutorProgress ?>%</span></div><div class="progress-track"><span style="width: <?= $tutorProgress ?>%"></span></div><p class="panel-note">Sesiones realizadas frente a tu actividad registrada.</p><div class="availability-status <?= $statValues['horarios_configurados'] > 0 ? 'is-available' : 'is-unavailable' ?>"><span class="availability-dot"></span><span><strong><?= $statValues['horarios_configurados'] > 0 ? 'Disponible' : 'Sin disponibilidad' ?></strong><small><?= $statValues['horarios_configurados'] > 0 ? $statValues['horarios_configurados'] . ' horario(s) publicado(s)' : 'Publica un horario para recibir solicitudes' ?></small></span><a href="<?= e(app_url('disponibilidad/')) ?>">Gestionar</a></div></article>
                <?php else: ?>
                    <article class="card progress-card academic-progress"><div class="section-heading"><div><span class="eyebrow">Mi progreso</span><h2>Trayectoria academica</h2></div><span class="progress-value"><?= $statValues['semestre'] > 0 ? 'S' . $statValues['semestre'] : 'Sin perfil' ?></span></div><div class="academic-meter"><div class="progress-ring" style="--progress: <?= $semesterProgress ?>%"><strong><?= $statValues['semestre'] > 0 ? $statValues['semestre'] : '-' ?></strong><span>semestre</span></div><div><strong><?= e($stats['nombre_carrera'] ?? 'Carrera pendiente') ?></strong><p>Avance visual del plan de estudios de referencia.</p><div class="progress-track"><span style="width: <?= $semesterProgress ?>%"></span></div><small><?= $sessionsProgress ?>% de tutorias completadas</small></div></div></article>
                <?php endif; ?>
            </section>

            <section class="card upcoming-card">
                <div class="section-heading"><div><span class="eyebrow">Agenda</span><h2>Proximas tutorias</h2></div><a href="<?= e(app_url('tutorias/')) ?>">Ver agenda completa</a></div>
                <?php if (!$upcoming): ?><div class="empty-panel"><span class="empty-icon">--</span><strong>No hay tutorias proximas</strong><p>Cuando exista una sesion pendiente o confirmada aparecera aqui.</p></div><?php else: ?><div class="upcoming-list"><?php foreach ($upcoming as $event): ?><a class="upcoming-item" href="<?= e(app_url('tutorias/')) ?>"><time datetime="<?= e($event['fecha']) ?>"><strong><?= e(date('d', strtotime((string) $event['fecha']))) ?></strong><span><?= e($monthNames[(int) date('n', strtotime((string) $event['fecha']))]) ?></span></time><span class="upcoming-info"><strong><?= e($event['nombre_materia']) ?></strong><small><?= e(substr((string) $event['hora_inicio'], 0, 5) . ' - ' . substr((string) $event['hora_fin'], 0, 5)) ?> · <?= e($role === 'tutor' ? $event['estudiante'] : $event['tutor']) ?></small></span><span class="status status-<?= e($event['estado']) ?>"><?= e($event['estado']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
            </section>
            <section class="card activity-card"><div class="section-heading"><div><span class="eyebrow">Trazabilidad</span><h2>Actividad reciente</h2></div><a href="<?= e(app_url('tutorias/')) ?>">Ver tutorias</a></div><?php if (!$recentActivity): ?><p class="panel-note">Aun no hay actividad registrada para tu espacio.</p><?php else: ?><ul class="activity-list dashboard-activity-list"><?php foreach ($recentActivity as $activity): ?><li><div><strong><?= e(ucfirst(str_replace('_', ' ', $activity['tipo_evento']))) ?></strong><span><?= e($activity['nombre_materia']) ?> · <?= e($activity['motivo'] ?: 'Actualizacion registrada') ?></span></div><time><?= e($activity['fecha_cambio']) ?></time></li><?php endforeach; ?></ul><?php endif; ?></section>
        </div>

        <aside class="dashboard-side-column">
            <section class="card calendar-card"><div class="section-heading"><div><span class="eyebrow">Calendario</span><h2><?= e($monthLabel) ?></h2></div><span class="calendar-mark"><?= count($upcoming) ?></span></div><div class="calendar-week"><?php foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $dayName): ?><span><?= $dayName ?></span><?php endforeach; ?></div><div class="calendar-grid"><?php for ($blank = 0; $blank < $calendarOffset; $blank++): ?><span class="calendar-day is-empty"></span><?php endfor; ?><?php for ($day = 1; $day <= $calendarDays; $day++): ?><?php $dateKey = $calendarStart->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT); $isToday = $dateKey === date('Y-m-d'); ?><a class="calendar-day <?= $isToday ? 'is-today' : '' ?> <?= !empty($calendarEvents[$dateKey]) ? 'has-event' : '' ?>" href="<?= !empty($calendarEvents[$dateKey]) ? e(app_url('tutorias/')) : '#' ?>"><span><?= $day ?></span><?php if (!empty($calendarEvents[$dateKey])): ?><i><?= $calendarEvents[$dateKey] ?></i><?php endif; ?></a><?php endfor; ?></div><div class="calendar-legend"><span><i class="legend-dot"></i> Tutoria programada</span><span><i class="legend-today"></i> Hoy</span></div></section>

            <section class="card quick-action-card"><div class="section-heading"><div><span class="eyebrow">Atajos</span><h2>Acciones rapidas</h2></div><span class="shortcut-hint">1 click</span></div><div class="compact-actions"><?php if ($role === 'administrador'): ?><a href="<?= e(app_url('estudiantes/create.php')) ?>"><span class="action-icon">ES</span><span><strong>Nuevo estudiante</strong><small>Crear perfil academico</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('tutorias/')) ?>"><span class="action-icon">TI</span><span><strong>Revisar solicitudes</strong><small>Ver actividad pendiente</small></span><b aria-hidden="true">&rarr;</b></a><?php elseif ($role === 'tutor'): ?><a href="<?= e(app_url('tutorias/')) ?>"><span class="action-icon">TI</span><span><strong>Atender solicitudes</strong><small>Gestionar tutorias</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('disponibilidad/')) ?>"><span class="action-icon">DI</span><span><strong>Mi disponibilidad</strong><small>Actualizar horarios</small></span><b aria-hidden="true">&rarr;</b></a><?php else: ?><a href="<?= e(app_url('tutorias/create.php')) ?>"><span class="action-icon">TI</span><span><strong>Solicitar tutoria</strong><small>Encuentra apoyo academico</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('tutores-disponibles/')) ?>"><span class="action-icon">TU</span><span><strong>Explorar tutores</strong><small>Consulta especialistas</small></span><b aria-hidden="true">&rarr;</b></a><?php endif; ?></div></section>
        </aside>
    </div>

    <section class="card dashboard-actions dashboard-all-actions"><div class="section-heading"><div><span class="eyebrow">Acceso directo</span><h2><?= $role === 'administrador' ? 'Administracion del portal' : 'Mi espacio' ?></h2></div><span class="section-hint">Todo en un solo lugar</span></div><div class="quick-links"><?php if ($role === 'administrador'): ?><a href="<?= e(app_url('usuarios/')) ?>"><span>US</span><strong>Cuentas de acceso</strong><small>Credenciales y estados</small></a><a href="<?= e(app_url('estudiantes/')) ?>"><span>ES</span><strong>Estudiantes</strong><small>Perfiles academicos</small></a><a href="<?= e(app_url('tutores/')) ?>"><span>TU</span><strong>Tutores</strong><small>Perfiles profesionales</small></a><a href="<?= e(app_url('materias/')) ?>"><span>MA</span><strong>Materias</strong><small>Catalogo academico</small></a><a href="<?= e(app_url('carreras/')) ?>"><span>CA</span><strong>Carreras</strong><small>Clasificacion academica</small></a><a href="<?= e(app_url('permisos/')) ?>"><span>PE</span><strong>Permisos</strong><small>Acceso por rol</small></a><?php elseif ($role === 'tutor'): ?><a href="<?= e(app_url('mis-materias/')) ?>"><span>MA</span><strong>Mis materias</strong><small>Gestionar asignaturas</small></a><a href="<?= e(app_url('disponibilidad/')) ?>"><span>DI</span><strong>Disponibilidad</strong><small>Definir horarios</small></a><a href="<?= e(app_url('tutorias/')) ?>"><span>TI</span><strong>Tutorias</strong><small>Atender solicitudes</small></a><a href="<?= e(app_url('evaluaciones/')) ?>"><span>EV</span><strong>Evaluaciones</strong><small>Revisar opiniones</small></a><?php else: ?><a href="<?= e(app_url('materias-disponibles/')) ?>"><span>MA</span><strong>Materias</strong><small>Asignaturas disponibles</small></a><a href="<?= e(app_url('tutores-disponibles/')) ?>"><span>TU</span><strong>Tutores</strong><small>Conocer tutores activos</small></a><a href="<?= e(app_url('horarios-disponibles/')) ?>"><span>HO</span><strong>Horarios</strong><small>Consultar disponibilidad</small></a><a href="<?= e(app_url('tutorias/')) ?>"><span>TI</span><strong>Mis tutorias</strong><small>Consultar sesiones</small></a><a href="<?= e(app_url('evaluaciones/')) ?>"><span>EV</span><strong>Evaluaciones</strong><small>Dejar comentarios</small></a><?php endif; ?></div></section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<?php require __DIR__ . '/layouts/footer.php'; ?>
