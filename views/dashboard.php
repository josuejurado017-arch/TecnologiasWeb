<?php
$title = 'Resumen general';
$role = (string) ($user['nombre_rol'] ?? '');
$upcoming = is_array($upcoming ?? null) ? $upcoming : [];
$periodoNombre = $stats['periodo_nombre'] ?? null;

$monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$calendarStart = new DateTimeImmutable('first day of this month');
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

if ($role === 'administrador') {
    $chartType = 'doughnut';
    $chartData = ['labels' => ['Grupos', 'Confirmados', 'Cancelados'], 'values' => [(int) ($stats['grupos'] ?? 0), (int) ($stats['grupos_confirmados'] ?? 0), (int) ($stats['grupos_cancelados'] ?? 0)]];
} elseif ($role === 'tutor') {
    $chartType = 'bar';
    $chartData = ['labels' => ['Grupos', 'Estudiantes', 'Sesiones dadas'], 'values' => [(int) ($stats['grupos'] ?? 0), (int) ($stats['estudiantes'] ?? 0), (int) ($stats['sesiones_realizadas'] ?? 0)]];
} else {
    $chartType = 'doughnut';
    $chartData = ['labels' => ['Tutorías', 'Asistencias', 'Por evaluar'], 'values' => [(int) ($stats['tutorias'] ?? 0), (int) ($stats['asistencias'] ?? 0), (int) ($stats['evaluaciones_pendientes'] ?? 0)]];
}

$activeRate = (int) ($stats['total_usuarios'] ?? 0) > 0 ? min(100, (int) round((int) ($stats['usuarios_activos'] ?? 0) / (int) $stats['total_usuarios'] * 100)) : 0;

require __DIR__ . '/layouts/header.php';
?>

