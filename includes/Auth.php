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
}
