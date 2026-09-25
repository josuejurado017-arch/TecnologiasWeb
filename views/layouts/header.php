<?php
$title = $title ?? 'Sistema de Tutorias';
$isAuthenticated = Auth::check();
$user = $user ?? Auth::user();
$activePage = $activePage ?? '';
$role = $user['nombre_rol'] ?? '';
// Visto bueno de la coordinacion (db/028): contadores del administrador y estado
// docente del tutor. Consultas baratas; si fallan, el menu se muestra sin ellos.
$reviewCounts = ['tutores' => 0, 'grupos' => 0, 'ofertas' => 0];
$tutorHabilitacion = null;
if ($isAuthenticated) {
    try {
        if ($role === 'administrador') {
            $reviewCounts['tutores'] = (new Tutor())->countPendientes();
            $reviewCounts['grupos'] = (new Grupo())->countPorAprobar();
            $reviewCounts['ofertas'] = (new TutorMateriaConfig())->countPendientes();
        } elseif ($role === 'tutor') {
            $tutorHabilitacion = (new Tutor())->estadoDocenteByUserId((int) ($user['id_usuario'] ?? 0));
        }
    } catch (Throwable $exception) {
        error_log('Contadores de revision: ' . $exception->getMessage());
    }
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('Front/assets/css/upds.css')) ?>">
    <script>(function(){try{var theme=localStorage.getItem('upds-theme');if(theme==='light'||theme==='dark'){document.documentElement.dataset.theme=theme;}}catch(error){}})();</script>
