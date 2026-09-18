<?php
$title = $title ?? 'Sistema de Tutorias';
$isAuthenticated = Auth::check();
$user = $user ?? Auth::user();
$activePage = $activePage ?? '';
$role = $user['nombre_rol'] ?? '';
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
                    <strong>Portal de tutorias</strong>
                    <span>Apoyo academico</span>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Navegacion principal">
                <span class="nav-label">Workspace</span>
                <a class="nav-link <?= $activePage === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(app_url('dashboard.php')) ?>">
                    <span class="nav-icon">01</span>
                    <span>Resumen</span>
                </a>

                <?php if (($user['nombre_rol'] ?? '') === 'administrador'): ?>
                    <span class="nav-label">Administracion</span>
                    <a class="nav-link <?= $activePage === 'usuarios' ? 'is-active' : '' ?>" href="<?= e(app_url('usuarios/')) ?>">
                        <span class="nav-icon">US</span>
                        <span>Cuentas de acceso</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'roles' ? 'is-active' : '' ?>" href="<?= e(app_url('roles/')) ?>">
                        <span class="nav-icon">RO</span>
                        <span>Roles</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'permisos' ? 'is-active' : '' ?>" href="<?= e(app_url('permisos/')) ?>">
                        <span class="nav-icon">PE</span>
                        <span>Permisos</span>
                    </a>
                    <span class="nav-label">Catalogo academico</span>
                    <a class="nav-link <?= $activePage === 'carreras' ? 'is-active' : '' ?>" href="<?= e(app_url('carreras/')) ?>">
                        <span class="nav-icon">CA</span>
                        <span>Carreras</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'materias' ? 'is-active' : '' ?>" href="<?= e(app_url('materias/')) ?>">
                        <span class="nav-icon">MA</span>
                        <span>Materias</span>
                    </a>
                    <span class="nav-label">Perfiles academicos</span>
                    <a class="nav-link <?= $activePage === 'estudiantes' ? 'is-active' : '' ?>" href="<?= e(app_url('estudiantes/')) ?>">
                        <span class="nav-icon">ES</span>
                        <span>Estudiantes</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'tutores' ? 'is-active' : '' ?>" href="<?= e(app_url('tutores/')) ?>">
                        <span class="nav-icon">TU</span>
                        <span>Tutores</span>
                    </a>
                    <span class="nav-label">Operacion</span>
                    <a class="nav-link <?= $activePage === 'asignaciones' ? 'is-active' : '' ?>" href="<?= e(app_url('asignaciones/')) ?>">
                        <span class="nav-icon">AS</span>
                        <span>Asignaciones</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'disponibilidad' ? 'is-active' : '' ?>" href="<?= e(app_url('disponibilidad/')) ?>">
                        <span class="nav-icon">DI</span>
                        <span>Disponibilidad</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'tutorias' ? 'is-active' : '' ?>" href="<?= e(app_url('tutorias/')) ?>">
                        <span class="nav-icon">TI</span>
                        <span>Tutorias</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'evaluaciones' ? 'is-active' : '' ?>" href="<?= e(app_url('evaluaciones/')) ?>">
                        <span class="nav-icon">EV</span>
                        <span>Evaluaciones</span>
                    </a>
                    <span class="nav-label">Auditoria</span>
                    <a class="nav-link <?= $activePage === 'accesos' ? 'is-active' : '' ?>" href="<?= e(app_url('accesos/')) ?>">
                        <span class="nav-icon">LG</span>
                        <span>Registro de accesos</span>
                    </a>
                    <a class="nav-link <?= $activePage === 'reportes' ? 'is-active' : '' ?>" href="<?= e(app_url('reportes/tutorias.php')) ?>">
                        <span class="nav-icon">RP</span>
                        <span>Reportes</span>
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
                    <span class="nav-label">Operacion</span>
                <?php endif; ?>
                <?php if ($role === 'tutor' && Auth::can('disponibilidad')): ?>
                    <a class="nav-link <?= $activePage === 'disponibilidad' ? 'is-active' : '' ?>" href="<?= e(app_url('disponibilidad/')) ?>">
                        <span class="nav-icon">DI</span>
                        <span>Disponibilidad</span>
                    </a>
                <?php endif; ?>
                <?php if ($role === 'estudiante'): ?>
                    <span class="nav-label">Mi espacio</span>
                    <?php if (Auth::can('materias')): ?><a class="nav-link <?= $activePage === 'materias-disponibles' ? 'is-active' : '' ?>" href="<?= e(app_url('materias-disponibles/')) ?>">
                        <span class="nav-icon">MA</span>
                        <span>Materias disponibles</span>
                    </a><?php endif; ?>
                    <?php if (Auth::can('tutores')): ?><a class="nav-link <?= $activePage === 'tutores-disponibles' ? 'is-active' : '' ?>" href="<?= e(app_url('tutores-disponibles/')) ?>">
                        <span class="nav-icon">TU</span>
                        <span>Tutores disponibles</span>
                    </a><?php endif; ?>
                    <?php if (Auth::can('disponibilidad')): ?><a class="nav-link <?= $activePage === 'horarios-disponibles' ? 'is-active' : '' ?>" href="<?= e(app_url('horarios-disponibles/')) ?>">
                        <span class="nav-icon">HO</span>
                        <span>Horarios disponibles</span>
                    </a><?php endif; ?>
                <?php endif; ?>
                <?php if (in_array($role, ['tutor', 'estudiante'], true) && Auth::can('tutorias')): ?>
                    <a class="nav-link <?= $activePage === 'tutorias' ? 'is-active' : '' ?>" href="<?= e(app_url('tutorias/')) ?>">
                        <span class="nav-icon">TI</span>
                        <span>Tutorias</span>
                    </a>
                    <?php if (Auth::can('evaluaciones')): ?><a class="nav-link <?= $activePage === 'evaluaciones' ? 'is-active' : '' ?>" href="<?= e(app_url('evaluaciones/')) ?>">
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
                    <span>Cerrar sesion</span>
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
                    <span class="eyebrow">Sistema de apoyo academico</span>
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
                            <a role="menuitem" href="<?= e(app_url('tutorias/')) ?>">Mis tutorias</a>
                        <?php endif; ?>
                        <a class="user-panel-logout" role="menuitem" href="<?= e(app_url('logout.php')) ?>">Cerrar sesion</a>
                    </div>
                </div>
            </header>
<?php endif; ?>
