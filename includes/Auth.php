<?php

declare(strict_types=1);

final class Auth
{
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        unset($user['contrasena_hash']);
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id_usuario']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Relee la cuenta en cada peticion: una cuenta desactivada (o borrada) pierde la
     * sesion de inmediato, y el rol y el estado de la sesion son siempre los de la
     * base. Devuelve false si la sesion se cerro.
     */
    public static function revalidar(): bool
    {
        if (!self::check()) {
            return true;
        }

        $statement = Database::connection()->prepare(
            'SELECT u.estado, u.id_rol, r.nombre_rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = :id LIMIT 1'
        );
        $statement->execute(['id' => (int) $_SESSION['user']['id_usuario']]);
        $cuenta = $statement->fetch();
        if (!$cuenta || $cuenta['estado'] !== 'activo') {
            self::logout();

            return false;
        }

        $_SESSION['user']['estado'] = $cuenta['estado'];
        $_SESSION['user']['id_rol'] = $cuenta['id_rol'];
        $_SESSION['user']['nombre_rol'] = $cuenta['nombre_rol'];

        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . app_url('login.php'));
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();

        if ((self::user()['nombre_rol'] ?? null) !== $role) {
            self::denegar('No tienes permisos para acceder a esta página.');
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array(self::user()['nombre_rol'] ?? null, $roles, true)) {
            self::denegar('No tienes permisos para acceder a esta página.');
        }
    }

    /**
     * Capacidades fijas por rol (modelo institucional: 3 roles, sin matriz dinámica).
     * El administrador tiene acceso total. Reemplaza a la antigua tabla permisos_rol.
     */
    private const ROLE_MODULES = [
        // tutores = Mi perfil de tutor; asignaciones = Mis materias; tutorias = Mis grupos y asistencia.
        'tutor' => ['dashboard', 'tutores', 'tutorias', 'asignaciones'],
        // tutorias = Solicitar apoyo y Mis tutorias; evaluaciones = Mis evaluaciones.
        'estudiante' => ['dashboard', 'evaluaciones', 'tutorias'],
    ];

    public static function can(string $module): bool
    {
        if (!self::check()) {
            return false;
        }

        $role = self::user()['nombre_rol'] ?? '';
        if ($role === 'administrador') {
            return true;
        }

        // Con un expediente de grado activo el estudiante trabaja solo en Modalidades de
        // Grado: se le cierran las tutorias y evaluaciones (db/049).
        if ($role === 'estudiante' && in_array($module, ['tutorias', 'evaluaciones'], true) && self::enModoGrado()) {
            return false;
        }

        return in_array($module, self::ROLE_MODULES[$role] ?? [], true);
    }

    /**
     * Estudiante con un expediente de Modalidades de Grado activo: su portal es solo el
     * de grado. Si el expediente se cierra sin aprobar (reprobado, abandono, retirado)
     * o aun no existe, vuelve a ver las tutorias. Se consulta una vez por peticion.
     */
    public static function enModoGrado(): bool
    {
        static $cache = [];
        if (!self::check() || (self::user()['nombre_rol'] ?? '') !== 'estudiante') {
            return false;
        }
        $userId = (int) self::user()['id_usuario'];
        if (!isset($cache[$userId])) {
            try {
                $consulta = Database::connection()->prepare(
                    "SELECT 1 FROM expedientes_mg e INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
                     WHERE es.id_usuario = :u AND e.estado = 'activo' LIMIT 1"
                );
                $consulta->execute(['u' => $userId]);
                $cache[$userId] = (bool) $consulta->fetchColumn();
            } catch (Throwable $exception) {
                error_log('Modo grado: ' . $exception->getMessage());
                $cache[$userId] = false;
            }
        }

        return $cache[$userId];
    }

    public static function requireModule(string $module): void
    {
        self::requireLogin();

        if (!self::can($module)) {
            self::denegar('No tienes permisos para acceder a este módulo.');
        }
    }

    /** Roles del equipo de Modalidades de Grado (db/044). Su inicio es /mg/. */
    public const MG_ROLES = ['coordinador_mg', 'auxiliar_mg'];

    /**
     * Permisos por accion de Modalidades de Grado (docs/analisis/plan-mg-ajustado.md §2).
     * Fijos en codigo, como ROLE_MODULES: la matriz dinamica se elimino en db/017.
     * El administrador puede todo. 'mg.propio' = ver solo lo propio (tutor: sus
     * tesistas; estudiante: su expediente y sus notas publicadas).
     * MVP-2: 'mg.validar' = validar, observar y corregir reuniones (HU-035);
     * 'mg.informe' = registrar informes en nombre del tutor (HU-037);
     * 'mg.alertas' = panel de alertas y marcarlas atendidas (HU-038).
     * El tutor registra sus reuniones e informes con 'mg.propio' si es el tutor vigente.
     * Solicitudes: 'mg.solicitar' (estudiante: enviar y corregir la propia) y
     * 'mg.solicitudes' (Coordinacion: ver el documento y aprobar, observar o rechazar).
     */
    private const MG_ACTIONS = [
        'coordinador_mg' => ['mg.ver', 'mg.parametros', 'mg.catalogo', 'mg.importar', 'mg.expediente', 'mg.tutor',
            'mg.tribunal', 'mg.defensa', 'mg.calificacion', 'mg.documentos', 'mg.reportes', 'mg.bitacora',
            'mg.validar', 'mg.informe', 'mg.alertas', 'mg.solicitudes'],
        // [PENDIENTE] cargos exactos del auxiliar (pregunta 1 al Coordinador). No valida reuniones (C-03).
        'auxiliar_mg' => ['mg.ver', 'mg.importar', 'mg.expediente', 'mg.tribunal', 'mg.defensa', 'mg.documentos', 'mg.reportes',
            'mg.informe', 'mg.alertas'],
        'tutor' => ['mg.propio'],
        // mg.solicitar = pedir la modalidad de grado con su documento de notas (db/049).
        'estudiante' => ['mg.propio', 'mg.solicitar'],
    ];

    public static function canDo(string $action): bool
    {
        if (!self::check()) {
            return false;
        }

        $role = self::user()['nombre_rol'] ?? '';
        if ($role === 'administrador') {
            return !in_array($action, ['mg.propio', 'mg.solicitar'], true);
        }

        return in_array($action, self::MG_ACTIONS[$role] ?? [], true);
    }

    public static function requireAction(string $action): void
    {
        self::requireLogin();

        if (!self::canDo($action)) {
            self::denegar('No tienes permisos para realizar esta acción.');
        }
    }

    /**
     * Respuesta 403 con una pagina clara en vez de texto plano. El caso mas comun:
     * se inicio sesion con otra cuenta en otra pestana del mismo navegador (la
     * sesion es una sola por navegador) y al volver atras la pagina es de otro rol.
     */
    public static function denegar(string $mensaje): never
    {
        http_response_code(403);
        $user = self::user() ?? [];
        $roles = ['administrador' => 'Administrador', 'tutor' => 'Tutor', 'estudiante' => 'Estudiante',
            'coordinador_mg' => 'Coordinación de Modalidades de Grado', 'auxiliar_mg' => 'Auxiliar de Modalidades de Grado'];
        $nombre = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''));
        $rol = $roles[$user['nombre_rol'] ?? ''] ?? ($user['nombre_rol'] ?? '');
        $inicio = app_url(self::isMgRole() ? 'mg/' : 'dashboard.php');
        $css = app_url('Front/assets/css/upds.css');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Sin permiso</title><link rel="stylesheet" href="' . e($css) . '"></head><body>'
            . '<main class="container" style="max-width:40rem;margin:4rem auto;padding:0 1rem;"><div class="card" style="padding:1.5rem;">'
            . '<h1>Sin permiso</h1><p>' . e($mensaje) . '</p>'
            . ($nombre !== '' ? '<p>Estás con la sesión de <strong>' . e($nombre) . '</strong> (' . e($rol) . ').</p>' : '')
            . '<p class="panel-note">Si iniciaste sesión con otra cuenta en otra pestaña de este navegador, esa sesión reemplazó a la anterior en todas las pestañas.'
            . ' Para usar dos cuentas a la vez, abre la otra en una ventana de incógnito o en otro navegador.</p>'
            . '<p style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1rem;"><a class="button" href="' . e($inicio) . '">Ir a mi inicio</a>'
            . '<a class="button secondary" href="' . e(app_url('logout.php')) . '">Cambiar de usuario</a></p>'
            . '</div></main></body></html>';
        exit;
    }

    public static function isMgRole(): bool
    {
        return in_array(self::user()['nombre_rol'] ?? '', self::MG_ROLES, true);
    }
}
