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
            http_response_code(403);
            exit('No tiene permisos para acceder a esta pagina.');
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array(self::user()['nombre_rol'] ?? null, $roles, true)) {
            http_response_code(403);
            exit('No tiene permisos para acceder a esta pagina.');
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

        return in_array($module, self::ROLE_MODULES[$role] ?? [], true);
    }

    public static function requireModule(string $module): void
    {
        self::requireLogin();

        if (!self::can($module)) {
            http_response_code(403);
            exit('No tiene permisos para acceder a este modulo.');
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
     */
    private const MG_ACTIONS = [
        'coordinador_mg' => ['mg.ver', 'mg.parametros', 'mg.catalogo', 'mg.importar', 'mg.expediente', 'mg.tutor',
            'mg.tribunal', 'mg.defensa', 'mg.calificacion', 'mg.documentos', 'mg.reportes', 'mg.bitacora',
            'mg.validar', 'mg.informe', 'mg.alertas'],
        // [PENDIENTE] cargos exactos del auxiliar (pregunta 1 al Coordinador). No valida reuniones (C-03).
        'auxiliar_mg' => ['mg.ver', 'mg.importar', 'mg.expediente', 'mg.tribunal', 'mg.defensa', 'mg.documentos', 'mg.reportes',
            'mg.informe', 'mg.alertas'],
        'tutor' => ['mg.propio'],
        'estudiante' => ['mg.propio'],
    ];

    public static function canDo(string $action): bool
    {
        if (!self::check()) {
            return false;
        }

        $role = self::user()['nombre_rol'] ?? '';
        if ($role === 'administrador') {
            return $action !== 'mg.propio';
        }

        return in_array($action, self::MG_ACTIONS[$role] ?? [], true);
    }

    public static function requireAction(string $action): void
    {
        self::requireLogin();

        if (!self::canDo($action)) {
            http_response_code(403);
            exit('No tiene permisos para realizar esta accion.');
        }
    }

    public static function isMgRole(): bool
    {
        return in_array(self::user()['nombre_rol'] ?? '', self::MG_ROLES, true);
    }
}
