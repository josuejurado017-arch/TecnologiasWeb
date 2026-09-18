<?php

declare(strict_types=1);

// Crea o actualiza la cuenta de administrador usando la conexion de la app.
// Uso: docker compose exec web php deploy/docker/create-admin.php <usuario> <clave> [correo]

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';

[$script, $username, $password, $email] = array_pad($argv, 4, null);
if ($username === null || $password === null) {
    fwrite(STDERR, "Uso: php {$script} <usuario> <clave> [correo]\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "La clave debe tener al menos 8 caracteres.\n");
    exit(1);
}
$email = $email ?? $username . '@tutorias.local';

$pdo = Database::connection();
$roleId = $pdo->query("SELECT id_rol FROM roles WHERE nombre_rol = 'administrador' LIMIT 1")->fetchColumn();
if (!$roleId) {
    fwrite(STDERR, "No existe el rol administrador; ejecute primero db/002_seed.sql.\n");
    exit(1);
}

$statement = $pdo->prepare(
    'INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash, estado)
     VALUES (:id_rol, :nombre, :apellido, :correo, :usuario, :hash, \'activo\')
     ON DUPLICATE KEY UPDATE contrasena_hash = VALUES(contrasena_hash), estado = \'activo\''
);
$statement->execute([
    'id_rol' => (int) $roleId,
    'nombre' => 'Admin',
    'apellido' => 'Sistema',
    'correo' => $email,
    'usuario' => $username,
    'hash' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Administrador '{$username}' listo.\n";