<main class="container dashboard-page">
    <section class="hero-heading dashboard-hero">
        <div>
            <div class="hero-kicker">Panel principal<?= $periodoNombre ? ' · ' . e($periodoNombre) : '' ?></div>
            <h1>Hola, <?= e($user['nombre'] ?? 'usuario') ?>.</h1>
            <p>Una vista clara de lo que requiere tu atención hoy.</p>
        </div>
        <div class="hero-actions">
            <span class="status status-activo"><span class="status-pulse"></span>Sesión activa</span>
            <?php if ($role === 'estudiante'): ?><a class="button hero-button" href="<?= e(app_url('tutorias/create.php')) ?>">Solicitar apoyo <span aria-hidden="true">+</span></a><?php elseif ($role === 'tutor'): ?><a class="button hero-button" href="<?= e(app_url('mis-grupos/')) ?>">Ver mis grupos <span aria-hidden="true">+</span></a><?php else: ?><a class="button hero-button" href="<?= e(app_url('grupos/')) ?>">Supervisar grupos <span aria-hidden="true">+</span></a><?php endif; ?>
        </div>
    </section>

    <?php if (!$periodoNombre): ?>
        <p class="alert" role="alert">No hay un período de tutoría activo. <?php if ($role === 'administrador'): ?>Activa una en <a href="<?= e(app_url('periodos/')) ?>">Períodos de tutoría</a>.<?php endif; ?></p>
    <?php endif; ?>

    <?php if ($role === 'administrador'): ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del período">
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Grupos</span><span class="stat-icon">GR</span></div><strong class="stat-value"><?= (int) ($stats['grupos'] ?? 0) ?></strong><span class="stat-caption"><?= (int) ($stats['grupos_confirmados'] ?? 0) ?> confirmados</span></article>
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Estudiantes</span><span class="stat-icon">ES</span></div><strong class="stat-value"><?= (int) ($stats['estudiantes_campania'] ?? 0) ?></strong><span class="stat-caption"><?= (int) ($stats['inscritos'] ?? 0) ?> inscripciones</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Asistencia</span><span class="stat-icon">AS</span></div><strong class="stat-value"><?= (int) ($stats['asistencia_pct'] ?? 0) ?>%</strong><span class="stat-caption">de registros presentes</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Demanda</span><span class="stat-icon">DE</span></div><strong class="stat-value"><?= (int) ($stats['demanda_pendiente'] ?? 0) ?></strong><span class="stat-caption">en lista de espera</span></article>
        </section>
    <?php elseif ($role === 'tutor'): ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del tutor">
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Mis grupos</span><span class="stat-icon">GR</span></div><strong class="stat-value"><?= (int) ($stats['grupos'] ?? 0) ?></strong><span class="stat-caption">En el periodo activo</span></article>
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Estudiantes</span><span class="stat-icon">ES</span></div><strong class="stat-value"><?= (int) ($stats['estudiantes'] ?? 0) ?></strong><span class="stat-caption">Atendidos</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Sesiones</span><span class="stat-icon">SE</span></div><strong class="stat-value"><?= (int) ($stats['sesiones_realizadas'] ?? 0) ?></strong><span class="stat-caption">Realizadas</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Mi promedio</span><span class="stat-icon">EV</span></div><strong class="stat-value"><?= e(number_format((float) ($stats['evaluacion_promedio'] ?? 0), 2)) ?><small>/5</small></strong><span class="stat-caption">Valoración recibida</span></article>
        </section>
    <?php else: ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del estudiante">
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Mis tutorías</span><span class="stat-icon">TI</span></div><strong class="stat-value"><?= (int) ($stats['tutorias'] ?? 0) ?></strong><span class="stat-caption">Grupos asignados</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Asistencias</span><span class="stat-icon">AS</span></div><strong class="stat-value"><?= (int) ($stats['asistencias'] ?? 0) ?></strong><span class="stat-caption">Sesiones presentes</span></article>
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Por evaluar</span><span class="stat-icon">EV</span></div><strong class="stat-value"><?= (int) ($stats['evaluaciones_pendientes'] ?? 0) ?></strong><span class="stat-caption">Comparte tu opinión</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Materias</span><span class="stat-icon">MA</span></div><strong class="stat-value"><?= (int) ($stats['materias_disponibles'] ?? 0) ?></strong><span class="stat-caption">Con oferta activa</span></article>
        </section>
    <?php endif; ?>

    <div class="dashboard-layout">
        <div class="dashboard-main-column">
            <section class="dashboard-panels">
                <article class="card chart-card">
                    <div class="section-heading"><div><span class="eyebrow">Actividad</span><h2><?= $role === 'administrador' ? 'Estado del período' : 'Resumen' ?></h2></div><span class="chart-period">Actual</span></div>
                    <div class="chart-wrap"><canvas data-dashboard-chart data-chart-type="<?= e($chartType) ?>" data-chart-data="<?= e(json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Grafico de actividad" role="img"></canvas></div>
                </article>

                <?php if ($role === 'administrador'): ?>
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Salud operativa</span><h2>Usuarios activos</h2></div><span class="progress-value"><?= $activeRate ?>%</span></div><div class="progress-track"><span style="width: <?= $activeRate ?>%"></span></div><p class="panel-note">Satisfacción promedio: <strong><?= e(number_format((float) ($stats['satisfaccion'] ?? 0), 2)) ?>/5</strong></p><div class="metric-list"><div><span>Materias</span><strong><?= (int) ($stats['total_materias'] ?? 0) ?></strong></div><div><span>Aulas</span><strong><?= (int) ($stats['total_aulas'] ?? 0) ?></strong></div><div><span>Tutores activos</span><strong><?= (int) ($stats['tutores_campania'] ?? 0) ?></strong></div></div></article>
                <?php elseif ($role === 'tutor'): ?>
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Oferta</span><h2>Mi disponibilidad</h2></div><span class="progress-value"><?= (int) ($stats['materias_asignadas'] ?? 0) ?></span></div><p class="panel-note">Materias que impartes: <strong><?= (int) ($stats['materias_asignadas'] ?? 0) ?></strong></p><div class="availability-status <?= (int) ($stats['horarios_configurados'] ?? 0) > 0 ? 'is-available' : 'is-unavailable' ?>"><span class="availability-dot"></span><span><strong><?= (int) ($stats['horarios_configurados'] ?? 0) > 0 ? 'Disponible' : 'Sin disponibilidad' ?></strong><small><?= (int) ($stats['horarios_configurados'] ?? 0) > 0 ? (int) $stats['horarios_configurados'] . ' horario(s) publicado(s)' : 'Publica un horario para recibir grupos' ?></small></span><a href="<?= e(app_url('disponibilidad/')) ?>">Gestionar</a></div></article>
                <?php else: ?>
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Mi perfil</span><h2>Trayectoria academica</h2></div><span class="progress-value"><?= (int) ($stats['semestre'] ?? 0) > 0 ? 'S' . (int) $stats['semestre'] : '-' ?></span></div><p class="panel-note"><strong><?= e($stats['nombre_carrera'] ?? 'Carrera pendiente') ?></strong></p><div class="metric-list"><div><span>Tutorias</span><strong><?= (int) ($stats['tutorias'] ?? 0) ?></strong></div><div><span>Asistencias</span><strong><?= (int) ($stats['asistencias'] ?? 0) ?></strong></div></div></article>
                <?php endif; ?>
            </section>

            <section class="card upcoming-card">
                <div class="section-heading"><div><span class="eyebrow">Agenda</span><h2>Próximas sesiones</h2></div><a href="<?= e($role === 'estudiante' ? app_url('mis-tutorias/') : ($role === 'tutor' ? app_url('mis-grupos/') : app_url('grupos/'))) ?>">Ver todo</a></div>
                <?php if (!$upcoming): ?><div class="empty-panel"><span class="empty-icon">--</span><strong>No hay sesiones proximas</strong><p>Cuando existan sesiones programadas apareceran aqui.</p></div><?php else: ?><div class="upcoming-list"><?php foreach ($upcoming as $event): ?><div class="upcoming-item"><time datetime="<?= e($event['fecha']) ?>"><strong><?= e(date('d', strtotime((string) $event['fecha']))) ?></strong><span><?= e($monthNames[(int) date('n', strtotime((string) $event['fecha']))]) ?></span></time><span class="upcoming-info"><strong><?= e($event['nombre_materia']) ?></strong><small><?= e(substr((string) $event['hora_inicio'], 0, 5) . ' - ' . substr((string) $event['hora_fin'], 0, 5)) ?> · <?= e($event['tutor']) ?></small></span><span class="status status-<?= e($event['estado']) ?>"><?= e(ucfirst((string) $event['modalidad'])) ?></span></div><?php endforeach; ?></div><?php endif; ?>
            </section>
        </div>

        <aside class="dashboard-side-column">
            <section class="card calendar-card"><div class="section-heading"><div><span class="eyebrow">Calendario</span><h2><?= e($monthLabel) ?></h2></div><span class="calendar-mark"><?= count($upcoming) ?></span></div><div class="calendar-week"><?php foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $dayName): ?><span><?= $dayName ?></span><?php endforeach; ?></div><div class="calendar-grid"><?php for ($blank = 0; $blank < $calendarOffset; $blank++): ?><span class="calendar-day is-empty"></span><?php endfor; ?><?php for ($day = 1; $day <= $calendarDays; $day++): ?><?php $dateKey = $calendarStart->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT); $isToday = $dateKey === date('Y-m-d'); ?><span class="calendar-day <?= $isToday ? 'is-today' : '' ?> <?= !empty($calendarEvents[$dateKey]) ? 'has-event' : '' ?>"><span><?= $day ?></span><?php if (!empty($calendarEvents[$dateKey])): ?><i><?= $calendarEvents[$dateKey] ?></i><?php endif; ?></span><?php endfor; ?></div><div class="calendar-legend"><span><i class="legend-dot"></i> Sesion programada</span><span><i class="legend-today"></i> Hoy</span></div></section>

            <section class="card quick-action-card"><div class="section-heading"><div><span class="eyebrow">Atajos</span><h2>Acciones rápidas</h2></div><span class="shortcut-hint">1 click</span></div><div class="compact-actions"><?php if ($role === 'administrador'): ?><a href="<?= e(app_url('periodos/')) ?>"><span class="action-icon">CP</span><span><strong>Períodos</strong><small>Gestionar periodos de tutoría</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('grupos/')) ?>"><span class="action-icon">GR</span><span><strong>Grupos</strong><small>Supervisar y demanda</small></span><b aria-hidden="true">&rarr;</b></a><?php elseif ($role === 'tutor'): ?><a href="<?= e(app_url('mis-grupos/')) ?>"><span class="action-icon">GR</span><span><strong>Mis grupos</strong><small>Asistencia y sesiones</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('disponibilidad/')) ?>"><span class="action-icon">DI</span><span><strong>Mi disponibilidad</strong><small>Actualizar horarios</small></span><b aria-hidden="true">&rarr;</b></a><?php else: ?><a href="<?= e(app_url('tutorias/create.php')) ?>"><span class="action-icon">SA</span><span><strong>Solicitar apoyo</strong><small>Elegir materias</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('mis-evaluaciones/')) ?>"><span class="action-icon">EV</span><span><strong>Evaluaciones</strong><small>Califica a tus tutores</small></span><b aria-hidden="true">&rarr;</b></a><?php endif; ?></div></section>
        </aside>
    </div>

    <section class="card dashboard-actions dashboard-all-actions"><div class="section-heading"><div><span class="eyebrow">Acceso directo</span><h2><?= $role === 'administrador' ? 'Administración del portal' : 'Mi espacio' ?></h2></div><span class="section-hint">Todo en un solo lugar</span></div><div class="quick-links"><?php if ($role === 'administrador'): ?><a href="<?= e(app_url('periodos/')) ?>"><span>CP</span><strong>Períodos</strong><small>Períodos de tutoría</small></a><a href="<?= e(app_url('aulas/')) ?>"><span>AU</span><strong>Aulas</strong><small>Fisicas y virtuales</small></a><a href="<?= e(app_url('grupos/')) ?>"><span>GR</span><strong>Grupos</strong><small>Supervision y demanda</small></a><a href="<?= e(app_url('materias/')) ?>"><span>MA</span><strong>Materias</strong><small>Catálogo académico</small></a><a href="<?= e(app_url('reportes/campania.php')) ?>"><span>RC</span><strong>Reportes</strong><small>Métricas por periodo</small></a><a href="<?= e(app_url('usuarios/')) ?>"><span>US</span><strong>Cuentas</strong><small>Acceso y estados</small></a><?php elseif ($role === 'tutor'): ?><a href="<?= e(app_url('mis-materias/')) ?>"><span>MA</span><strong>Mis materias</strong><small>Gestionar asignaturas</small></a><a href="<?= e(app_url('disponibilidad/')) ?>"><span>DI</span><strong>Disponibilidad</strong><small>Definir horarios</small></a><a href="<?= e(app_url('mis-grupos/')) ?>"><span>GR</span><strong>Mis grupos</strong><small>Asistencia y sesiones</small></a><?php else: ?><a href="<?= e(app_url('tutorias/create.php')) ?>"><span>SA</span><strong>Solicitar apoyo</strong><small>Elegir materias</small></a><a href="<?= e(app_url('mis-tutorias/')) ?>"><span>TI</span><strong>Mis tutorias</strong><small>Grupos asignados</small></a><a href="<?= e(app_url('mis-evaluaciones/')) ?>"><span>EV</span><strong>Evaluaciones</strong><small>Calificar tutores</small></a><?php endif; ?></div></section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<?php require __DIR__ . '/layouts/footer.php'; ?>
