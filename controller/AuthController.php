<?php

declare(strict_types=1);

final class AuthController
{
    public function login(string $username, string $password): ?string
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return 'Ingrese su usuario y contrasena.';
        }

        try {
            $model = new Usuario();
            $user = $model->findForLogin($username);

            if (!$user || !password_verify($password, $user['contrasena_hash'])) {
                if ($user) {
                    $model->registerAccess((int) $user['id_usuario'], 'fallido');
                }
                return 'Las credenciales no son válidas.';
            }

            if ($user['estado'] !== 'activo') {
                $model->registerAccess((int) $user['id_usuario'], 'fallido');
                return 'Tu cuenta esta inactiva. Contacta al administrador.';
            }

            $model->registerAccess((int) $user['id_usuario'], 'exitoso');
            Auth::login($user);
            header('Location: ' . app_url('dashboard.php'));
            exit;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return 'No fue posible conectar con el sistema. Revise la configuracion.';
        }
    }
}
