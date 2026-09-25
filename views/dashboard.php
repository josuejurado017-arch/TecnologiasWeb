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
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Demanda</span><span class="stat-icon">DE</span></div><strong class="stat-value"><?= (int) ($stats['demanda_pendiente'] ?? 0) ?></strong><span class="stat-caption">en espera · <?= (int) ($stats['demanda_sin_tutor'] ?? 0) ?> sin tutor en <?= (int) ($stats['demanda_materias_sin_tutor'] ?? 0) ?> materia(s) · <?= (int) ($stats['demanda_atendidas'] ?? 0) ?> atendidas</span></article>
        </section>
        <?php $etapas = $stats['etapas'] ?? []; ?>
        <section class="card" style="margin-top:1rem;" aria-label="Formación de grupos">
            <div class="section-heading"><div><span class="eyebrow">Formación de grupos</span><h2>¿En qué etapa está cada grupo?</h2></div>
                <?php if (($etapas['listo'] ?? 0) + ($etapas['formacion'] ?? 0) > 0): ?><a class="button small" href="<?= e(app_url('grupos/?estado=' . (($etapas['listo'] ?? 0) > 0 ? 'listo' : 'formacion'))) ?>">Revisar <?= (int) (($etapas['listo'] ?? 0) + ($etapas['formacion'] ?? 0)) ?> por aprobar</a><?php endif; ?>
            </div>
            <div class="embudo-grupos">
                <a href="<?= e(app_url('grupos/#demanda')) ?>"><span class="estado-grupo estado-grupo--interes"><span aria-hidden="true">📌</span> Interés registrado</span><strong><?= (int) ($stats['demanda_pendiente'] ?? 0) ?></strong><small><?= (int) ($stats['demanda_esperando'] ?? 0) ?> esperando compañeros</small></a>
                <a href="<?= e(app_url('grupos/?estado=formacion')) ?>"><span class="estado-grupo estado-grupo--formacion"><span aria-hidden="true">🟡</span> En formación</span><strong><?= (int) ($etapas['formacion'] ?? 0) ?></strong><small>Reuniendo estudiantes</small></a>
                <a href="<?= e(app_url('grupos/?estado=listo')) ?>"><span class="estado-grupo estado-grupo--listo"><span aria-hidden="true">🟣</span> Listo para revisión</span><strong><?= (int) ($etapas['listo'] ?? 0) ?></strong><small><?= Grupo::UMBRAL_GRUPO_NORMAL ?> o más: prioridad</small></a>
                <a href="<?= e(app_url('grupos/?estado=confirmado')) ?>"><span class="estado-grupo estado-grupo--confirmado"><span aria-hidden="true">✅</span> Confirmados</span><strong><?= (int) ($etapas['confirmado'] ?? 0) ?></strong><small>Aprobados, sin iniciar</small></a>
                <a href="<?= e(app_url('grupos/?estado=en_curso')) ?>"><span class="estado-grupo estado-grupo--en_curso"><span aria-hidden="true">▶️</span> En curso</span><strong><?= (int) ($etapas['en_curso'] ?? 0) ?></strong><small>Sesiones iniciadas</small></a>
                <a href="<?= e(app_url('grupos/?estado=finalizado')) ?>"><span class="estado-grupo estado-grupo--finalizado"><span aria-hidden="true">🔵</span> Finalizados</span><strong><?= (int) ($etapas['finalizado'] ?? 0) ?></strong><small>Histórico</small></a>
            </div>
        </section>
    <?php elseif ($role === 'tutor'): ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del tutor">
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Mis grupos</span><span class="stat-icon">GR</span></div><strong class="stat-value"><?= (int) ($stats['grupos'] ?? 0) ?></strong><span class="stat-caption">En el periodo activo</span></article>
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Estudiantes</span><span class="stat-icon">ES</span></div><strong class="stat-value"><?= (int) ($stats['estudiantes'] ?? 0) ?></strong><span class="stat-caption">Atendidos</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Sesiones</span><span class="stat-icon">SE</span></div><strong class="stat-value"><?= (int) ($stats['sesiones_realizadas'] ?? 0) ?></strong><span class="stat-caption">Realizadas</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Mi promedio</span><span class="stat-icon">EV</span></div><strong class="stat-value"><?= e(number_format((float) ($stats['evaluacion_promedio'] ?? 0), 2)) ?><small>/5</small></strong><span class="stat-caption">Valoración recibida</span></article>
        </section>
        <?php $etapas = $stats['etapas'] ?? []; ?>
        <section class="card" style="margin-top:1rem;" aria-label="Mis grupos por etapa">
            <div class="section-heading"><div><span class="eyebrow">Mis grupos</span><h2>¿En qué etapa está cada uno?</h2></div><a href="<?= e(app_url('mis-grupos/')) ?>">Ver mis grupos</a></div>
            <div class="embudo-grupos">
                <a href="<?= e(app_url('mis-grupos/')) ?>"><span class="estado-grupo estado-grupo--formacion"><span aria-hidden="true">🟡</span> En formación</span><strong><?= (int) ($etapas['formacion'] ?? 0) ?></strong><small>Esperan aprobación</small></a>
                <a href="<?= e(app_url('mis-grupos/')) ?>"><span class="estado-grupo estado-grupo--listo"><span aria-hidden="true">🟣</span> Listo para revisión</span><strong><?= (int) ($etapas['listo'] ?? 0) ?></strong><small>Grupo completo</small></a>
                <a href="<?= e(app_url('mis-grupos/')) ?>"><span class="estado-grupo estado-grupo--confirmado"><span aria-hidden="true">✅</span> Confirmados</span><strong><?= (int) ($etapas['confirmado'] ?? 0) ?></strong><small>Aún no empiezan</small></a>
                <a href="<?= e(app_url('mis-grupos/')) ?>"><span class="estado-grupo estado-grupo--en_curso"><span aria-hidden="true">▶️</span> En curso</span><strong><?= (int) ($etapas['en_curso'] ?? 0) ?></strong><small>Registra la asistencia</small></a>
                <a href="<?= e(app_url('mis-grupos/')) ?>"><span class="estado-grupo estado-grupo--finalizado"><span aria-hidden="true">🔵</span> Finalizados</span><strong><?= (int) ($etapas['finalizado'] ?? 0) ?></strong><small>Histórico</small></a>
            </div>
        </section>
    <?php else: ?>
        <section class="stat-grid dashboard-stat-grid" aria-label="Resumen del estudiante">
            <article class="stat-card stat-card-teal"><div class="stat-card-top"><span class="stat-label">Mis tutorías</span><span class="stat-icon">TI</span></div><strong class="stat-value"><?= (int) ($stats['tutorias'] ?? 0) ?></strong><span class="stat-caption">Grupos asignados</span></article>
            <article class="stat-card stat-card-gold"><div class="stat-card-top"><span class="stat-label">Asistencias</span><span class="stat-icon">AS</span></div><strong class="stat-value"><?= (int) ($stats['asistencias'] ?? 0) ?></strong><span class="stat-caption">Sesiones presentes</span></article>
            <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Por evaluar</span><span class="stat-icon">EV</span></div><strong class="stat-value"><?= (int) ($stats['evaluaciones_pendientes'] ?? 0) ?></strong><span class="stat-caption">Comparte tu opinión</span></article>
            <article class="stat-card stat-card-blue"><div class="stat-card-top"><span class="stat-label">Materias</span><span class="stat-icon">MA</span></div><strong class="stat-value"><?= (int) ($stats['materias_disponibles'] ?? 0) ?><small>/<?= (int) ($stats['materias_total'] ?? 0) ?></small></strong><span class="stat-caption">con oferta<?php if (!empty($stats['en_espera'])): ?> · <?= (int) $stats['en_espera'] ?> en espera<?php endif; ?></span></article>
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
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Salud operativa</span><h2>Usuarios activos</h2></div><span class="progress-value"><?= $activeRate ?>%</span></div><div class="progress-track"><span style="width: <?= $activeRate ?>%"></span></div><p class="panel-note">Satisfacción promedio: <strong><?= e(number_format((float) ($stats['satisfaccion'] ?? 0), 2)) ?>/5</strong></p><div class="metric-list"><div><span>Materias</span><strong><?= (int) ($stats['total_materias'] ?? 0) ?></strong></div><div><span>Sin ubicación</span><strong><a href="<?= e(app_url('grupos/?estado=sin_ubicacion')) ?>" title="Grupos vigentes sin aula o enlace definido"><?= (int) ($stats['grupos_sin_ubicacion'] ?? 0) ?></a></strong></div><div><span>Tutores activos</span><strong><?= (int) ($stats['tutores_campania'] ?? 0) ?></strong></div></div>
                    <?php if ((int) ($stats['tutores_sin_horarios'] ?? 0) > 0): ?>
                        <p class="banner-warning" role="status"><?= (int) $stats['tutores_sin_horarios'] ?> tutor(es) sin horarios configurados en ninguna materia — no pueden recibir grupos. <a href="<?= e(app_url('cobertura-tutores/?filtro=sin_horarios')) ?>">Ver detalle &rarr;</a></p>
                    <?php endif; ?>
                    </article>
                <?php elseif ($role === 'tutor'): ?>
                    <article class="card progress-card"><div class="section-heading"><div><span class="eyebrow">Oferta</span><h2>Mis materias</h2></div><span class="progress-value"><?= (int) ($stats['materias_asignadas'] ?? 0) ?></span></div><p class="panel-note">Materias que impartes: <strong><?= (int) ($stats['materias_asignadas'] ?? 0) ?></strong></p><div class="availability-status <?= (int) ($stats['materias_configuradas'] ?? 0) > 0 ? 'is-available' : 'is-unavailable' ?>"><span class="availability-dot"></span><span><strong><?= (int) ($stats['materias_configuradas'] ?? 0) > 0 ? 'Disponible' : 'Sin horarios' ?></strong><small><?= (int) ($stats['materias_configuradas'] ?? 0) > 0 ? (int) $stats['materias_configuradas'] . ' de ' . (int) ($stats['materias_asignadas'] ?? 0) . ' materia(s) con horarios configurados' : 'No podrás recibir grupos de tutoría hasta configurar los horarios de al menos una materia.' ?></small></span><a href="<?= e(app_url('mis-materias/')) ?>">Gestionar</a></div></article>
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

            <section class="card quick-action-card"><div class="section-heading"><div><span class="eyebrow">Atajos</span><h2>Acciones rápidas</h2></div><span class="shortcut-hint">1 click</span></div><div class="compact-actions"><?php if ($role === 'administrador'): ?><a href="<?= e(app_url('periodos/')) ?>"><span class="action-icon">CP</span><span><strong>Períodos</strong><small>Gestionar periodos de tutoría</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('grupos/')) ?>"><span class="action-icon">GR</span><span><strong>Grupos</strong><small>Supervisar y demanda</small></span><b aria-hidden="true">&rarr;</b></a><?php elseif ($role === 'tutor'): ?><a href="<?= e(app_url('mis-grupos/')) ?>"><span class="action-icon">GR</span><span><strong>Mis grupos</strong><small>Asistencia y sesiones</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('mis-materias/')) ?>"><span class="action-icon">MA</span><span><strong>Mis materias</strong><small>Horarios por materia</small></span><b aria-hidden="true">&rarr;</b></a><?php else: ?><a href="<?= e(app_url('tutorias/create.php')) ?>"><span class="action-icon">SA</span><span><strong>Solicitar apoyo</strong><small>Elegir materias</small></span><b aria-hidden="true">&rarr;</b></a><a href="<?= e(app_url('mis-evaluaciones/')) ?>"><span class="action-icon">EV</span><span><strong>Evaluaciones</strong><small>Califica a tus tutores</small></span><b aria-hidden="true">&rarr;</b></a><?php endif; ?></div></section>
        </aside>
    </div>

    <section class="card dashboard-actions dashboard-all-actions"><div class="section-heading"><div><span class="eyebrow">Acceso directo</span><h2><?= $role === 'administrador' ? 'Administración del portal' : 'Mi espacio' ?></h2></div><span class="section-hint">Todo en un solo lugar</span></div><div class="quick-links"><?php if ($role === 'administrador'): ?><a href="<?= e(app_url('periodos/')) ?>"><span>CP</span><strong>Períodos</strong><small>Períodos de tutoría</small></a><a href="<?= e(app_url('grupos/?estado=sin_ubicacion')) ?>"><span>UB</span><strong>Ubicaciones</strong><small>Aulas y enlaces pendientes</small></a><a href="<?= e(app_url('grupos/')) ?>"><span>GR</span><strong>Grupos</strong><small>Supervision y demanda</small></a><a href="<?= e(app_url('materias/')) ?>"><span>MA</span><strong>Materias</strong><small>Catálogo académico</small></a><a href="<?= e(app_url('reportes/campania.php')) ?>"><span>RC</span><strong>Reportes</strong><small>Métricas por periodo</small></a><a href="<?= e(app_url('usuarios/')) ?>"><span>US</span><strong>Cuentas</strong><small>Acceso y estados</small></a><?php elseif ($role === 'tutor'): ?><a href="<?= e(app_url('mis-materias/')) ?>"><span>MA</span><strong>Mis materias</strong><small>Asignaturas y horarios</small></a><a href="<?= e(app_url('mis-grupos/')) ?>"><span>GR</span><strong>Mis grupos</strong><small>Asistencia y sesiones</small></a><?php else: ?><a href="<?= e(app_url('tutorias/create.php')) ?>"><span>SA</span><strong>Solicitar apoyo</strong><small>Elegir materias</small></a><a href="<?= e(app_url('mis-tutorias/')) ?>"><span>TI</span><strong>Mis tutorias</strong><small>Grupos asignados</small></a><a href="<?= e(app_url('mis-evaluaciones/')) ?>"><span>EV</span><strong>Evaluaciones</strong><small>Calificar tutores</small></a><?php endif; ?></div></section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<?php require __DIR__ . '/layouts/footer.php'; ?>