</head>
<body class="<?= $isAuthenticated ? 'app-body' : 'auth-body' ?>">
<?php if ($isAuthenticated): ?>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar" data-sidebar>
            <div class="sidebar-brand">
                <img class="brand-logo" src="<?= e(app_url('Front/assets/img/upds-logo.svg')) ?>" alt="UPDS">
                <div>
                    <strong>Portal de tutorías</strong>
                    <span>Apoyo académico</span>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Navegación principal">
                <span class="nav-label">Workspace</span>
                <a class="nav-link <?= $activePage === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(app_url('dashboard.php')) ?>">
                    <span class="nav-icon">01</span>
                    <span>Resumen</span>
                </a>

                <?php if (($user['nombre_rol'] ?? '') === 'administrador'): ?>
                    <span class="nav-label">Administración</span>
                    <a class="nav-link <?= $activePage === 'usuarios' ? 'is-active' : '' ?>" href="<?= e(app_url('usuarios/')) ?>">
                        <span class="nav-icon">US</span>
                        <span>Cuentas de acceso</span>
                    </a>
                    <span class="nav-label">Períodos</span>
                    <a class="nav-link <?= $activePage === 'periodos' ? 'is-active' : '' ?>" href="<?= e(app_url('periodos/')) ?>">
                        <span class="nav-icon">CP</span>
                        <span>Períodos de tutoría</span>
                    </a>
                    <span class="nav-label">Catálogo académico</span>
                    <a class="nav-link <?= $activePage === 'carreras' ? 'is-active' : '' ?>" href="<?= e(app_url('carreras/')) ?>">
                        <span class="nav-icon">CA</span>
                        <span>Carreras</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'materias' ? 'is-active' : '' ?>" href="<?= e(app_url('materias/')) ?>">
                        <span class="nav-icon">MA</span>
                        <span>Materias</span>
                    </a>
                    <span class="nav-label">Perfiles académicos</span>
                    <a class="nav-link <?= $activePage === 'estudiantes' ? 'is-active' : '' ?>" href="<?= e(app_url('estudiantes/')) ?>">
                        <span class="nav-icon">ES</span>
                        <span>Estudiantes</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'tutores' ? 'is-active' : '' ?>" href="<?= e(app_url('tutores/')) ?>">
                        <span class="nav-icon">TU</span>
                        <span>Tutores</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'tutores-pendientes' ? 'is-active' : '' ?>" href="<?= e(app_url('tutores/pendientes/')) ?>">
                        <span class="nav-icon">TP</span>
                        <span>Tutores pendientes</span>
                        <?php if ($reviewCounts['tutores'] > 0): ?><span class="nav-count"><?= (int) $reviewCounts['tutores'] ?></span><?php endif; ?>
                    </a>
                    <a class="nav-link <?= $activePage === 'ofertas-tutores' ? 'is-active' : '' ?>" href="<?= e(app_url('tutores/ofertas/')) ?>">
                        <span class="nav-icon">OF</span>
                        <span>Ofertas de materias</span>
                        <?php if ($reviewCounts['ofertas'] > 0): ?><span class="nav-count"><?= (int) $reviewCounts['ofertas'] ?></span><?php endif; ?>
                    </a>
                    <span class="nav-label">Operación</span>
                    <a class="nav-link <?= $activePage === 'grupos' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/')) ?>">
                        <span class="nav-icon">GR</span>
                        <span>Grupos de tutoría</span>
                        <?php if ($reviewCounts['grupos'] > 0): ?><span class="nav-count" title="Grupos por aprobar"><?= (int) $reviewCounts['grupos'] ?></span><?php endif; ?>
                    </a>
                    <span class="nav-label">Analítica</span>
                    <a class="nav-link <?= $activePage === 'cobertura-tutores' ? 'is-active' : '' ?>" href="<?= e(app_url('cobertura-tutores/')) ?>">
                        <span class="nav-icon">CT</span>
                        <span>Cobertura de tutores</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'reportes-campania' ? 'is-active' : '' ?>" href="<?= e(app_url('reportes/campania.php')) ?>">
                        <span class="nav-icon">RC</span>
                        <span>Reportes</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'accesos' ? 'is-active' : '' ?>" href="<?= e(app_url('accesos/')) ?>">
                        <span class="nav-icon">LG</span>
                        <span>Registro de accesos</span>
                    </a>
                    <span class="nav-label">Configuración</span>
                    <a class="nav-link <?= $activePage === 'espacios' ? 'is-active' : '' ?>" href="<?= e(app_url('espacios/')) ?>">
                        <span class="nav-icon">ES</span>
                        <span>Espacios de tutoría</span>
                    </a>
                <?php endif; ?>
                <?php if ($role === 'tutor'): ?>
                    <span class="nav-label">Mi espacio</span>
                    <?php if (Auth::can('tutores')): ?><a class="nav-link <?= $activePage === 'mi-perfil-tutor' ? 'is-active' : '' ?>" href="<?= e(app_url('mi-perfil-tutor/')) ?>">
                        <span class="nav-icon">PE</span>
                        <span>Mi perfil</span>
                    </a><?php endif; ?>
                    <?php if (Auth::can('asignaciones')): ?><a class="nav-link <?= $activePage === 'mis-materias' ? 'is-active' : '' ?>" href="<?= e(app_url('mis-materias/')) ?>">
                        <span class="nav-icon">MA</span>
                        <span>Mis materias</span>
                    </a><?php endif; ?>
                    <span class="nav-label">Operación</span>
                <?php endif; ?>
                <?php if ($role === 'tutor' && Auth::can('tutorias')): ?>
                    <a class="nav-link <?= $activePage === 'mis-grupos' ? 'is-active' : '' ?>" href="<?= e(app_url('mis-grupos/')) ?>">
                        <span class="nav-icon">GR</span>
                        <span>Mis grupos</span>
                    </a>
                <?php endif; ?>
                <?php if ($role === 'estudiante' && Auth::can('tutorias')): ?>
                    <span class="nav-label">Mi espacio</span>
                    <a class="nav-link <?= $activePage === 'tutorias' ? 'is-active' : '' ?>" href="<?= e(app_url('tutorias/create.php')) ?>">
                        <span class="nav-icon">SA</span>
                        <span>Solicitar apoyo</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'mis-tutorias' ? 'is-active' : '' ?>" href="<?= e(app_url('mis-tutorias/')) ?>">
                        <span class="nav-icon">TI</span>
                        <span>Mis tutorías</span>
                    </a>
                    <?php if (Auth::can('evaluaciones')): ?><a class="nav-link <?= $activePage === 'mis-evaluaciones' ? 'is-active' : '' ?>" href="<?= e(app_url('mis-evaluaciones/')) ?>">
                        <span class="nav-icon">EV</span>
                        <span>Evaluaciones</span>
                    </a><?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <span class="avatar avatar-small"><?= e(strtoupper(substr($user['nombre'] ?? 'U', 0, 1))) ?></span>
                    <div>
                        <strong><?= e($user['nombre'] ?? 'Usuario') ?></strong>
                        <span><?= e($user['nombre_rol'] ?? '') ?></span>
                    </div>
                </div>
                <a class="nav-link logout-link" href="<?= e(app_url('logout.php')) ?>">
                    <span class="nav-icon">-&gt;</span>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </aside>

        <div class="sidebar-backdrop" data-sidebar-close></div>
        <div class="app-main">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-label="Abrir navegacion" aria-controls="sidebar" aria-expanded="false" data-sidebar-toggle>
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-context">
                    <span class="eyebrow">Sistema de apoyo académico</span>
                    <strong><?= e($title) ?></strong>
                </div>
                <div class="topbar-actions" aria-label="Acciones del portal">
                    <button class="topbar-action" type="button" title="Idioma">Aa <span>ES</span></button>
                    <button class="topbar-action highlight" type="button" title="Cambiar tema" aria-label="Cambiar tema" data-theme-toggle>
                        <span data-theme-icon aria-hidden="true">&#9728;</span>
                    </button>
                    <?php
                    if (!isset($notifications)) {
                        try {
                            $notifications = (new Notificacion())->unreadForUser((int) ($user['id_usuario'] ?? 0));
                        } catch (Throwable $exception) {
                            $notifications = [];
                        }
                    }
                    $headerNotifications = $notifications;
                    $headerNotificationCount = count($headerNotifications);
                    try {
                        $headerNotificationCount = (new Notificacion())->countUnread((int) ($user['id_usuario'] ?? 0));
                    } catch (Throwable $exception) {
                        // La interfaz conserva las alertas disponibles aunque el contador no pueda consultarse.
                    }
                    ?>
                    <div class="notification-menu" data-notifications>
                        <button class="topbar-action notification-trigger" type="button" title="Notificaciones" aria-label="Abrir notificaciones" aria-expanded="false" data-notifications-toggle>
                            <span class="notification-dot" aria-hidden="true"></span><span class="notification-label">Alertas</span>
                            <span class="notification-count" data-notification-count <?= $headerNotificationCount ? '' : 'hidden' ?>><?= $headerNotificationCount ?></span>
                        </button>
                        <div class="notification-panel" role="status" hidden data-notifications-panel>
                            <div class="panel-heading"><strong>Notificaciones</strong><span><?= $headerNotificationCount ?> nuevas</span></div>
                            <?php if (!$headerNotifications): ?>
                                <p class="panel-empty">No tienes alertas pendientes.</p>
                            <?php else: ?>
                                <?php foreach ($headerNotifications as $notification): ?>
                                    <form method="post" action="<?= e(app_url('notificaciones/read.php')) ?>" class="notification-item-form">
                                        <input type="hidden" name="id" value="<?= (int) $notification['id_notificacion'] ?>"><input type="hidden" name="redirect" value="<?= e(ltrim((string) ($notification['href'] ?? 'dashboard.php'), '/')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="notification-item" type="submit"><span class="notification-indicator notification-<?= e($notification['tone'] ?? 'info') ?>"></span><span><strong><?= e($notification['title']) ?></strong><small><?= e($notification['text']) ?></small></span></button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="user-menu" data-user-menu>
                    <button class="topbar-user" type="button" aria-haspopup="menu" aria-expanded="false" data-user-menu-toggle>
                        <span class="avatar"><?= e(strtoupper(substr($user['nombre'] ?? 'U', 0, 1))) ?></span>
                        <span class="topbar-user-copy">
                            <strong><?= e(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?></strong>
                            <span><?= e($user['nombre_rol'] ?? '') ?></span>
                        </span>
                        <span class="menu-chevron" aria-hidden="true">&#8964;</span>
                    </button>
                    <div class="user-panel" role="menu" hidden data-user-menu-panel>
                        <div class="user-panel-heading"><span class="avatar avatar-small"><?= e(strtoupper(substr($user['nombre'] ?? 'U', 0, 1))) ?></span><span><strong><?= e(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?></strong><small><?= e($user['correo'] ?? '') ?></small></span></div>
                        <?php if ($role === 'administrador'): ?>
                            <a role="menuitem" href="<?= e(app_url('usuarios/')) ?>">Cuentas de acceso</a>
                        <?php elseif ($role === 'tutor'): ?>
                            <a role="menuitem" href="<?= e(app_url('mi-perfil-tutor/')) ?>">Mi perfil</a>
                            <a role="menuitem" href="<?= e(app_url('mis-materias/')) ?>">Mis materias</a>
                        <?php else: ?>
                            <a role="menuitem" href="<?= e(app_url('mis-tutorias/')) ?>">Mis tutorías</a>
                        <?php endif; ?>
                        <a class="user-panel-logout" role="menuitem" href="<?= e(app_url('logout.php')) ?>">Cerrar sesión</a>
                    </div>
                </div>
            </header>
            <?php if ($tutorHabilitacion !== null && $tutorHabilitacion['estado_docente'] !== 'aprobado'): ?>
                <div class="notice-warning" role="status">
                    <?php if ($tutorHabilitacion['estado_docente'] === 'pendiente'): ?>
                        <strong>Tu habilitación docente está en revisión.</strong>
                        Ya puedes completar tu perfil y configurar tus materias. El sistema te propondrá grupos cuando la coordinación académica apruebe tu habilitación.
                    <?php elseif ($tutorHabilitacion['estado_docente'] === 'rechazado'): ?>
                        <strong>La coordinación no aprobó tu habilitación docente.</strong>
                        <?= $tutorHabilitacion['motivo_rechazo'] ? 'Motivo: ' . e($tutorHabilitacion['motivo_rechazo']) . '. ' : '' ?>Contacta a la coordinación académica si quieres que se reconsidere.
                    <?php else: ?>
                        <strong>Tu habilitación docente está suspendida.</strong>
                        No recibirás grupos nuevos. Contacta a la coordinación académica.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
<?php endif; ?>
